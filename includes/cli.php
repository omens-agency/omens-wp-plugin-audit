<?php
/**
 * WP-CLI: wp omens-audit run | export | checks.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Omens Site Audit.
 */
class Omens_Audit_CLI {

	/**
	 * Run the audit and store it as the latest run.
	 *
	 * Prints one line per check as it goes, so a run started over a transport
	 * that kills silent commands keeps talking.
	 *
	 * ## OPTIONS
	 *
	 * [--family=<family>]
	 * : Only checks in this family (security, currency, seo, a11y, legal).
	 *
	 * [--check=<id>]
	 * : Only this check ID (repeatable as a comma-separated list). For verifying one fix.
	 *
	 * [--dry-run]
	 * : Run but do not store the result.
	 *
	 * [--store]
	 * : Store a partial run (--check or --family) as the latest run. Without it a
	 * partial run is never stored, because the central run reads the latest run
	 * to decide what was re-checked and a one-check record would mark every
	 * other task "not checked".
	 *
	 * [--format=<format>]
	 * : Summary format: table (default) or json (prints the whole run).
	 *
	 * ## EXAMPLES
	 *
	 *     wp omens-audit run
	 *     wp omens-audit run --check=security.file-edit-enabled --dry-run
	 *
	 * @param array $args       Positional.
	 * @param array $assoc_args Flags.
	 */
	public function run( $args, $assoc_args ) {
		$opts   = array(
			'family'  => (string) ( $assoc_args['family'] ?? '' ),
			'checks'  => isset( $assoc_args['check'] ) ? array_filter( array_map( 'trim', explode( ',', (string) $assoc_args['check'] ) ) ) : array(),
			'dry_run' => ! empty( $assoc_args['dry-run'] ),
		);
		$format = (string) ( $assoc_args['format'] ?? 'table' );
		$partial = '' !== $opts['family'] || ! empty( $opts['checks'] );
		if ( $partial && ! $opts['dry_run'] && empty( $assoc_args['store'] ) ) {
			$opts['dry_run'] = true;
			if ( 'json' !== $format ) {
				WP_CLI::log( 'Partial selection: not stored as the latest run (pass --store to overwrite it).' );
			}
		}

		$selected = omens_audit_select_checks( $opts );
		if ( ! $selected ) {
			WP_CLI::error( 'No checks match that selection. See: wp omens-audit checks' );
		}

		$progress = static function ( $event, $id, $data ) use ( $format ) {
			if ( 'json' === $format ) {
				return;
			}
			if ( 'done' === $event ) {
				WP_CLI::log( sprintf( '%-40s %-8s %3d finding(s) %5d ms', $id, $data['status'], $data['findings'], $data['ms'] ) );
			} elseif ( 'failed' === $event ) {
				WP_CLI::warning( sprintf( '%s threw: %s', $id, $data['error'] ) );
			}
		};

		$run = omens_audit_run( $opts, $progress );

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $run ) );
			return;
		}
		$c = $run['counts']['severity'];
		WP_CLI::success(
			sprintf(
				'%s%s: %d checks, %d findings (critical %d, serious %d, moderate %d, minor %d, info %d) in %.1f s. %s build, %s environment. Sample: %d URLs, %d fetched.',
				$run['host'],
				$run['dry_run'] ? ' (dry run, not stored)' : '',
				count( $run['checks'] ),
				$run['counts']['total'],
				$c['critical'],
				$c['serious'],
				$c['moderate'],
				$c['minor'],
				$c['info'],
				$run['duration_ms'] / 1000,
				$run['build_type'],
				$run['environment'],
				count( $run['sample'] ),
				count( $run['fetched'] )
			)
		);
	}

	/**
	 * Print the latest run as JSON for the central audit.
	 *
	 * Refuses (exit 1) when the stored run belongs to another host or
	 * environment, which is what happens after a database copy.
	 *
	 * ## OPTIONS
	 *
	 * [--part=<part>]
	 * : run (default, everything), findings, sample, inventory, meta.
	 *
	 * [--pretty]
	 * : Pretty-print.
	 *
	 * @param array $args       Positional.
	 * @param array $assoc_args Flags.
	 */
	public function export( $args, $assoc_args ) {
		$reason = '';
		$run    = omens_audit_get_run( $reason );
		if ( ! $run ) {
			WP_CLI::error( $reason );
		}
		$part = (string) ( $assoc_args['part'] ?? 'run' );
		switch ( $part ) {
			case 'findings':
				$out = $run['findings'];
				break;
			case 'sample':
				$out = array(
					'sample'  => $run['sample'],
					'fetched' => $run['fetched'],
				);
				break;
			case 'inventory':
				$out = $run['inventory'];
				break;
			case 'meta':
				$out = $run;
				unset( $out['findings'], $out['inventory'], $out['sample'], $out['fetched'] );
				break;
			case 'run':
				$out = $run;
				break;
			default:
				WP_CLI::error( 'Unknown --part. Use run, findings, sample, inventory or meta.' );
		}
		$flags = ! empty( $assoc_args['pretty'] ) ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES : JSON_UNESCAPED_SLASHES;
		WP_CLI::line( wp_json_encode( $out, $flags ) );
	}

	/**
	 * List the registered checks and whether each applies on this site.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table (default), json, csv.
	 *
	 * @param array $args       Positional.
	 * @param array $assoc_args Flags.
	 */
	public function checks( $args, $assoc_args ) {
		$ctx  = new Omens_Audit_Context( array( 'dry_run' => true ) );
		$rows = array();
		foreach ( omens_audit_checks() as $id => $check ) {
			$applies = $check->applies( $ctx );
			$rows[]  = array(
				'id'       => $id,
				'family'   => $check->family(),
				'version'  => $check->version(),
				'severity' => $check instanceof Omens_Audit_Check_Base ? $check->severity() : '',
				'applies'  => true === $applies ? 'yes' : 'no: ' . $applies,
				'title'    => $check->title(),
			);
		}
		WP_CLI\Utils\format_items( (string) ( $assoc_args['format'] ?? 'table' ), $rows, array( 'id', 'family', 'severity', 'version', 'applies', 'title' ) );
	}
}

WP_CLI::add_command( 'omens-audit', 'Omens_Audit_CLI' );
