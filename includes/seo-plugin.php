<?php
/**
 * Which SEO plugin runs here, and where it keeps descriptions and noindex.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detect the active SEO plugin.
 *
 * @return array{ slug: string, name: string, description_key: string, noindex: callable|null, sitemap: string }
 */
function omens_audit_seo_plugin() {
	$active = array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	$has    = static function ( $file ) use ( $active ) {
		return in_array( $file, $active, true );
	};
	if ( $has( 'omens-wp-plugin-seo/omens-wp-plugin-seo.php' ) || function_exists( 'omens_seo_get_description' ) ) {
		$plugin = array(
			'slug'            => 'omens-seo',
			'name'            => 'Omens SEO',
			'description_key' => 'seo_description',
			'noindex'         => static function ( $post_id ) {
				return '1' === (string) get_post_meta( $post_id, 'seo_noindex', true );
			},
			'sitemap'         => '/wp-sitemap.xml',
		);
	} elseif ( $has( 'wordpress-seo/wp-seo.php' ) || $has( 'wordpress-seo-premium/wp-seo-premium.php' ) ) {
		$plugin = array(
			'slug'            => 'yoast',
			'name'            => 'Yoast SEO',
			'description_key' => '_yoast_wpseo_metadesc',
			'noindex'         => static function ( $post_id ) {
				return '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
			},
			'sitemap'         => '/sitemap_index.xml',
		);
	} elseif ( $has( 'seo-by-rank-math/rank-math.php' ) || $has( 'seo-by-rank-math-pro/rank-math-pro.php' ) ) {
		$plugin = array(
			'slug'            => 'rank-math',
			'name'            => 'Rank Math',
			'description_key' => 'rank_math_description',
			'noindex'         => static function ( $post_id ) {
				return in_array( 'noindex', (array) get_post_meta( $post_id, 'rank_math_robots', true ), true );
			},
			'sitemap'         => '/sitemap_index.xml',
		);
	} elseif ( $has( 'all-in-one-seo-pack/all_in_one_seo_pack.php' ) || $has( 'all-in-one-seo-pack-pro/all_in_one_seo_pack.php' ) ) {
		$plugin = array(
			'slug'            => 'aioseo',
			'name'            => 'All in One SEO',
			'description_key' => '', // Stored in its own table; not read in version 1.
			'noindex'         => null,
			'sitemap'         => '/sitemap.xml',
		);
	} else {
		$plugin = array(
			'slug'            => 'none',
			'name'            => 'none',
			'description_key' => '',
			'noindex'         => null,
			'sitemap'         => '/wp-sitemap.xml',
		);
	}
	/**
	 * Override the detected SEO plugin facts (same shape).
	 *
	 * @param array $plugin Facts.
	 */
	return apply_filters( 'omens_audit/seo_plugin', $plugin );
}

/**
 * Whether the site's host looks like a staging or local host, whatever
 * WP_ENVIRONMENT_TYPE says. The two are cross-checked by the SEO checks.
 *
 * @param string $host Host.
 * @return bool
 */
/**
 * The environment the checks act on. The host name is the authority: a site
 * answering on a production domain is production whatever WP_ENVIRONMENT_TYPE
 * says (a sandbox that became production by DNS still carries "staging"), and
 * a host on a staging pattern is staging whatever the constant says. Only
 * "local" is taken from the constant or a loopback host, because a local copy
 * answers on any name.
 *
 * @param string $host The site host.
 * @param string $env  wp_get_environment_type().
 * @return string production|staging|local
 */
function omens_audit_effective_environment( $host, $env ) {
	if ( 'local' === $env || preg_match( '/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i', (string) $host ) ) {
		return 'local';
	}
	return omens_audit_host_looks_nonproduction( $host ) ? 'staging' : 'production';
}

function omens_audit_host_looks_nonproduction( $host ) {
	/**
	 * Host patterns that mean "not production".
	 *
	 * @param string[] $patterns Regular expressions.
	 */
	$patterns = (array) apply_filters(
		'omens_audit/nonproduction_hosts',
		array(
			'/\.mystagingwebsite\.com$/i',
			'/\.omens\.site$/i',
			'/\.wpenginepowered\.com$/i',
			'/\.wpengine\.com$/i',
			'/\.kinsta\.cloud$/i',
			'/\.pantheonsite\.io$/i',
			'/^(www\.)?(staging|stg|dev|test|qa|uat)\./i',
			'/\.(local|test|localhost)$/i',
			'/^localhost(:\d+)?$/i',
		)
	);
	foreach ( $patterns as $p ) {
		if ( preg_match( $p, (string) $host ) ) {
			return true;
		}
	}
	return false;
}
