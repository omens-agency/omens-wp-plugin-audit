<?php
// phpcs:ignoreFile
return array(
	'fires' => array(
		'setup'  => function ( $t ) {
			$t->state()['options']['active_plugins'] = array( 'wordpress-seo/wp-seo.php' );
			omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'about', 'post_title' => 'About', 'meta' => array( '_yoast_wpseo_metadesc' => 'We are Omens.' ) ) );
			omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'team', 'post_title' => 'Team' ) );
			omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'thanks', 'post_title' => 'Thanks', 'meta' => array( '_yoast_wpseo_meta-robots-noindex' => '1' ) ) );
			omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'draft', 'post_status' => 'draft' ) );
			omens_test_add_post( array( 'post_type' => 'post', 'post_name' => 'hello', 'post_title' => 'Hello', 'meta' => array( '_yoast_wpseo_metadesc' => 'A post.' ) ) );
		},
		'expect' => function ( $f, $run, $t ) {
			$t->assert( 1 === count( $f ), 'expected one finding for pages, got ' . count( $f ) );
			$t->assert( 'page' === $f[0]['subject'] && 'content' === $f[0]['fix']['route'] && 'moderate' === $f[0]['severity'], 'shape' );
			$t->assert( false !== strpos( implode( ' ', $f[0]['fix']['steps'] ), 'Team' ) && false === strpos( implode( ' ', $f[0]['fix']['steps'] ), 'Thanks' ), 'Team listed, noindexed Thanks not' );
		},
	),
	'fires_omens_seo' => array(
		'setup'  => function ( $t ) {
			$t->state()['options']['active_plugins'] = array( 'omens-wp-plugin-seo/omens-wp-plugin-seo.php' );
			omens_test_add_post( array( 'post_type' => 'post', 'post_name' => 'a', 'post_title' => 'A', 'meta' => array( 'seo_description' => 'x' ) ) );
			omens_test_add_post( array( 'post_type' => 'post', 'post_name' => 'b', 'post_title' => 'B', 'post_excerpt' => 'An excerpt does not count' ) );
		},
		'expect' => array( 'count' => 1, 'subject' => 'post' ),
	),
	'clean' => array(
		'setup'  => function ( $t ) {
			$t->state()['options']['active_plugins'] = array( 'seo-by-rank-math/rank-math.php' );
			omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'about', 'meta' => array( 'rank_math_description' => 'd' ) ) );
			omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'thanks', 'meta' => array( 'rank_math_robots' => array( 'noindex' ) ) ) );
		},
		'expect' => array( 'count' => 0 ),
	),
	// Builder templates and fragments have public permalinks but are not pages (1.0.7).
	'clean_template_and_fragment_types' => array(
		'setup'  => function ( $t ) {
			$t->state()['options']['active_plugins'] = array( 'wordpress-seo/wp-seo.php' );
			omens_test_add_post( array( 'post_type' => 'elementor_library', 'post_name' => 'default-kit', 'post_title' => 'Default Kit' ) );
			omens_test_add_post( array( 'post_type' => 'wpr_mega_menu', 'post_name' => 'wpr-mega-menu-item-1', 'post_title' => 'wpr-mega-menu-item-1' ) );
			omens_test_add_post( array( 'post_type' => 'tribe_venue', 'post_name' => 'station-45', 'post_title' => 'Station 45' ) );
			omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'about', 'post_title' => 'About', 'meta' => array( '_yoast_wpseo_metadesc' => 'We are Omens.' ) ) );
		},
		'expect' => array( 'count' => 0 ),
	),
	'cannot_run_without_seo_plugin' => array(
		'setup'  => function ( $t ) { omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'about' ) ); },
		'expect' => array( 'count' => 1, 'severity' => 'info', 'subject' => 'cannot-run' ),
	),
);
