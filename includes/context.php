<?php
/**
 * The run context: what every check needs to know about the site, plus the
 * loopback fetcher that the rendered-HTML checks share.
 *
 * Loopback requests go to the site's own home_url() only, sequentially, with
 * a 10 second timeout each and a cache-bust query string so the edge cache
 * cannot serve stale HTML. A URL that fails is remembered so no second check
 * waits on it again, and the failure surfaces as one `info` finding per URL
 * through failed_fetches().
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Context {

	/**
	 * Site host (home_url host, lowercase).
	 *
	 * @var string
	 */
	public $host;

	/**
	 * wp_get_environment_type().
	 *
	 * @var string
	 */
	public $environment;

	/**
	 * The environment the checks act on: production or staging decided by the
	 * host name, local by the constant. See omens_audit_effective_environment().
	 *
	 * @var string
	 */
	public $effective_environment;

	/**
	 * custom|purchased. See omens_audit_build_type().
	 *
	 * @var string
	 */
	public $build_type;

	/**
	 * How build_type was decided.
	 *
	 * @var string
	 */
	public $build_evidence;

	/**
	 * Whether this is a dry run (nothing stored).
	 *
	 * @var bool
	 */
	public $dry_run = false;

	/**
	 * Sample URLs, built lazily.
	 *
	 * @var string[]|null
	 */
	private $sample = null;

	/**
	 * Fetched HTML by URL (the un-busted URL is the key).
	 *
	 * @var array<string, string>
	 */
	private $html = array();

	/**
	 * Fetch failures by URL: the reason.
	 *
	 * @var array<string, string>
	 */
	private $failed = array();

	/**
	 * Seconds spent in loopback requests.
	 *
	 * @var float
	 */
	public $fetch_seconds = 0.0;

	/**
	 * Build the context for this site.
	 *
	 * @param array $opts { dry_run }.
	 */
	public function __construct( array $opts = array() ) {
		$this->host           = omens_audit_host();
		$this->environment    = wp_get_environment_type();
		$this->effective_environment = omens_audit_effective_environment( $this->host, $this->environment );
		$build                = omens_audit_build_type();
		$this->build_type     = $build['type'];
		$this->build_evidence = $build['evidence'];
		$this->dry_run        = ! empty( $opts['dry_run'] );
	}

	/**
	 * The rendered-HTML sample for this run (PLAN section 4.1).
	 *
	 * @return string[]
	 */
	public function sample() {
		if ( null === $this->sample ) {
			$this->sample = omens_audit_sample_urls();
		}
		return $this->sample;
	}

	/**
	 * Fetch a page of this site over loopback. Returns the HTML or null.
	 *
	 * @param string $url A URL on home_url(); anything else is refused.
	 * @return string|null
	 */
	public function fetch( $url ) {
		$url = (string) $url;
		if ( isset( $this->html[ $url ] ) ) {
			return $this->html[ $url ];
		}
		if ( isset( $this->failed[ $url ] ) ) {
			return null;
		}
		if ( ! $this->is_own_url( $url ) ) {
			$this->failed[ $url ] = 'not on this site; the audit only fetches its own home_url()';
			return null;
		}
		// A site mid-update serves its maintenance page to every request, and that page passed
		// the rendered checks once as if it were the home page (no footer, so every legal link
		// "missing"). Refuse to fetch while the .maintenance file is live; the failures surface
		// as run.fetch-failed and the rendered checks skip the URL.
		if ( $this->in_maintenance() ) {
			$this->failed[ $url ] = 'site is in maintenance mode (.maintenance present); rendered checks skipped this run';
			return null;
		}

		/**
		 * Loopback request arguments. Timeout is fixed at 10 seconds per URL
		 * by the plan; sslverify may need to be off on a local site with a
		 * self-signed certificate.
		 *
		 * @param array  $args wp_remote_get() arguments.
		 * @param string $url  URL being fetched.
		 */
		$args = apply_filters(
			'omens_audit/fetch_args',
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'sslverify'   => 'local' !== $this->environment,
				'user-agent'  => 'OmensSiteAudit/' . OMENS_AUDIT_VERSION . ' (+https://omens.agency)',
				'headers'     => array(
					'Cache-Control' => 'no-cache',
					'X-Omens-Audit' => '1',
				),
			),
			$url
		);
		// The ?x=1 lesson: Pressable's edge cache ignores Cache-Control, so the
		// query string is what actually gets a fresh render.
		$busted = add_query_arg( 'omens-audit', (string) time(), $url );

		$start                = microtime( true );
		$response             = wp_remote_get( $busted, $args );
		$this->fetch_seconds += microtime( true ) - $start;

		if ( is_wp_error( $response ) ) {
			$this->failed[ $url ] = $response->get_error_message();
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$this->failed[ $url ] = 'HTTP ' . $code;
			return null;
		}
		$body = (string) wp_remote_retrieve_body( $response );
		if ( '' === trim( $body ) ) {
			$this->failed[ $url ] = 'empty body';
			return null;
		}
		// A password gate, "coming soon" or maintenance plugin answers 200 with a page that is
		// not the site. Checked against the rendered home page once, it reported every footer
		// link missing. A page with a password field and no main or footer landmark is a gate.
		if ( omens_audit_looks_gated( $body ) ) {
			$this->failed[ $url ] = 'response is a password gate or holding page, not the site; rendered checks skipped this URL';
			return null;
		}
		$this->html[ $url ] = $body;
		return $body;
	}

	/**
	 * Parsed DOMs by URL.
	 *
	 * @var array<string, DOMDocument|null>
	 */
	private $doms = array();

	/**
	 * Fetch and parse a page; null when it could not be fetched.
	 *
	 * @param string $url URL on this site.
	 * @return DOMDocument|null
	 */
	public function dom( $url ) {
		$url = (string) $url;
		if ( ! array_key_exists( $url, $this->doms ) ) {
			$html               = $this->fetch( $url );
			$this->doms[ $url ] = null === $html ? null : omens_audit_dom( $html );
		}
		return $this->doms[ $url ];
	}

	/**
	 * Whether WordPress is in maintenance mode right now (the .maintenance file
	 * exists and is under ten minutes old, the same rule core applies).
	 *
	 * @return bool
	 */
	public function in_maintenance() {
		if ( function_exists( 'wp_is_maintenance_mode' ) ) {
			return (bool) wp_is_maintenance_mode();
		}
		/**
		 * Path of the .maintenance file. Tests point it at a temp folder.
		 *
		 * @param string $file Default ABSPATH . '.maintenance'.
		 */
		$file = (string) apply_filters( 'omens_audit/maintenance_file', ABSPATH . '.maintenance' );
		if ( ! file_exists( $file ) ) {
			return false;
		}
		$upgrading = 0;
		include $file; // Defines $upgrading, as core does.
		return ( (int) $upgrading + 10 * MINUTE_IN_SECONDS ) >= time();
	}

	/**
	 * URLs that could not be fetched this run, with the reason.
	 *
	 * @return array<string, string>
	 */
	public function failed_fetches() {
		return $this->failed;
	}

	/**
	 * URLs fetched this run.
	 *
	 * @return string[]
	 */
	public function fetched_urls() {
		return array_keys( $this->html );
	}

	/**
	 * Whether a URL is on this site's home_url() host and scheme.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public function is_own_url( $url ) {
		$home = wp_parse_url( home_url() );
		$test = wp_parse_url( $url );
		if ( ! is_array( $test ) || empty( $test['host'] ) || empty( $test['scheme'] ) ) {
			return false;
		}
		return strtolower( $test['host'] ) === strtolower( (string) ( $home['host'] ?? '' ) )
			&& in_array( strtolower( $test['scheme'] ), array( 'http', 'https' ), true )
			&& ( $test['port'] ?? null ) === ( $home['port'] ?? null );
	}
}
