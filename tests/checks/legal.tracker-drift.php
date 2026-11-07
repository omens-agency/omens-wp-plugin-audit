<?php
// phpcs:ignoreFile
$privacy = function ( $t, $content ) {
	$id = omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'privacy-policy', 'post_title' => 'Privacy Policy', 'post_content' => $content ) );
	$t->state()['options']['wp_page_for_privacy_policy'] = $id;
};
return array(
	'fires' => array(
		'setup'  => function ( $t ) use ( $privacy ) {
			$privacy( $t, '<p>We use Google Analytics 4 to measure traffic.</p>' );
			$t->page( $t->doc( '<main>x</main>', '<script async src="https://www.googletagmanager.com/gtag/js?id=G-1"></script><script>!function(f,b,e,v,n,t,s){}(window,document,"script","https://connect.facebook.net/en_US/fbevents.js");</script><script src="https://static.hotjar.com/c/hotjar-1.js"></script>' ) );
		},
		'expect' => function ( $f, $run, $t ) {
			$subjects = array_column( $f, 'subject' ); sort( $subjects );
			$t->assert( array( 'hotjar', 'meta-pixel' ) === $subjects, 'expected Meta Pixel and Hotjar unnamed, GA4 named; got ' . json_encode( $subjects ) );
			foreach ( $f as $x ) { $t->assert( 'content' === $x['fix']['route'] && 'serious' === $x['severity'], 'shape' ); }
		},
	),
	'clean' => array(
		'setup'  => function ( $t ) use ( $privacy ) {
			$privacy( $t, '<h2>Cookies</h2><table><tr><td>Google Analytics 4</td><td>Google LLC</td></tr><tr><td>Hotjar</td><td>Hotjar Ltd.</td></tr></table>' );
			$t->page( $t->doc( '<main>x</main>', '<script src="https://www.googletagmanager.com/gtag/js?id=G-1"></script><script src="https://static.hotjar.com/c/hotjar-1.js"></script>' ) );
		},
		'expect' => array( 'count' => 0 ),
	),
	'clean_provider_named_without_suffix' => array(
		'setup'  => function ( $t ) use ( $privacy ) {
			$privacy( $t, '<p>Advertising cookies are set by Meta Platforms for our Facebook ads.</p>' );
			$t->page( $t->doc( '<main>x</main>', '<script src="https://connect.facebook.net/en_US/fbevents.js"></script>' ) );
		},
		'expect' => array( 'count' => 0 ),
	),
	'clean_strictly_necessary_never_drift' => array(
		'setup'  => function ( $t ) use ( $privacy ) {
			$privacy( $t, '<p>Minimal policy.</p>' );
			$t->page( $t->doc( '<main>x</main>', '<script src="https://cdn-cookieyes.com/client_data/x/script.js"></script><script src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>' ) );
		},
		'expect' => array( 'count' => 0 ),
	),
	'fires_embedded_media_is_moderate' => array(
		'setup'  => function ( $t ) use ( $privacy ) {
			$privacy( $t, '<p>Minimal policy.</p>' );
			$t->page( $t->doc( '<main><iframe src="https://player.vimeo.com/video/1"></iframe></main>' ) );
		},
		'expect' => array( 'count' => 1, 'severity' => 'moderate', 'subject' => 'vimeo-embeds' ),
	),
	'fires_hosting_analytics_is_moderate' => array(
		'setup'  => function ( $t ) use ( $privacy ) {
			$privacy( $t, '<p>Minimal policy.</p>' );
			$t->page( $t->doc( '<main>x</main>', '<script src="https://stats.wp.com/e-202645.js"></script><script src="https://secure.gaug.es/track.js"></script>' ) );
		},
		'expect' => function ( $f, $run, $t ) {
			$subjects = array_column( $f, 'subject' ); sort( $subjects );
			$t->assert( array( 'gauges', 'jetpack-stats' ) === $subjects, 'expected the two platform counters; got ' . json_encode( $subjects ) );
			foreach ( $f as $x ) {
				$t->assert( 'moderate' === $x['severity'], $x['subject'] . ' is moderate, not serious' );
				$t->assert( 'content' === $x['fix']['route'] && false !== strpos( $x['fix']['steps'][0], 'HOSTING_ANALYTICS' ), $x['subject'] . ' routes to the template block' );
			}
		},
	),
	'clean_hosting_analytics_block_present' => array(
		'setup'  => function ( $t ) use ( $privacy ) {
			$privacy( $t, '<p>Our hosting provider, Pressable, Inc. (part of Automattic), measures traffic to the Site.</p><ul><li>Jetpack Stats (Automattic Inc.) records, for each page view, the page address and the referring page.</li><li>The Pressable visit counter (Fathom Analytics, formerly Gauges) counts visits without cookies.</li></ul>' );
			$t->page( $t->doc( '<main>x</main>', '<script src="https://stats.wp.com/e-202645.js"></script><script src="https://secure.gaug.es/track.js"></script>' ) );
		},
		'expect' => array( 'count' => 0 ),
	),
	'skipped_without_privacy_page' => array(
		'setup'  => function ( $t ) { $t->page( $t->doc( '<main>x</main>', '<script src="https://static.hotjar.com/c/hotjar-1.js"></script>' ) ); },
		'expect' => array( 'count' => 1, 'severity' => 'info', 'subject' => 'not-applicable' ),
	),
);
