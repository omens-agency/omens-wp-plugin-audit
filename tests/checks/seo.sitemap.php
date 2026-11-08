<?php
// phpcs:ignoreFile
$prod = function ( $t ) { $t->state()['home'] = 'https://example.com'; $t->state()['env'] = 'production'; $t->state()['options']['active_plugins'] = array( 'wordpress-seo/wp-seo.php' ); $t->page( $t->doc( '<main>home</main>' ) ); };
return array(
	'fires' => array(
		'setup'  => function ( $t ) use ( $prod ) { $prod( $t ); /* no sitemap in the http map: 404 */ },
		'expect' => array( 'count' => 1, 'severity' => 'serious', 'route' => 'config', 'subject' => 'unreachable', 'summary_contains' => '/sitemap_index.xml, /sitemap.xml, /wp-sitemap.xml' ),
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
	// 1.0.4: AIOSEO wraps every <loc> in CDATA; bar-md and miresball read as "empty" until this.
	'clean_aioseo_cdata_index' => array(
		'setup'  => function ( $t ) {
			$t->state()['home'] = 'https://example.com'; $t->state()['env'] = 'production'; $t->state()['options']['active_plugins'] = array( 'all-in-one-seo-pack/all_in_one_seo_pack.php' );
			$t->page( $t->doc( '<main>home</main>' ) );
			$t->page( "<?xml version=\"1.0\"?><sitemapindex xmlns=\"x\"><sitemap><loc><![CDATA[https://example.com/post-sitemap.xml]]></loc></sitemap></sitemapindex>", home_url( '/sitemap.xml' ) );
			$t->page( "<urlset><url><loc><![CDATA[https://example.com/]]></loc></url></urlset>", home_url( '/post-sitemap.xml' ) );
		},
		'expect' => array( 'count' => 0 ),
	),
	// 1.0.4: robots.txt names the sitemap (Slim SEO at /sitemap.xml with core sitemaps off); no plugin detected.
	'clean_sitemap_found_through_robots' => array(
		'setup'  => function ( $t ) {
			$t->state()['home'] = 'https://example.com'; $t->state()['env'] = 'production';
			$t->page( $t->doc( '<main>home</main>' ) );
			$t->page( "User-agent: *\nSitemap: https://example.com/sitemap.xml\n", home_url( '/robots.txt' ) );
			$t->page( '<urlset><url><loc>https://example.com/</loc></url></urlset>', home_url( '/sitemap.xml' ) );
		},
		'expect' => array( 'count' => 0 ),
	),
	// 1.0.4: Yoast active beside something that defines omens_seo_get_description(): Yoast wins, its sitemap is found.
	'clean_yoast_wins_over_omens_function' => array(
		'setup'  => function ( $t ) {
			$t->state()['home'] = 'https://example.com'; $t->state()['env'] = 'production'; $t->state()['options']['active_plugins'] = array( 'wordpress-seo/wp-seo.php' );
			if ( ! function_exists( 'omens_seo_get_description' ) ) { eval( 'function omens_seo_get_description() { return ""; }' ); } // test double only
			$t->page( $t->doc( '<main>home</main>' ) );
			$t->page( '<sitemapindex><sitemap><loc>https://example.com/page-sitemap.xml</loc></sitemap></sitemapindex>', home_url( '/sitemap_index.xml' ) );
			$t->page( '<urlset><url><loc>https://example.com/</loc></url></urlset>', home_url( '/page-sitemap.xml' ) );
			$t->assert( 'yoast' === omens_audit_seo_plugin()['slug'], 'Yoast should be detected ahead of the Omens SEO function fallback' );
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
