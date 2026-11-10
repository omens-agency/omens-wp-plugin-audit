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
 * - `roles`: every role that has users, with a level read from its capabilities, so a custom
 *   role counts for what it can do rather than what it is called. A role WordPress cannot
 *   resolve is reported `unresolved` with no level; the central run treats that as able to
 *   reach anything, so an unknown role never hides a finding.
 * - `theme_plugin_slugs`: plugin slugs the theme's PHP registers, only when the theme ships
 *   TGMPA, with the file each was found in (`theme_plugin_sources`). The central run uses
 *   this as a hint beside its own list, and reports every plugin it sets aside because of it.
 *
 * The whole thing fails soft: an error is reported in `error` and the run carries on.
 *
 * @return array
 */
function omens_audit_inventory_site_facts() {
	$facts = array(
		'collected'            => gmdate( 'c' ),
		'users_can_register'   => (bool) get_option( 'users_can_register' ),
		'default_role'         => (string) get_option( 'default_role' ),
		'default_role_level'   => null,
		'roles'                => array(),
		'themes'               => array(),
		'theme_plugin_slugs'   => array(),
		'theme_plugin_sources' => array(),
	);

	try {
		$default                     = omens_audit_role_level( $facts['default_role'] );
		$facts['default_role_level'] = $default['level'];

		$counts = count_users();
		foreach ( (array) ( $counts['avail_roles'] ?? array() ) as $slug => $n ) {
			if ( $n ) {
				$facts['roles'][ (string) $slug ] = array( 'users' => (int) $n ) + omens_audit_role_level( (string) $slug );
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

		$scan                          = omens_audit_theme_registered_plugins( $dirs );
		$facts['theme_plugin_slugs']   = array_keys( $scan['slugs'] );
		$facts['theme_plugin_sources'] = $scan['slugs'];
		if ( $scan['note'] ) {
			$facts['theme_scan_note'] = $scan['note'];
		}
	} catch ( \Throwable $e ) {
		$facts['error'] = $e->getMessage();
	}

	return $facts;
}

/**
 * What a role can do, as a level: 5 administers the site or its code or users, 4 edits other
 * people's content, 3 publishes, 2 writes, 1 only reads. The "none" entry WordPress reports for
 * users with no role is level 1. Any other role that cannot be resolved has no level and is
 * marked unresolved, so the central run assumes the worst rather than the least.
 *
 * @param string $slug Role slug.
 * @return array{level:int|null,unresolved?:bool}
 */
function omens_audit_role_level( $slug ) {
	if ( 'none' === $slug ) {
		return array( 'level' => 1 );
	}
	$role = get_role( $slug );
	if ( ! $role ) {
		return array( 'level' => null, 'unresolved' => true );
	}
	foreach ( array( 'manage_options', 'edit_plugins', 'edit_themes', 'install_plugins', 'activate_plugins', 'edit_users', 'promote_users', 'create_users' ) as $cap ) {
		if ( $role->has_cap( $cap ) ) {
			return array( 'level' => 5 );
		}
	}
	if ( $role->has_cap( 'edit_others_posts' ) || $role->has_cap( 'unfiltered_html' ) ) {
		return array( 'level' => 4 );
	}
	if ( $role->has_cap( 'publish_posts' ) ) {
		return array( 'level' => 3 );
	}
	return array( 'level' => ( $role->has_cap( 'edit_posts' ) || $role->has_cap( 'upload_files' ) ) ? 2 : 1 );
}

/**
 * Plugin slugs a theme registers through TGMPA, with the file each was found in.
 *
 * Bounded on every axis: 5,000 directory entries, 20 MB read, 400 KB a file, 200 slugs, regular
 * files only, nothing that resolves outside the theme folder, and dependency folders skipped.
 * A theme that does not ship TGMPA yields nothing: a stray `'slug' =>` in a theme with no
 * plugin installer is not a registration.
 *
 * @param string[] $dirs Theme directories.
 * @return array{slugs:array<string,string>,note:string}
 */
function omens_audit_theme_registered_plugins( array $dirs ) {
	$found   = array();
	$entries = 0;
	$bytes   = 0;
	$tgmpa   = false;
	$note    = '';
	foreach ( $dirs as $dir ) {
		$root = is_dir( $dir ) ? realpath( $dir ) : false;
		if ( ! $root ) {
			continue;
		}
		try {
			$files = new RecursiveIteratorIterator(
				new RecursiveCallbackFilterIterator(
					new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
					static function ( $current ) {
						return ! in_array( $current->getFilename(), array( 'node_modules', 'vendor', '.git', '.claude' ), true );
					}
				),
				RecursiveIteratorIterator::LEAVES_ONLY,
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);
			foreach ( $files as $file ) {
				if ( ++$entries > 5000 || $bytes > 20 * 1024 * 1024 ) {
					$note = 'theme scan stopped at its limit';
					break 2;
				}
				if ( ! $file->isFile() || $file->isLink() || '.php' !== substr( $file->getFilename(), -4 ) || $file->getSize() > 400000 ) {
					continue;
				}
				$real = realpath( $file->getPathname() );
				if ( ! $real || 0 !== strpos( $real, $root . DIRECTORY_SEPARATOR ) ) {
					continue;
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
				$source = (string) file_get_contents( $real );
				$bytes += strlen( $source );
				if ( false !== stripos( $source, 'tgmpa' ) || false !== strpos( $source, 'TGM_Plugin_Activation' ) ) {
					$tgmpa = true;
				}
				if ( false === strpos( $source, 'slug' ) ) {
					continue;
				}
				if ( preg_match_all( '/[\'"]slug[\'"]\s*=>\s*[\'"]([A-Za-z0-9_\-]{2,60})[\'"]/', $source, $m ) ) {
					foreach ( $m[1] as $slug ) {
						if ( ! isset( $found[ $slug ] ) && count( $found ) < 200 ) {
							$found[ $slug ] = ltrim( substr( $real, strlen( $root ) ), DIRECTORY_SEPARATOR );
						}
					}
				}
			}
		} catch ( \Throwable $e ) {
			$note = 'theme scan failed: ' . $e->getMessage();
		}
	}
	return array(
		'slugs' => $tgmpa ? $found : array(),
		'note'  => $note,
	);
}
