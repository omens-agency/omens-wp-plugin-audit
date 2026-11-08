<?php
/**
 * currency.plugins: plugins with an update pending, from the update transient
 * WordPress already keeps. No outbound request; if the transient is missing the
 * check says so rather than fetching.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Currency_Plugins extends Omens_Audit_Check_Base {

	public function id() {
		return 'currency.plugins';
	}

	public function family() {
		return 'currency';
	}

	public function title() {
		return 'Plugin updates pending';
	}

	public function severity() {
		return 'moderate';
	}

	public function run( Omens_Audit_Context $ctx ) {
		$transient = get_site_transient( 'update_plugins' );
		if ( ! is_object( $transient ) || ! isset( $transient->last_checked ) ) {
			return array( $this->cannot_run( 'the update_plugins transient is empty; WordPress refreshes it twice daily and this check only reads it' ) );
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed  = get_plugins();
		$response   = isset( $transient->response ) ? (array) $transient->response : array();
		$no_update  = isset( $transient->no_update ) ? (array) $transient->no_update : array();
		$findings   = array();
		$no_channel = array();
		$no_package = array();
		$age_days   = (int) floor( ( time() - (int) $transient->last_checked ) / DAY_IN_SECONDS );

		foreach ( $installed as $file => $data ) {
			$slug = dirname( $file );
			if ( '.' === $slug ) {
				$slug = basename( $file, '.php' );
			}
			if ( isset( $response[ $file ] ) ) {
				$new = (string) ( $response[ $file ]->new_version ?? '' );
				$cur = (string) ( $data['Version'] ?? '' );
				// A pending update with no download package cannot be applied from this site:
				// the plugin came bundled with a theme or its licence is not active here, so its
				// updater announces the version and WordPress answers "Update package not
				// available". One roll-up line per site; the vulnerability leg is the watch.
				if ( '' === trim( (string) ( $response[ $file ]->package ?? '' ) ) ) {
					$no_package[] = sprintf( '%s %s (%s available)', $data['Name'] ?? $slug, $cur, $new );
					continue;
				}
				$findings[] = $this->finding(
					array(
						'subject'  => $slug,
						'title'    => sprintf( '%s %s has update %s pending', $data['Name'] ?? $slug, $cur, $new ),
						'summary'  => sprintf( '%s is on %s; %s is available.', $data['Name'] ?? $slug, $cur, $new ),
						'fix'      => array(
							'route'      => 'config',
							'where'      => 'Plugins > Installed Plugins',
							'steps'      => array(
								sprintf( 'Update %s to %s on staging first, check the site, then production (or let the Pressable plugin schedule do it).', $slug, $new ),
							),
							'confidence' => 'high',
						),
						'evidence' => array( sprintf( 'update_plugins transient, checked %d day(s) ago', $age_days ) ),
					)
				);
			} elseif ( ! isset( $no_update[ $file ] ) ) {
				$no_channel[] = sprintf( '%s %s', $data['Name'] ?? $slug, $data['Version'] ?? '' );
			}
		}

		if ( $no_package ) {
			sort( $no_package );
			$findings[] = $this->finding(
				array(
					'subject'  => 'no-update-package',
					'severity' => 'info',
					'title'    => sprintf( '%d plugin(s) have an update with no download package (bundled or unlicensed)', count( $no_package ) ),
					'summary'  => 'Their updater knows a newer version but no package is offered to this site: the plugin shipped inside a purchased theme or its licence is not active here. Maintenance cannot update them; known vulnerabilities in them still arrive through the Pressable alerts.',
					'detail'   => implode( '; ', $no_package ),
					'fix'      => array(
						'route'      => 'human',
						'where'      => 'plugin-licences.md and the site\'s BugHerd board',
						'steps'      => array_merge(
							array( 'Decide once per plugin and record it: buy a direct licence for the site, remove the plugin if unused, or accept it with the vulnerability watch as the backstop (close the task audit-accepted with the reason). A theme that no longer ships updates with a builder or slider in this list is a rebuild candidate, not a maintenance item.' ),
							array_slice( $no_package, 0, 25 )
						),
						'confidence' => 'high',
					),
					'evidence' => array( sprintf( 'update_plugins response with an empty package, checked %d day(s) ago', $age_days ) ),
				)
			);
		}

		if ( $no_channel ) {
			sort( $no_channel );
			$findings[] = $this->finding(
				array(
					'subject'  => 'no-update-channel',
					'severity' => 'minor',
					'title'    => sprintf( '%d plugin(s) report no update channel; check by hand', count( $no_channel ) ),
					'summary'  => 'Premium or bundled plugins that WordPress cannot check for updates. They are not current until someone checks the vendor.',
					'detail'   => implode( '; ', $no_channel ),
					'fix'      => array(
						'route'      => 'human',
						'where'      => 'each vendor\'s account or the theme bundle',
						'steps'      => array_merge(
							array( 'For each, compare the installed version with the vendor\'s latest and with the Pressable security alerts:' ),
							array_slice( $no_channel, 0, 25 )
						),
						'confidence' => 'high',
					),
					'evidence' => array( 'absent from both response and no_update in update_plugins' ),
				)
			);
		}
		return $findings;
	}
}
