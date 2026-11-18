<?php
/**
 * currency.house-plugins: the Omens plugins expected for this build type are
 * installed, active and current, the update token is present where a private
 * repo needs it, and no house plugin is a copy of its source folder.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_House_Plugins extends Omens_Audit_Check_Base {

	/**
	 * Paths a house plugin's release zip never holds. Every house repo has
	 * export-ignored these (or never had them) since its first release, so one
	 * in the installed folder means the folder was copied, cloned or symlinked
	 * from the repository instead of installed from the zip. branding/ is left
	 * out: it was only export-ignored from November 2, 2026.
	 */
	const SOURCE_MARKERS = array( '.git', '.gitattributes', '.claude', 'tools', 'tests', 'phpcs.xml.dist' );

	public function id() {
		return 'currency.house-plugins';
	}

	public function family() {
		return 'currency';
	}

	public function title() {
		return 'House plugins installed, active and current';
	}

	public function severity() {
		return 'moderate';
	}

	/**
	 * Expected house plugins for a build type.
	 *
	 * @param string $build_type custom|purchased.
	 * @return array<string, array{file: string, private: bool}> slug => facts.
	 */
	public static function expected( $build_type ) {
		$all = self::known();
		$set = 'custom' === $build_type
			? array( 'omens-wp-plugin-audit', 'omens-wp-plugin-seo', 'omens-wp-plugin-media', 'omens-wp-plugin-docs' )
			: array( 'omens-wp-plugin-audit', 'omens-wp-plugin-security' );
		/**
		 * The house plugins this site should run (slugs). Defaults by build type.
		 *
		 * @param string[] $set        Slugs.
		 * @param string   $build_type custom|purchased.
		 */
		$set = (array) apply_filters( 'omens_audit/house_plugins', $set, $build_type );
		return array_intersect_key( $all, array_fill_keys( $set, true ) );
	}

	/**
	 * Every house plugin, whatever the build type.
	 *
	 * @return array<string, array{file: string, private: bool}> slug => facts.
	 */
	public static function known() {
		return array(
			'omens-wp-plugin-audit'    => array(
				'file'    => 'omens-wp-plugin-audit/omens-wp-plugin-audit.php',
				'private' => false,
			),
			'omens-wp-plugin-seo'      => array(
				'file'    => 'omens-wp-plugin-seo/omens-wp-plugin-seo.php',
				'private' => true,
			),
			'omens-wp-plugin-media'    => array(
				'file'    => 'omens-wp-plugin-media/omens-wp-plugin-media.php',
				'private' => false,
			),
			'omens-wp-plugin-docs'     => array(
				'file'    => 'omens-wp-plugin-docs/omens-wp-plugin-docs.php',
				'private' => true,
			),
			'omens-wp-plugin-security' => array(
				'file'    => 'omens-wp-plugin-security/omens-wp-plugin-security.php',
				'private' => false,
			),
		);
	}

	/**
	 * Source-only paths present in an installed plugin folder.
	 *
	 * @param string $dir Plugin folder, no trailing slash.
	 * @return string[] Markers found; 'symlink' first when the folder is a link.
	 */
	public static function source_markers( $dir ) {
		$found = array();
		if ( is_link( $dir ) ) {
			$found[] = 'symlink';
		}
		foreach ( self::SOURCE_MARKERS as $marker ) {
			if ( file_exists( $dir . '/' . $marker ) ) {
				$found[] = $marker;
			}
		}
		return $found;
	}

	public function run( Omens_Audit_Context $ctx ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed = get_plugins();
		$active    = array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		$transient = get_site_transient( 'update_plugins' );
		$response  = is_object( $transient ) && isset( $transient->response ) ? (array) $transient->response : array();
		$findings  = array();
		$private   = false;

		$expected = self::expected( $ctx->build_type );
		$seo      = function_exists( 'omens_audit_seo_plugin' ) ? omens_audit_seo_plugin() : array( 'slug' => 'none' );
		if ( in_array( $seo['slug'] ?? 'none', array( 'yoast', 'rank-math', 'aioseo' ), true ) ) {
			// The build runs a third-party SEO plugin by choice (Yoast is the house pick); do not demand ours beside it.
			unset( $expected['omens-wp-plugin-seo'] );
		}
		foreach ( $expected as $slug => $facts ) {
			$file = $facts['file'];
			if ( $facts['private'] ) {
				$private = true;
			}
			if ( ! isset( $installed[ $file ] ) ) {
				$findings[] = $this->house_finding( $slug, 'missing', sprintf( '%s is not installed', $slug ), sprintf( 'The %s build expects %s and it is not in wp-content/plugins.', $ctx->build_type, $slug ), array( sprintf( 'wp plugin install https://github.com/omens-agency/%s/releases/latest/download/%s.zip --activate', $slug, $slug ) ) );
				continue;
			}
			if ( ! in_array( $file, $active, true ) ) {
				$findings[] = $this->house_finding( $slug, 'inactive', sprintf( '%s is installed but not active', $slug ), sprintf( '%s %s is present and deactivated.', $slug, $installed[ $file ]['Version'] ?? '' ), array( sprintf( 'wp plugin activate %s', $slug ) ) );
				continue;
			}
			if ( isset( $response[ $file ] ) ) {
				$new        = (string) ( $response[ $file ]->new_version ?? '' );
				$findings[] = $this->house_finding( $slug, 'outdated', sprintf( '%s %s is behind release %s', $slug, $installed[ $file ]['Version'] ?? '', $new ), 'The update checker knows a newer release and the site has not applied it yet.', array( sprintf( 'wp plugin update %s (full releases normally apply themselves within a day; a stall here means auto-update is off or the release is still a pre-release).', $slug ) ) );
			}
		}

		// A local site runs house plugins from a checkout on purpose (tools/smoke.sh symlinks one in).
		if ( 'local' !== $ctx->effective_environment ) {
			foreach ( self::known() as $slug => $facts ) {
				if ( isset( $installed[ $facts['file'] ] ) ) {
					$finding = $this->source_finding( $slug, $facts, $installed[ $facts['file'] ] );
					if ( $finding ) {
						$findings[] = $finding;
					}
				}
			}
		}

		if ( $private && ! ( defined( 'OMENS_PLUGIN_UPDATE_TOKEN' ) && OMENS_PLUGIN_UPDATE_TOKEN ) ) {
			$findings[] = $this->house_finding( 'update-token', 'token', 'OMENS_PLUGIN_UPDATE_TOKEN is not defined', 'At least one expected house plugin lives in a private repo, so without the token this site cannot see or apply its updates.', array( "Add define( 'OMENS_PLUGIN_UPDATE_TOKEN', '...' ); to wp-config.php with the shared read-only token (1Password), per environment-sync.md section 8." ) );
		}
		return $findings;
	}

	/**
	 * The installed-from-source finding for one house plugin, or null.
	 *
	 * @param string $slug   Plugin slug.
	 * @param array  $facts  From known().
	 * @param array  $plugin get_plugins() row.
	 * @return array|null
	 */
	private function source_finding( $slug, array $facts, array $plugin ) {
		$dir     = WP_PLUGIN_DIR . '/' . dirname( $facts['file'] );
		$markers = self::source_markers( $dir );
		if ( ! $markers ) {
			return null;
		}
		$shown = array_map(
			function ( $m ) {
				return in_array( $m, array( 'tools', 'tests', '.claude' ), true ) ? $m . '/' : $m;
			},
			array_diff( $markers, array( 'symlink' ) )
		);
		$zip   = $slug . '.zip';
		$steps = array();
		if ( in_array( 'symlink', $markers, true ) ) {
			$steps[] = sprintf( 'wp-content/plugins/%s is a symlink to a checkout: remove the link itself first (rm wp-content/plugins/%s, no -r), or the reinstall deletes through it into the checkout.', $slug, $slug );
		}
		$steps[] = $facts['private']
			? sprintf( 'Download the release zip (gh release download --repo omens-agency/%s --pattern %s), upload it, and run wp plugin install %s --force. The plugin stays active.', $slug, $zip, $zip )
			: sprintf( 'wp plugin install https://github.com/omens-agency/%s/releases/latest/download/%s --force (the plugin stays active).', $slug, $zip );
		$steps[] = sprintf( 'Re-run wp omens-audit run and confirm %s:source is gone.', $slug );
		return $this->house_finding(
			$slug,
			'source',
			sprintf( '%s is installed from source, not the release zip', $slug ),
			sprintf(
				'wp-content/plugins/%s %s, which the release zip never contains, so the folder was copied, cloned or symlinked from the repository (version %s). Developer files on a server are what malware scanners read as suspicious: Jetpack Protect flagged a source copy of omens-wp-plugin-audit on November 18, 2026.',
				$slug,
				in_array( 'symlink', $markers, true ) ? 'is a symlink' . ( $shown ? ' and holds ' . implode( ', ', $shown ) : '' ) : 'holds ' . implode( ', ', $shown ),
				(string) ( $plugin['Version'] ?? '' )
			),
			$steps,
			array_map(
				function ( $m ) {
					return 'found: ' . $m;
				},
				$markers
			)
		);
	}

	/**
	 * Build one house-plugin finding.
	 *
	 * @param string   $slug    Plugin slug or 'update-token'.
	 * @param string   $state    missing|inactive|outdated|source|token.
	 * @param string   $title    Title.
	 * @param string   $summary  Summary.
	 * @param string[] $steps    Steps.
	 * @param string[] $evidence Extra evidence lines.
	 * @return array
	 */
	private function house_finding( $slug, $state, $title, $summary, array $steps, array $evidence = array() ) {
		return $this->finding(
			array(
				'subject'  => $slug . ':' . $state,
				'title'    => $title,
				'summary'  => $summary,
				'fix'      => array(
					'route'      => 'config',
					'where'      => 'token' === $state ? 'wp-config.php' : 'Plugins',
					'steps'      => $steps,
					'confidence' => 'high',
				),
				'evidence' => array_merge( array( 'state: ' . $state ), $evidence ),
			)
		);
	}
}
