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

/**
 * Installed plugins with version and status, so the central run can confirm a
 * host vulnerability alert against what is really installed before filing.
 *
 * @return array[]
 */
function omens_audit_inventory_plugins() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$active = array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	$list   = array();
	foreach ( get_plugins() as $file => $data ) {
		$slug   = dirname( $file );
		$list[] = array(
			'slug'    => '.' === $slug ? basename( $file, '.php' ) : $slug,
			'file'    => (string) $file,
			'name'    => (string) ( $data['Name'] ?? '' ),
			'version' => (string) ( $data['Version'] ?? '' ),
			'status'  => in_array( $file, $active, true ) ? 'active' : 'inactive',
		);
	}
	return $list;
}

/**
 * Facts the central run needs to judge a theme-bundled plugin's vulnerability: whether the
 * theme offers an update, who can sign in below administrator, and which plugins the theme
 * itself registers. Read-only, and nothing here is compared on the site.
 *
 * - `themes`: the parent theme and the active stylesheet, with the update WordPress knows of.
 * - `roles`: every role that has users, with a level read from its capabilities
 *   (5 manage_options, 4 edit_others_posts, 3 publish_posts, 2 edit_posts, 1 anything else),
 *   so a custom role counts for what it can do rather than what it is called.
 * - `theme_plugin_slugs`: every `'slug' => '...'` the theme's PHP registers (TGMPA and the
 *   like). A plugin with no update channel of its own that the theme registers is bundled.
 *
 * @return array
 */
function omens_audit_inventory_site_facts() {
	$level = static function ( $role ) {
		if ( ! $role ) {
			return 1;
		}
		if ( $role->has_cap( 'manage_options' ) ) {
			return 5;
		}
		if ( $role->has_cap( 'edit_others_posts' ) ) {
			return 4;
		}
		if ( $role->has_cap( 'publish_posts' ) ) {
			return 3;
		}
		return $role->has_cap( 'edit_posts' ) ? 2 : 1;
	};

	$facts = array(
		'collected'          => gmdate( 'c' ),
		'users_can_register' => (bool) get_option( 'users_can_register' ),
		'default_role'       => (string) get_option( 'default_role' ),
		'roles'              => array(),
		'themes'             => array(),
		'theme_plugin_slugs' => array(),
	);
	$facts['default_role_level'] = $level( get_role( $facts['default_role'] ) );

	$counts = count_users();
	foreach ( (array) ( $counts['avail_roles'] ?? array() ) as $slug => $n ) {
		if ( $n ) {
			$facts['roles'][ (string) $slug ] = array(
				'users' => (int) $n,
				'level' => $level( get_role( $slug ) ),
			);
		}
	}

	$updates = get_site_transient( 'update_themes' );
	$dirs    = array();
	foreach ( array_unique( array( get_template(), get_stylesheet() ) ) as $slug ) {
		$theme  = wp_get_theme( $slug );
		$dirs[] = $theme->get_stylesheet_directory();
		$facts['themes'][] = array(
			'slug'    => (string) $slug,
			'name'    => (string) $theme->get( 'Name' ),
			'version' => (string) $theme->get( 'Version' ),
			'update'  => ( is_object( $updates ) && isset( $updates->response[ $slug ]['new_version'] ) ) ? (string) $updates->response[ $slug ]['new_version'] : '',
			'parent'  => get_template() === $slug,
		);
	}

	$found = array();
	$seen  = 0;
	foreach ( $dirs as $dir ) {
		if ( ! is_dir( $dir ) ) {
			continue;
		}
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			if ( $seen > 4000 ) {
				break;
			}
			if ( '.php' !== substr( $file->getFilename(), -4 ) || $file->getSize() > 400000 ) {
				continue;
			}
			++$seen;
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
			$source = (string) file_get_contents( $file->getPathname() );
			if ( false === strpos( $source, 'slug' ) ) {
				continue;
			}
			if ( preg_match_all( '/[\'"]slug[\'"]\s*=>\s*[\'"]([A-Za-z0-9_\-]{2,60})[\'"]/', $source, $m ) ) {
				foreach ( $m[1] as $slug ) {
					$found[ $slug ] = true;
				}
			}
		}
	}
	$facts['theme_plugin_slugs'] = array_keys( $found );

	return $facts;
}
