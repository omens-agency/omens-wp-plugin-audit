<?php
// phpcs:ignoreFile
return array(
	'fires' => array(
		'setup'  => function ( $t ) { $t->state()['home'] = 'https://example.com'; define( 'WP_DEBUG', true ); define( 'WP_DEBUG_DISPLAY', true ); ini_set( 'display_errors', '0' ); },
		'expect' => array( 'count' => 1, 'severity' => 'serious', 'route' => 'config' ),
	),
	'fires_debug_without_display_constant' => array(
		'setup'  => function ( $t ) { $t->state()['home'] = 'https://example.com'; define( 'WP_DEBUG', true ); ini_set( 'display_errors', '0' ); },
		'expect' => array( 'count' => 1, 'summary_contains' => 'not defined' ),
	),
	'fires_ini_display_errors' => array(
		'setup'  => function ( $t ) { $t->state()['home'] = 'https://example.com'; define( 'WP_DEBUG', false ); ini_set( 'display_errors', '1' ); add_filter( 'omens_audit/php_sapi', function () { return 'fpm-fcgi'; } ); },
		'expect' => array( 'count' => 1, 'summary_contains' => 'display_errors' ),
	),
	'clean_ini_display_errors_under_cli' => array(
		// PHP's CLI forces display_errors on; under WP-CLI the ini value says nothing about the web server.
		'setup'  => function ( $t ) { $t->state()['home'] = 'https://example.com'; define( 'WP_DEBUG', false ); ini_set( 'display_errors', '1' ); add_filter( 'omens_audit/php_sapi', function () { return 'cli'; } ); },
		'expect' => array( 'count' => 0 ),
	),
	'skipped_on_staging_host' => array(
		'setup'  => function ( $t ) { $t->state()['home'] = 'https://client.mystagingwebsite.com'; $t->state()['env'] = 'production'; define( 'WP_DEBUG', true ); define( 'WP_DEBUG_DISPLAY', true ); },
		'expect' => function ( $f, $run, $t ) { $t->assert( 1 === count( $f ) && 'not-applicable' === $f[0]['subject'], 'a staging host is staging whatever the constant says' ); },
	),
	'clean' => array(
		'setup'  => function ( $t ) { $t->state()['home'] = 'https://example.com'; define( 'WP_DEBUG', false ); ini_set( 'display_errors', '0' ); },
		'expect' => array( 'count' => 0 ),
	),
	'clean_debug_logged_not_displayed' => array(
		'setup'  => function ( $t ) { $t->state()['home'] = 'https://example.com'; define( 'WP_DEBUG', true ); define( 'WP_DEBUG_DISPLAY', false ); ini_set( 'display_errors', '1' ); },
		'expect' => array( 'count' => 0 ),
	),
	'skipped_on_staging' => array(
		'setup'  => function ( $t ) { $t->state()['env'] = 'staging'; define( 'WP_DEBUG', true ); define( 'WP_DEBUG_DISPLAY', true ); },
		'expect' => function ( $f, $run, $t ) { $t->assert( 1 === count( $f ) && 'info' === $f[0]['severity'] && 'not-applicable' === $f[0]['subject'], 'expected one info not-applicable finding' ); },
	),
);
