<?php
/**
 * The only state: the latest run, in one non-autoloaded option, keyed by
 * environment and host so a staging clone of production never reports
 * production's findings.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store a run as the latest. Replaces whatever was there.
 *
 * @param array $run Run array from omens_audit_run().
 * @return bool
 */
function omens_audit_store_run( array $run ) {
	$write = function ( array $run ) {
		if ( false === get_option( OMENS_AUDIT_OPTION, false ) ) {
			return (bool) add_option( OMENS_AUDIT_OPTION, $run, '', false );
		}
		return (bool) update_option( OMENS_AUDIT_OPTION, $run, false );
	};
	if ( $write( $run ) ) {
		return true;
	}
	// wpdb refuses a value its column cannot hold: invalid UTF-8, or a four-byte character
	// (an emoji in a post title) on a utf8 rather than utf8mb4 options table. Make the text
	// safe and try once more, so one odd title cannot cost the site its whole run.
	array_walk_recursive(
		$run,
		function ( &$v ) {
			if ( is_string( $v ) ) {
				$v = omens_audit_clean_utf8( $v );
				if ( function_exists( 'wp_encode_emoji' ) ) {
					$v = wp_encode_emoji( $v );
				}
			}
		}
	);
	return $write( $run );
}

/**
 * The latest run, or null when there is none or it belongs to another site.
 *
 * @param string|null $reason Set to why null was returned.
 * @return array|null
 */
function omens_audit_get_run( &$reason = null ) {
	$run = get_option( OMENS_AUDIT_OPTION, null );
	if ( ! is_array( $run ) || empty( $run['host'] ) ) {
		$reason = 'no run stored';
		return null;
	}
	$host = omens_audit_host();
	$env  = wp_get_environment_type();
	if ( $run['host'] !== $host || ( $run['environment'] ?? '' ) !== $env ) {
		$reason = sprintf(
			'stored run is for %s (%s); this site is %s (%s). Ignored: it came along with a copy of the database. Run the audit here.',
			$run['host'],
			$run['environment'] ?? '?',
			$host,
			$env
		);
		return null;
	}
	$reason = '';
	return $run;
}

/**
 * Delete the stored run.
 *
 * @return bool
 */
function omens_audit_delete_run() {
	return delete_option( OMENS_AUDIT_OPTION );
}
