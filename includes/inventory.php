<?php
/**
 * Inventories for the baselines the central run keeps off the site: who the
 * administrators are, and what mu-plugins exist. The plugin compares nothing;
 * the comparison happens where an attacker with admin access cannot edit it.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Users holding administrator, or any role that grants manage_options.
 *
 * @return array[]
 */
function omens_audit_inventory_admins() {
	$roles = array();
	foreach ( wp_roles()->roles as $slug => $role ) {
		if ( 'administrator' === $slug || ! empty( $role['capabilities']['manage_options'] ) ) {
			$roles[] = $slug;
		}
	}
	$admins = array();
	if ( $roles ) {
		$users = get_users(
			array(
				'role__in' => $roles,
				'orderby'  => 'ID',
				'number'   => 500,
				'fields'   => array( 'ID', 'user_login', 'user_email', 'user_registered' ),
			)
		);
		foreach ( $users as $user ) {
			$admins[] = array(
				'id'           => (int) $user->ID,
				'login'        => (string) $user->user_login,
				'email_domain' => strtolower( (string) substr( strrchr( (string) $user->user_email, '@' ), 1 ) ),
				'registered'   => (string) $user->user_registered,
				'roles'        => array_values( array_intersect( (array) get_userdata( $user->ID )->roles, $roles ) ),
				'last_login'   => omens_audit_last_login( (int) $user->ID ),
			);
		}
	}
	if ( is_multisite() ) {
		foreach ( get_super_admins() as $login ) {
			$user = get_user_by( 'login', $login );
			if ( $user && ! in_array( (int) $user->ID, array_column( $admins, 'id' ), true ) ) {
				$admins[] = array(
					'id'           => (int) $user->ID,
					'login'        => (string) $user->user_login,
					'email_domain' => strtolower( (string) substr( strrchr( (string) $user->user_email, '@' ), 1 ) ),
					'registered'   => (string) $user->user_registered,
					'roles'        => array( 'super_admin' ),
					'last_login'   => omens_audit_last_login( (int) $user->ID ),
				);
			}
		}
	}
	return $admins;
}

/**
 * Last login if some plugin records it (Wordfence, WP Last Login, the common
 * `last_login` meta). Empty string when nothing does.
 *
 * @param int $user_id User ID.
 * @return string
 */
function omens_audit_last_login( $user_id ) {
	/**
	 * Meta keys to read a last-login timestamp from, in order.
	 *
	 * @param string[] $keys Meta keys.
	 */
	$keys = apply_filters( 'omens_audit/last_login_keys', array( 'wfls-last-login', 'last_login', 'wp-last-login', '_last_login' ) );
	foreach ( (array) $keys as $key ) {
		$value = get_user_meta( $user_id, $key, true );
		if ( '' === $value || null === $value ) {
			continue;
		}
		if ( is_numeric( $value ) ) {
			return gmdate( 'c', (int) $value );
		}
		return (string) $value;
	}
	return '';
}

/**
 * Must-use plugins with a SHA-1 of each file.
 *
 * @return array[]
 */
function omens_audit_inventory_mu_plugins() {
	$list = array();
	foreach ( get_mu_plugins() as $file => $data ) {
		$path   = trailingslashit( WPMU_PLUGIN_DIR ) . $file;
		$list[] = array(
			'file'    => (string) $file,
			'name'    => (string) ( $data['Name'] ?? '' ),
			'version' => (string) ( $data['Version'] ?? '' ),
			'sha1'    => is_readable( $path ) ? (string) sha1_file( $path ) : '',
			'size'    => is_readable( $path ) ? (int) filesize( $path ) : 0,
		);
	}
	return $list;
}
