<?php
// phpcs:ignoreFile
$prod = function ( $t ) { $t->state()['home'] = 'https://example.com'; $t->state()['env'] = 'production'; };
return array(
	'fires' => array(
		'setup'  => function ( $t ) use ( $prod ) { $prod( $t ); $t->state()['options']['blog_public'] = '0'; $t->page( $t->doc( '<main>x</main>' ) ); },
		'expect' => array( 'count' => 1, 'severity' => 'critical', 'route' => 'config', 'subject' => 'production-noindex' ),
	),
	'fires_noindex_meta' => array(
		'setup'  => function ( $t ) use ( $prod ) { $prod( $t ); $t->page( $t->doc( '<main>x</main>', '<meta name="robots" content="noindex, follow">' ) ); },
		'expect' => array( 'count' => 1, 'subject' => 'production-noindex', 'summary_contains' => 'robots' ),
	),
	'fires_staging_indexable' => array(
		'setup'  => function ( $t ) { $t->state()['home'] = 'https://client-staging.omens.site'; $t->state()['env'] = 'staging'; $t->page( $t->doc( '<main>x</main>' ) ); },
		'expect' => array( 'count' => 1, 'severity' => 'serious', 'subject' => 'staging-indexable' ),
	),
	'fires_env_mismatch_trusts_host' => array(
		'setup'  => function ( $t ) { $t->state()['home'] = 'https://client.mystagingwebsite.com'; $t->state()['env'] = 'production'; $t->page( $t->doc( '<main>x</main>' ) ); },
		'expect' => function ( $f, $run, $t ) {
			$subjects = array_column( $f, 'subject' ); sort( $subjects );
			$t->assert( array( 'environment-mismatch', 'staging-indexable' ) === $subjects, 'got ' . json_encode( $subjects ) );
			$by = array_column( $f, null, 'subject' );
			$t->assert( 'info' === $by['environment-mismatch']['severity'], 'mismatch should be info' );
		},
	),
	'production_host_staging_constant_open' => array(
		// A sandbox that became production by DNS: the constant still says staging. The host wins,
		// so there is no "staging is indexable" finding and no step to discourage search engines.
		'setup'  => function ( $t ) { $t->state()['home'] = 'https://example.com'; $t->state()['env'] = 'staging'; $t->page( $t->doc( '<main>x</main>' ) ); },
		'expect' => function ( $f, $run, $t ) {
			$subjects = array_column( $f, 'subject' );
			$t->assert( array( 'environment-mismatch' ) === $subjects, 'got ' . json_encode( $subjects ) );
			$t->assert( 'info' === $f[0]['severity'], 'mismatch should be info' );
		},
	),
	'production_host_staging_constant_blocked' => array(
		'setup'  => function ( $t ) { $t->state()['home'] = 'https://example.com'; $t->state()['env'] = 'staging'; $t->state()['options']['blog_public'] = '0'; $t->page( $t->doc( '<main>x</main>' ) ); },
		'expect' => function ( $f, $run, $t ) {
			$subjects = array_column( $f, 'subject' ); sort( $subjects );
			$t->assert( array( 'environment-mismatch', 'production-noindex' ) === $subjects, 'got ' . json_encode( $subjects ) );
		},
	),
	'clean' => array(
		'setup'  => function ( $t ) use ( $prod ) { $prod( $t ); $t->page( $t->doc( '<main>x</main>', '<meta name="robots" content="index, follow, max-image-preview:large">' ) ); },
		'expect' => array( 'count' => 0 ),
	),
	'clean_staging_blocked' => array(
		'setup'  => function ( $t ) { $t->state()['home'] = 'https://stg.example.com'; $t->state()['env'] = 'staging'; $t->state()['options']['blog_public'] = '0'; $t->page( $t->doc( '<main>x</main>' ) ); },
		'expect' => array( 'count' => 0 ),
	),
);
