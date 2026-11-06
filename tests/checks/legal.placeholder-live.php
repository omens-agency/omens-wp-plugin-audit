<?php
// phpcs:ignoreFile
$privacy = function ( $t, $content, $status = 'publish' ) {
	$id = omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'privacy-policy', 'post_title' => 'Privacy Policy', 'post_content' => $content, 'post_status' => $status ) );
	$t->state()['options']['wp_page_for_privacy_policy'] = $id;
	return $id;
};
return array(
	'fires' => array(
		'setup'  => function ( $t ) use ( $privacy ) { $privacy( $t, '<p>Contact [ INSERT: privacy contact email ] with questions. {{COMPANY_NAME}} is the controller.</p>' ); },
		'expect' => array( 'count' => 1, 'severity' => 'critical', 'route' => 'content', 'subject' => 'privacy', 'title_contains' => '2 unfilled' ),
	),
	'fires_terms_block_marker' => array(
		'setup'  => function ( $t ) use ( $privacy ) { $privacy( $t, '<p>Fine.</p>' ); omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'terms-of-use', 'post_content' => '[[BLOCK:ARBITRATION]]' ) ); },
		'expect' => array( 'count' => 1, 'subject' => 'terms' ),
	),
	'clean' => array(
		'setup'  => function ( $t ) use ( $privacy ) { $privacy( $t, '<p>We collect what the form asks for. Shortcodes like [gravityform id="1"] are fine.</p>' ); omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'accessibility-statement', 'post_content' => '<p>WCAG 2.2 AA.</p>' ) ); },
		'expect' => array( 'count' => 0 ),
	),
	'clean_draft_ignored' => array(
		'setup'  => function ( $t ) use ( $privacy ) { $privacy( $t, '[ INSERT: x ]', 'draft' ); },
		'expect' => array( 'count' => 0 ),
	),
);
