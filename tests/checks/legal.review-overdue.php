<?php
// phpcs:ignoreFile
$legal = function ( $t, $privacy_age_days, $a11y_age_days ) {
	$id = omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'privacy-policy', 'post_title' => 'Privacy', 'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', time() - $privacy_age_days * 86400 ) ) );
	$t->state()['options']['wp_page_for_privacy_policy'] = $id;
	omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'accessibility-statement', 'post_title' => 'Accessibility', 'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', time() - $a11y_age_days * 86400 ) ) );
};
return array(
	'fires' => array(
		'setup'  => function ( $t ) use ( $legal ) { $legal( $t, 400, 30 ); },
		'expect' => array( 'count' => 1, 'severity' => 'moderate', 'route' => 'human', 'subject' => 'privacy', 'title_contains' => '400 days' ),
	),
	'fires_both' => array(
		'setup'  => function ( $t ) use ( $legal ) { $legal( $t, 366, 800 ); },
		'expect' => array( 'count' => 2 ),
	),
	'clean' => array(
		'setup'  => function ( $t ) use ( $legal ) { $legal( $t, 364, 10 ); },
		'expect' => array( 'count' => 0 ),
	),
	'clean_interval_filtered' => array(
		'setup'  => function ( $t ) use ( $legal ) { add_filter( 'omens_audit/legal_review_days', function () { return 730; } ); $legal( $t, 500, 500 ); },
		'expect' => array( 'count' => 0 ),
	),
);
