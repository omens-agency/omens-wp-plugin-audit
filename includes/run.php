<?php
/**
 * The run engine: build the context, run the selected checks, apply the
 * severity filter and the purchased-site routing rule, add the loopback
 * failures and the inventories, and store the result as the latest run.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run the audit.
 *
 * @param array         $opts {
 *     @type string   $family   Only checks in this family.
 *     @type string[] $checks   Only these check IDs.
 *     @type bool     $dry_run  Do not store the run.
 * }
 * @param callable|null $progress Called as ( $event, $check_id, $data ) with
 *                                events `start`, `done`, `skip`, `failed`.
 *                                WP-CLI prints one line per event so a run
 *                                invoked over a transport that kills silent
 *                                commands (the Pressable MCP, 30 seconds) keeps
 *                                talking.
 * @return array The run.
 */
function omens_audit_run( array $opts = array(), $progress = null ) {
	$started = microtime( true );
	$ctx     = new Omens_Audit_Context( array( 'dry_run' => ! empty( $opts['dry_run'] ) ) );
	$notify  = is_callable( $progress ) ? $progress : static function () {};

	$selected = omens_audit_select_checks( $opts );
	$findings = array();
	$ran      = array();

	foreach ( $selected as $id => $check ) {
		$t0 = microtime( true );
		$notify( 'start', $id, array() );
		$status = 'ran';
		$out    = array();
		try {
			$applies = $check->applies( $ctx );
			if ( true !== $applies ) {
				$status = 'skipped';
				$out[]  = omens_audit_finding(
					array(
						'check'         => $id,
						'subject'       => 'not-applicable',
						'family'        => $check->family(),
						'severity'      => 'info',
						'title'         => sprintf( '%s did not run', $id ),
						'summary'       => is_string( $applies ) ? $applies : 'not applicable on this site',
						'fix'           => array(
							'route' => 'human',
							'where' => 'the audit itself',
							'steps' => array( 'Nothing to do: the check does not apply on this site.' ),
						),
						'check_version' => $check->version(),
					)
				);
				$notify( 'skip', $id, array( 'reason' => is_string( $applies ) ? $applies : 'not applicable' ) );
			} else {
				$out = (array) $check->run( $ctx );
				// A check that could not do its job (its one cannot-run finding) looked at
				// nothing: record that, so the central run never reads its open tasks as
				// re-checked. Seen live when wp plugin install emptied the update_plugins transient.
				if ( 1 === count( $out ) && 'cannot-run' === ( $out[0]['subject'] ?? '' ) ) {
					$status = 'cannot-run';
				}
			}
		} catch ( \Throwable $e ) {
			$status = 'failed';
			$out    = array(
				omens_audit_finding(
					array(
						'check'    => 'run.check-failed',
						'subject'  => $id,
						'family'   => 'run',
						'severity' => 'info',
						'title'    => sprintf( 'Check %s threw an error', $id ),
						'summary'  => get_class( $e ) . ': ' . $e->getMessage(),
						'detail'   => $e->getFile() . ':' . $e->getLine(),
						'fix'      => array(
							'route' => 'human',
							'where' => 'omens-wp-plugin-audit',
							'steps' => array( 'Fix the check; the site itself may be fine.' ),
						),
					)
				),
			);
			$notify( 'failed', $id, array( 'error' => $e->getMessage() ) );
		}

		foreach ( $out as $finding ) {
			if ( ! is_array( $finding ) || empty( $finding['fingerprint'] ) ) {
				continue;
			}
			$findings[] = omens_audit_finalize_finding( $finding, $ctx );
		}
		$ran[ $id ] = array(
			'version' => $check->version(),
			'status'  => $status,
			'ms'      => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
		);
		$notify(
			'done',
			$id,
			array(
				'status'   => $status,
				'findings' => count( $out ),
				'ms'       => $ran[ $id ]['ms'],
			)
		);
	}

	// Every loopback URL that failed is one info finding, so a heavy page shows up without sinking the run.
	foreach ( $ctx->failed_fetches() as $url => $reason ) {
		$findings[] = omens_audit_finalize_finding(
			omens_audit_finding(
				array(
					'check'    => 'run.fetch-failed',
					'subject'  => $url,
					'family'   => 'run',
					'severity' => 'info',
					'title'    => 'Page could not be fetched over loopback',
					'summary'  => $reason,
					'url'      => $url,
					'fix'      => array(
						'route' => 'human',
						'where' => $url,
						'steps' => array( 'Open the page; if it is slow or erroring, that is the finding. The rendered-HTML checks skipped it.' ),
					),
					'evidence' => array( 'wp_remote_get with a 10 second timeout: ' . $reason ),
				)
			),
			$ctx
		);
	}

	// The pieces below run outside the per-check try: a third-party filter that throws in one
	// of them (a post_link or pre_get_users callback under WP-CLI) used to lose every check's
	// result. Each now fails on its own and is named in `errors`.
	$errors = array();
	$guard  = function ( $label, callable $piece, $fallback ) use ( &$errors ) {
		try {
			return $piece();
		} catch ( \Throwable $e ) {
			$errors[] = $label . ': ' . $e->getMessage();
			return $fallback;
		}
	};

	$run = array(
		'schema'         => OMENS_AUDIT_SCHEMA,
		'plugin_version' => OMENS_AUDIT_VERSION,
		'run_at'         => gmdate( 'c' ),
		'host'           => $ctx->host,
		'environment'    => $ctx->environment,
		'build_type'     => $ctx->build_type,
		'build_evidence' => $ctx->build_evidence,
		'theme'          => $guard( 'theme', 'omens_audit_theme_info', array() ),
		'wp_version'     => get_bloginfo( 'version' ),
		'php_version'    => PHP_VERSION,
		'locale'         => get_locale(),
		'dry_run'        => $ctx->dry_run,
		'selection'      => array(
			'family' => (string) ( $opts['family'] ?? '' ),
			'checks' => array_values( (array) ( $opts['checks'] ?? array() ) ),
		),
		'sample'         => $guard( 'sample', array( $ctx, 'sample' ), array() ),
		'fetched'        => $guard( 'fetched', array( $ctx, 'fetched_urls' ), array() ),
		'checks'         => $ran,
		'findings'       => $findings,
		'counts'         => omens_audit_counts( $findings ),
		'inventory'      => array(
			'admins'     => $guard( 'inventory.admins', 'omens_audit_inventory_admins', array() ),
			'mu_plugins' => $guard( 'inventory.mu_plugins', 'omens_audit_inventory_mu_plugins', array() ),
			'plugins'    => $guard( 'inventory.plugins', 'omens_audit_inventory_plugins', array() ),
			'site_facts' => $guard( 'inventory.site_facts', 'omens_audit_inventory_site_facts', array() ),
		),
		'fetch_seconds'  => round( $ctx->fetch_seconds, 2 ),
		'duration_ms'    => (int) round( ( microtime( true ) - $started ) * 1000 ),
	);

	$run['errors'] = $errors;

	// The caller is told whether the run was saved. `export` reads the saved run, so a run that
	// could not be saved would otherwise hand the collector last month's result as this month's.
	$stored = false;
	if ( ! $ctx->dry_run ) {
		try {
			$stored = (bool) omens_audit_store_run( $run );
		} catch ( \Throwable $e ) {
			$run['errors'][] = 'store: ' . $e->getMessage();
		}
	}
	$run['stored'] = $stored;
	return $run;
}

/**
 * Which checks to run for the given options.
 *
 * @param array $opts { family, checks }.
 * @return array<string, Omens_Audit_Check>
 */
function omens_audit_select_checks( array $opts ) {
	$all      = omens_audit_checks();
	$selected = $all;
	if ( ! empty( $opts['checks'] ) ) {
		$selected = array_intersect_key( $all, array_fill_keys( (array) $opts['checks'], true ) );
	}
	if ( ! empty( $opts['family'] ) ) {
		$selected = array_filter(
			$selected,
			static function ( $check ) use ( $opts ) {
				return $check->family() === $opts['family'];
			}
		);
	}
	return $selected;
}

/**
 * Apply the site's severity filter and the purchased-site routing rule.
 *
 * @param array               $finding Finding.
 * @param Omens_Audit_Context $ctx     Context.
 * @return array
 */
function omens_audit_finalize_finding( array $finding, Omens_Audit_Context $ctx ) {
	/**
	 * Adjust a finding's severity for this site.
	 *
	 * @param string $severity Severity.
	 * @param string $check    Check ID.
	 * @param array  $finding  The whole finding.
	 */
	$severity = apply_filters( 'omens_audit/severity', $finding['severity'], $finding['check'], $finding );
	if ( in_array( $severity, omens_audit_severities(), true ) ) {
		$finding['severity'] = $severity;
	}
	if ( 'purchased' === $ctx->build_type && 'code' === $finding['fix']['route'] ) {
		$finding['fix']['route'] = 'human';
		$finding['fix']['where'] = trim( 'theme or child theme; no repo. ' . $finding['fix']['where'] );
	}
	return $finding;
}

/**
 * Counts by severity and by family, for the summary and the admin screen.
 *
 * @param array[] $findings Findings.
 * @return array
 */
function omens_audit_counts( array $findings ) {
	$counts = array(
		'total'    => count( $findings ),
		'severity' => array_fill_keys( omens_audit_severities(), 0 ),
		'family'   => array(),
	);
	foreach ( $findings as $f ) {
		++$counts['severity'][ $f['severity'] ];
		$counts['family'][ $f['family'] ] = ( $counts['family'][ $f['family'] ] ?? 0 ) + 1;
	}
	ksort( $counts['family'] );
	return $counts;
}
