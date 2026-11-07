<?php
// phpcs:ignoreFile
$pages = function ( $t, $footer_links ) {
	$p = omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'privacy-policy', 'post_title' => 'Privacy' ) );
	$t->state()['options']['wp_page_for_privacy_policy'] = $p;
	omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'terms-of-use', 'post_title' => 'Terms' ) );
	omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'accessibility-statement', 'post_title' => 'Accessibility' ) );
	$t->page( $t->doc( '<main>x</main><footer>' . $footer_links . '</footer>' ) );
};
return array(
	'fires' => array(
		'setup'  => function ( $t ) { define( 'OMENS_STARTER_VERSION', '1.12.0' ); $t->page( $t->doc( '<main>x</main><footer><a href="/contact/">Contact</a></footer>' ) ); },
		'expect' => function ( $f, $run, $t ) {
			$subjects = array_column( $f, 'subject' ); sort( $subjects );
			$t->assert( array( 'accessibility:missing', 'privacy:missing', 'terms:missing' ) === $subjects, json_encode( $subjects ) );
			foreach ( $f as $x ) { $t->assert( 'serious' === $x['severity'] && 'content' === $x['fix']['route'], 'serious on a custom build' ); }
		},
	),
	'fires_moderate_on_purchased' => array(
		'setup'  => function ( $t ) { $t->page( $t->doc( '<main>x</main>' ) ); },
		'expect' => array( 'count' => 3, 'severity' => 'moderate' ),
	),
	'fires_unlinked' => array(
		'setup'  => function ( $t ) use ( $pages ) { $pages( $t, '<a href="/privacy-policy/">Privacy</a><a href="https://example.test/terms-of-use">Terms</a>' ); },
		'expect' => array( 'count' => 1, 'subject' => 'accessibility:unlinked', 'where_contains' => 'footer menu' ),
	),
	'fires_unpublished' => array(
		'setup'  => function ( $t ) use ( $pages ) { $pages( $t, '<a href="/privacy-policy/">P</a><a href="/terms-of-use/">T</a><a href="/accessibility-statement/">A</a>' ); $t->state()['posts'][ $t->state()['options']['wp_page_for_privacy_policy'] ]->post_status = 'draft'; },
		'expect' => array( 'count' => 1, 'subject' => 'privacy:unpublished' ),
	),
	'clean' => array(
		'setup'  => function ( $t ) use ( $pages ) { $pages( $t, '<a href="/privacy-policy/">Privacy</a><a href="https://example.test/terms-of-use/">Terms</a><a href="/accessibility-statement">Accessibility</a>' ); },
		'expect' => array( 'count' => 0 ),
	),
	'clean_maintenance_mode_skips_rendered_check' => array(
		'setup'  => function ( $t ) use ( $pages ) {
			$pages( $t, '' ); // Footer with no legal links, which would fire three "unlinked" findings...
			$file = $t->file( 'abspath/.maintenance', '<?php $upgrading = ' . time() . '; ?>' );
			add_filter( 'omens_audit/maintenance_file', function () use ( $file ) { return $file; } );
		},
		'expect' => function ( $f, $run, $t ) {
			$t->assert( 0 === count( $f ), '...but maintenance mode means home was not fetched: got ' . count( $f ) );
			$fetch_failed = array_filter( $run['findings'], function ( $x ) { return 'run.fetch-failed' === $x['check']; } );
			$t->assert( count( $fetch_failed ) >= 1 && false !== strpos( reset( $fetch_failed )['summary'], 'maintenance' ), 'expected run.fetch-failed naming maintenance mode' );
		},
	),
	'clean_password_gate_is_not_the_site' => array(
		'setup'  => function ( $t ) use ( $pages ) {
			$pages( $t, '' );
			// Overwrite home with a review gate: 200, no landmarks, a password field.
			$t->page( '<!doctype html><html lang="en"><head><title>Example</title></head><body><form method="post"><h1>Example</h1><p>This site is in review.</p><input name="gate" type="password"><button>Enter</button></form></body></html>' );
		},
		'expect' => function ( $f, $run, $t ) {
			$t->assert( 0 === count( $f ), 'a gate page must not count as a footer with no legal links: got ' . count( $f ) );
			$ff = array_filter( $run['findings'], function ( $x ) { return 'run.fetch-failed' === $x['check']; } );
			$t->assert( count( $ff ) >= 1 && false !== strpos( reset( $ff )['summary'], 'gate' ), 'expected run.fetch-failed naming the gate' );
		},
	),
	'clean_custom_slugs_by_filter' => array(
		'setup'  => function ( $t ) {
			$p = omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'privacy' ) );
			$t->state()['options']['wp_page_for_privacy_policy'] = $p;
			omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'legal' ) );
			omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'a11y' ) );
			add_filter( 'omens_audit/legal_slugs', function ( $s ) { $s['terms'] = array( 'legal' ); $s['accessibility'] = array( 'a11y' ); return $s; } );
			$t->page( $t->doc( '<main>x</main><footer><a href="/privacy/">P</a><a href="/legal/">L</a><a href="/a11y/">A</a></footer>' ) );
		},
		'expect' => array( 'count' => 0 ),
	),
);
