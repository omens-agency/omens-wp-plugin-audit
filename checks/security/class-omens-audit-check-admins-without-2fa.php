<?php
/**
 * security.admins-without-2fa: administrators with no second factor.
 *
 * Reads the user meta (or table) each supported plugin writes: WP 2FA (the
 * house baseline), Two Factor, Wordfence Login Security. No 2FA plugin at all
 * is one finding on its own.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Admins_Without_2fa extends Omens_Audit_Check_Base {

	public function id() {
		return 'security.admins-without-2fa';
	}

	public function family() {
		return 'security';
	}

	public function title() {
		return 'Administrators without two-factor authentication';
	}

	public function severity() {
		return 'serious';
	}

	/**
	 * Which 2FA plugins are active.
	 *
	 * @return string[] Keys from the known list.
	 */
	public function active_providers() {
		$active = array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		$known  = array(
			'wp-2fa'     => array( 'wp-2fa/wp-2fa.php' ),
			'two-factor' => array( 'two-factor/two-factor.php' ),
			'wordfence'  => array( 'wordfence-login-security/wordfence-login-security.php', 'wordfence/wordfence.php' ),
		);
		$found  = array();
		foreach ( $known as $key => $files ) {
			if ( array_intersect( $files, $active ) ) {
				$found[] = $key;
			}
		}
		/**
		 * Providers to treat as present, for a 2FA setup this check does not know.
		 *
		 * @param string[] $found Provider keys.
		 */
		return (array) apply_filters( 'omens_audit/2fa_providers', $found );
	}

	/**
	 * Whether a user has a second factor with any active provider.
	 *
	 * @param int      $user_id   User.
	 * @param string[] $providers Active providers.
	 * @return bool
	 */
	public function user_has_2fa( $user_id, array $providers ) {
		foreach ( $providers as $p ) {
			switch ( $p ) {
				case 'wp-2fa':
					$methods = get_user_meta( $user_id, 'wp_2fa_enabled_methods', true );
					if ( ! empty( $methods ) ) {
						return true;
					}
					break;
				case 'two-factor':
					$enabled = get_user_meta( $user_id, '_two_factor_enabled_providers', true );
					if ( ! empty( $enabled ) ) {
						return true;
					}
					break;
				case 'wordfence':
					global $wpdb;
					$table = $wpdb->prefix . 'wfls_secrets';
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT user_id FROM {$table}" ) );
						if ( in_array( (int) $user_id, $ids, true ) ) {
							return true;
						}
					}
					break;
			}
		}
		/**
		 * Say a user has a second factor through a setup this check cannot read
		 * (an SSO plugin, say).
		 *
		 * @param bool $has     Default false.
		 * @param int  $user_id User.
		 */
		return (bool) apply_filters( 'omens_audit/user_has_2fa', false, $user_id );
	}

	public function run( Omens_Audit_Context $ctx ) {
		$providers = $this->active_providers();
		$admins    = omens_audit_inventory_admins();

		if ( ! $providers ) {
			return array(
				$this->finding(
					array(
						'subject'  => 'no-2fa-plugin',
						'title'    => 'No two-factor authentication plugin is active',
						'summary'  => sprintf( '%d administrator account(s) sign in with a password alone; no 2FA plugin is installed and active.', count( $admins ) ),
						'fix'      => array(
							'route'      => 'config',
							'where'      => 'Plugins',
							'steps'      => array(
								'Install and activate WP 2FA (plugin baseline tier 2) and enforce it for the Administrator role.',
								'Give each administrator a week to enrol, then confirm with this check.',
							),
							'confidence' => 'high',
						),
						'evidence' => array( 'active_plugins has none of wp-2fa, two-factor, wordfence-login-security' ),
					)
				),
			);
		}

		$findings = array();
		foreach ( $admins as $admin ) {
			if ( $this->user_has_2fa( $admin['id'], $providers ) ) {
				continue;
			}
			$findings[] = $this->finding(
				array(
					'subject'  => 'user:' . $admin['id'],
					'title'    => sprintf( 'Administrator "%s" has no second factor', $admin['login'] ),
					'summary'  => sprintf( 'User %d (%s, registered %s) can sign in with a password alone while %s is active.', $admin['id'], $admin['login'], substr( $admin['registered'], 0, 10 ), implode( ', ', $providers ) ),
					'fix'      => array(
						'route'      => 'human',
						'where'      => 'Users > ' . $admin['login'],
						'steps'      => array(
							'Ask the user to enrol a second factor, or enforce it for the Administrator role so the next login requires it.',
							'If the account is unused, downgrade or remove it.',
						),
						'confidence' => 'high',
					),
					'evidence' => array( 'providers active: ' . implode( ', ', $providers ) . '; email domain ' . $admin['email_domain'] ),
				)
			);
		}
		return $findings;
	}
}
