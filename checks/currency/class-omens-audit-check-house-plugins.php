<?php
/**
 * currency.house-plugins: the Omens plugins expected for this build type are
 * installed, active and current, and the update token is present where a
 * private repo needs it.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_House_Plugins extends Omens_Audit_Check_Base {

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
		$all = array(
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

		foreach ( self::expected( $ctx->build_type ) as $slug => $facts ) {
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

		if ( $private && ! ( defined( 'OMENS_PLUGIN_UPDATE_TOKEN' ) && OMENS_PLUGIN_UPDATE_TOKEN ) ) {
			$findings[] = $this->house_finding( 'update-token', 'token', 'OMENS_PLUGIN_UPDATE_TOKEN is not defined', 'At least one expected house plugin lives in a private repo, so without the token this site cannot see or apply its updates.', array( "Add define( 'OMENS_PLUGIN_UPDATE_TOKEN', '...' ); to wp-config.php with the shared read-only token (1Password), per environment-sync.md section 8." ) );
		}
		return $findings;
	}

	/**
	 * Build one house-plugin finding.
	 *
	 * @param string   $slug    Plugin slug or 'update-token'.
	 * @param string   $state   missing|inactive|outdated|token.
	 * @param string   $title   Title.
	 * @param string   $summary Summary.
	 * @param string[] $steps   Steps.
	 * @return array
	 */
	private function house_finding( $slug, $state, $title, $summary, array $steps ) {
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
				'evidence' => array( 'state: ' . $state ),
			)
		);
	}
}
