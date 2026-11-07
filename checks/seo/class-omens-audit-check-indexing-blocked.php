<?php
/**
 * seo.indexing-blocked: production that search engines cannot index, or
 * staging that they can. The same inversion qa.py uses.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Indexing_Blocked extends Omens_Audit_Check_Base {

	public function id() {
		return 'seo.indexing-blocked';
	}

	public function family() {
		return 'seo';
	}

	public function title() {
		return 'Indexing blocked on production, or open on staging';
	}

	public function severity() {
		return 'critical';
	}

	public function applies( Omens_Audit_Context $ctx ) {
		return 'local' === $ctx->effective_environment ? 'local environment' : true;
	}

	public function run( Omens_Audit_Context $ctx ) {
		$findings  = array();
		$env       = $ctx->environment;
		$looks_dev = omens_audit_host_looks_nonproduction( $ctx->host );
		// The host decides in both directions. 1.0.0 trusted it one way only, so a production
		// domain whose constant still said "staging" was told to discourage search engines.
		$effective = $ctx->effective_environment;

		if ( ( 'production' === $env ) === $looks_dev ) {
			$findings[] = $this->finding(
				array(
					'subject'  => 'environment-mismatch',
					'severity' => 'info',
					'title'    => sprintf( 'WP_ENVIRONMENT_TYPE is %s but the host %s looks like %s', $env, $ctx->host, $looks_dev ? 'staging' : 'production' ),
					'summary'  => 'The audit trusts the host name (this site is treated as ' . $effective . '); the constant should be corrected so every other tool agrees.',
					'fix'      => array(
						'route'      => 'config',
						'where'      => 'wp-config.php (or the Pressable environment setting)',
						'steps'      => array( "Set WP_ENVIRONMENT_TYPE to '" . $effective . "' on this site." ),
						'confidence' => 'medium',
					),
				)
			);
		}

		$public  = '0' !== (string) get_option( 'blog_public', '1' );
		$dom     = $ctx->dom( home_url( '/' ) );
		$robots  = $dom ? omens_audit_robots( $dom ) : array();
		$noindex = in_array( 'noindex', $robots, true ) || in_array( 'none', $robots, true );
		$blocked = ! $public || $noindex;
		$why     = array();
		if ( ! $public ) {
			$why[] = 'Settings > Reading has "Discourage search engines" on (blog_public = 0)';
		}
		if ( $noindex ) {
			$why[] = 'the home page carries <meta name="robots" content="' . implode( ',', $robots ) . '">';
		}

		if ( 'production' === $effective && $blocked ) {
			$findings[] = $this->finding(
				array(
					'subject'  => 'production-noindex',
					'title'    => 'Production site is telling search engines not to index it',
					'summary'  => implode( ' and ', $why ) . '.',
					'url'      => home_url( '/' ),
					'fix'      => array(
						'route'      => 'config',
						'where'      => ! $public ? 'Settings > Reading' : 'the SEO plugin\'s robots settings for the home page',
						'steps'      => array(
							! $public ? 'Untick "Discourage search engines from indexing this site" and save.' : 'Remove the noindex directive for the front page in the SEO plugin (or the theme if it prints the meta itself).',
							'Request indexing of the home page in Search Console once the tag is gone.',
						),
						'confidence' => 'high',
					),
					'evidence' => $why,
				)
			);
		} elseif ( 'production' !== $effective && ! $blocked ) {
			$findings[] = $this->finding(
				array(
					'subject'  => 'staging-indexable',
					'severity' => 'serious',
					'title'    => 'Staging site is indexable',
					'summary'  => 'blog_public is 1 and the home page has no noindex, so search engines can index a duplicate of the production site.',
					'url'      => home_url( '/' ),
					'fix'      => array(
						'route'      => 'config',
						'where'      => 'Settings > Reading',
						'steps'      => array( 'Tick "Discourage search engines from indexing this site" on staging (environment-sync.md lists it among the options that must never travel to production).' ),
						'confidence' => 'high',
					),
					'evidence' => array( 'blog_public ' . get_option( 'blog_public', '1' ), 'robots meta: ' . ( $robots ? implode( ',', $robots ) : 'none' ) ),
				)
			);
		}
		return $findings;
	}
}
