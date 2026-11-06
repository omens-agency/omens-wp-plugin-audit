<?php
// phpcs:ignoreFile
$prod = function ( $t ) { $t->state()['home'] = 'https://example.com'; $t->state()['env'] = 'production'; $t->state()['options']['active_plugins'] = array( 'wordpress-seo/wp-seo.php' ); $t->page( $t->doc( '<main>home</main>' ) ); };
return array(
	'fires' => array(
		'setup'  => function ( $t ) use ( $prod ) { $prod( $t ); /* no sitemap in the http map: 404 */ },
		'expect' => array( 'count' => 1, 'severity' => 'serious', 'route' => 'config', 'subject' => 'unreachable' ),
	),
	'fires_empty' => array(
		'setup'  => function ( $t ) use ( $prod ) { $prod( $t ); $t->page( '<?xml version="1.0"?><urlset xmlns="x"></urlset>', home_url( '/sitemap_index.xml' ) ); },
		'expect' => array( 'count' => 1, 'subject' => 'empty' ),
	),
	'fires_lists_noindexed_page' => array(
		'setup'  => function ( $t ) use ( $prod ) {
			$prod( $t );
			$id = omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'thanks' ) );
			$t->page( $t->doc( '<main>thanks</main>', '<meta name="robots" content="noindex">' ), get_permalink( $id ) );
			$t->page( '<sitemapindex><sitemap><loc>https://example.com/page-sitemap.xml</loc></sitemap></sitemapindex>', home_url( '/sitemap_index.xml' ) );
			$t->page( '<urlset><url><loc>https://example.com/</loc></url><url><loc>https://example.com/thanks/</loc></url></urlset>', home_url( '/page-sitemap.xml' ) );
		},
		'expect' => array( 'count' => 1, 'subject' => 'noindex:/thanks/' ),
	),
	'clean' => array(
		'setup'  => function ( $t ) use ( $prod ) {
			$prod( $t );
			$id = omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'about' ) );
			$t->page( $t->doc( '<main>about</main>' ), get_permalink( $id ) );
			$t->page( '<sitemapindex><sitemap><loc>https://example.com/page-sitemap.xml</loc></sitemap></sitemapindex>', home_url( '/sitemap_index.xml' ) );
			$t->page( '<urlset><url><loc>https://example.com/</loc></url><url><loc>https://example.com/about/</loc></url></urlset>', home_url( '/page-sitemap.xml' ) );
		},
		'expect' => array( 'count' => 0 ),
	),
	'clean_core_sitemap_no_plugin' => array(
		'setup'  => function ( $t ) { $t->state()['home'] = 'https://example.com'; $t->page( $t->doc( '<main>x</main>' ) ); $t->page( '<urlset><url><loc>https://example.com/</loc></url></urlset>', home_url( '/wp-sitemap.xml' ) ); },
		'expect' => array( 'count' => 0 ),
	),
	'skipped_on_staging' => array(
		'setup'  => function ( $t ) { $t->state()['env'] = 'staging'; },
		'expect' => array( 'count' => 1, 'severity' => 'info', 'subject' => 'not-applicable' ),
	),
);
