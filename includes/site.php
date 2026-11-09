<?php
/**
 * Facts about this site: build type and the rendered-HTML sample.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Work out whether this is a custom build (a theme scaffolded from
 * omens-wp-starter-theme, which has a repo Claude Code can open a PR against)
 * or a purchased or legacy theme (no repo; `code` routes become `human`).
 *
 * The marker is the starter's OMENS_STARTER_VERSION constant, which
 * functions.php defines in every scaffolded theme. The plan's original marker,
 * a library/php/omens.php file, is not used: legacy Omens themes from the
 * Three Omens era (omens-reskin on omens.agency, for one) carry a file of that
 * name with none of the starter core, so it would misclassify them.
 *
 * @return array { type: custom|purchased, evidence: string, origin: starter|omens-legacy|third-party }
 */
function omens_audit_build_type() {
	$theme  = wp_get_theme();
	$parent = $theme->parent() ? $theme->parent() : $theme;

	if ( defined( 'OMENS_STARTER_VERSION' ) ) {
		$type = array(
			'type'     => 'custom',
			'origin'   => 'starter',
			'evidence' => sprintf( 'OMENS_STARTER_VERSION %s defined by theme %s', OMENS_STARTER_VERSION, $parent->get_stylesheet() ),
		);
	} elseif ( preg_match( '/omens/i', (string) $parent->get( 'Author' ) . ' ' . (string) $parent->get( 'AuthorURI' ) ) ) {
		$type = array(
			'type'     => 'purchased',
			'origin'   => 'omens-legacy',
			'evidence' => sprintf( 'theme %s is by Omens but predates the starter (no OMENS_STARTER_VERSION); treated as no-repo', $parent->get_stylesheet() ),
		);
	} else {
		$type = array(
			'type'     => 'purchased',
			'origin'   => 'third-party',
			'evidence' => sprintf( 'theme %s by %s', $parent->get_stylesheet(), (string) $parent->get( 'Author' ) ),
		);
	}

	/**
	 * Override the detected build type, for a legacy custom theme that does
	 * have a repo, say. Must return the same shape.
	 *
	 * @param array $type { type, origin, evidence }.
	 */
	$type = apply_filters( 'omens_audit/build_type', $type );
	if ( ! in_array( $type['type'] ?? '', array( 'custom', 'purchased' ), true ) ) {
		$type['type'] = 'purchased';
	}
	return $type;
}

/**
 * Theme facts for the run metadata.
 *
 * @return array
 */
function omens_audit_theme_info() {
	$theme  = wp_get_theme();
	$parent = $theme->parent();
	return array(
		'stylesheet' => $theme->get_stylesheet(),
		'name'       => (string) $theme->get( 'Name' ),
		'version'    => (string) $theme->get( 'Version' ),
		'author'     => (string) $theme->get( 'Author' ),
		'parent'     => $parent ? $parent->get_stylesheet() : '',
		'starter'    => defined( 'OMENS_STARTER_VERSION' ) ? OMENS_STARTER_VERSION : '',
	);
}

/**
 * The legal pages, by role: privacy (core's setting), terms and the
 * accessibility statement (the slugs web-legal-pages publishes, filterable
 * for sites built before it existed).
 *
 * @return array<string, int> role => post ID (0 when missing).
 */
function omens_audit_legal_pages() {
	$pages = array(
		'privacy'       => (int) get_option( 'wp_page_for_privacy_policy' ),
		'terms'         => 0,
		'accessibility' => 0,
	);
	/**
	 * Slugs to look up the terms and accessibility statement pages by.
	 *
	 * @param array $slugs role => string[] candidate slugs.
	 */
	$slugs = apply_filters(
		'omens_audit/legal_slugs',
		array(
			'terms'         => array( 'terms-of-use', 'terms', 'terms-and-conditions', 'terms-of-service' ),
			'accessibility' => array( 'accessibility-statement', 'accessibility' ),
		)
	);
	foreach ( $slugs as $role => $candidates ) {
		foreach ( (array) $candidates as $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $page && 'publish' === $page->post_status ) {
				$pages[ $role ] = (int) $page->ID;
				break;
			}
		}
	}
	/**
	 * Override the legal page IDs outright.
	 *
	 * @param array $pages role => post ID.
	 */
	return apply_filters( 'omens_audit/legal_pages', $pages );
}

/**
 * The rendered-HTML sample: home, the legal pages, one published entry per
 * public post type, the five most recently modified pages, plus anything added
 * through `omens_audit/sample_urls`. Capped at 25 by default. The exact list
 * is part of the run output, because the central run uses it to decide
 * whether a finding was re-checked.
 *
 * @return string[]
 */
/**
 * Post types that hold page-builder templates rather than pages: never sampled.
 *
 * @return string[]
 */
function omens_audit_template_post_types() {
	$types = array( 'elementor_library', 'e-landing-page', 'cpt_layouts', 'ct_template', 'oxy_user_library', 'elementskit_template', 'elementskit_content', 'jet-theme-core', 'jet-engine', 'fl-builder-template', 'fl-theme-layout', 'brizy_template', 'tbuilder_layout', 'tbuilder_layout_part', 'us_page_block', 'kadence_element', 'blocksy_content_block', 'generateblocks_element', 'gp_elements', 'astra-advanced-hook', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation',
		// Fragments with a public permalink but no page behind it, met on the November 2026 fleet: Royal Addons templates and mega-menu items, a mega-menu plugin, JupiterX code and font records, a slider text type, Elementor global links, a Facebook-tab feed, WP Timeline entries (their permalinks redirect), Events Calendar venues and organizers, GravityView views.
		'wpr_templates', 'wpr_mega_menu', 'ic_mega_menu', 'jupiterx-codes', 'jupiterx-fonts', 'slider_text', 'global-links', 'facebook', 'wp-timeline', 'tribe_venue', 'tribe_organizer', 'gravityview' );
	/**
	 * Post types excluded from the sample and from per-entry checks because they are builder templates or fragments, not pages.
	 *
	 * @param string[] $types Slugs.
	 */
	return (array) apply_filters( 'omens_audit/template_post_types', $types );
}

function omens_audit_sample_urls() {
	$urls = array( home_url( '/' ) );

	foreach ( omens_audit_legal_pages() as $id ) {
		if ( $id > 0 && 'publish' === get_post_status( $id ) ) {
			$urls[] = get_permalink( $id );
		}
	}

	$types         = get_post_types(
		array(
			'public'             => true,
			'publicly_queryable' => true,
		),
		'names'
	);
	$types['page'] = 'page';
	unset( $types['attachment'] );
	// Page-builder template types are publicly queryable but their permalinks
	// (?elementor_library=slug and the like) redirect to the front page, and the
	// redirected request carries no cache-buster, so the sample would read a
	// cached copy of / under another name. Not pages anyone visits.
	foreach ( omens_audit_template_post_types() as $skip ) {
		unset( $types[ $skip ] );
	}
	foreach ( $types as $type ) {
		$posts = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => false,
				'no_found_rows'    => true,
			)
		);
		foreach ( $posts as $post ) {
			$urls[] = get_permalink( $post );
		}
	}

	$recent = get_posts(
		array(
			'post_type'        => 'page',
			'post_status'      => 'publish',
			'posts_per_page'   => 5,
			'orderby'          => 'modified',
			'order'            => 'DESC',
			'suppress_filters' => false,
			'no_found_rows'    => true,
		)
	);
	foreach ( $recent as $post ) {
		$urls[] = get_permalink( $post );
	}

	/**
	 * Add or replace sample URLs. Only URLs on home_url() are fetched.
	 *
	 * @param string[] $urls Sample so far.
	 */
	$urls = (array) apply_filters( 'omens_audit/sample_urls', $urls );

	/**
	 * Cap on the sample size.
	 *
	 * @param int $cap Default 25.
	 */
	$cap  = max( 1, (int) apply_filters( 'omens_audit/sample_cap', 25 ) );
	$urls = array_values( array_unique( array_filter( array_map( 'strval', $urls ) ) ) );
	return array_slice( $urls, 0, $cap );
}
