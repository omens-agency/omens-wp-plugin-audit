<?php
// phpcs:ignoreFile
return array(
	'fires' => array(
		'setup'  => function ( $t ) { define( 'WP_DEBUG', true ); define( 'WP_DEBUG_DISPLAY', true ); ini_set( 'display_errors', '0' ); },
		'expect' => array( 'count' => 1, 'severity' => 'serious', 'route' => 'config' ),
	),
	'fires_debug_without_display_constant' => array(
		'setup'  => function ( $t ) { define( 'WP_DEBUG', true ); ini_set( 'display_errors', '0' ); },
		'expect' => array( 'count' => 1, 'summary_contains' => 'not defined' ),
	),
	'fires_ini_display_errors' => array(
		'setup'  => function ( $t ) { define( 'WP_DEBUG', false ); ini_set( 'display_errors', '1' ); },
		'expect' => array( 'count' => 1, 'summary_contains' => 'display_errors' ),
	),
	'clean' => array(
		'setup'  => function ( $t ) { define( 'WP_DEBUG', false ); ini_set( 'display_errors', '0' ); },
		'expect' => array( 'count' => 0 ),
	),
	'clean_debug_logged_not_displayed' => array(
		'setup'  => function ( $t ) { define( 'WP_DEBUG', true ); define( 'WP_DEBUG_DISPLAY', false ); ini_set( 'display_errors', '1' ); },
		'expect' => array( 'count' => 0 ),
	),
	'skipped_on_staging' => array(
		'setup'  => function ( $t ) { $t->state()['env'] = 'staging'; define( 'WP_DEBUG', true ); define( 'WP_DEBUG_DISPLAY', true ); },
		'expect' => function ( $f, $run, $t ) { $t->assert( 1 === count( $f ) && 'info' === $f[0]['severity'] && 'not-applicable' === $f[0]['subject'], 'expected one info not-applicable finding' ); },
	),
);
