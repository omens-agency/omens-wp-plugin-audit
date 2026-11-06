<?php
// phpcs:ignoreFile
return array(
	'fires' => array(
		'setup'  => function ( $t ) { define( 'OMENS_STARTER_VERSION', '1.12.0' ); $t->page( $t->doc( '<main><h1>Hi</h1></main>', '', '' ) ); },
		'expect' => array( 'count' => 1, 'severity' => 'serious', 'route' => 'code', 'criterion' => 'WCAG 2.2 3.1.1', 'subject' => 'html-lang:missing' ),
	),
	'fires_mismatch' => array(
		'setup'  => function ( $t ) { $t->state()['locale'] = 'en_US'; $t->page( $t->doc( '<main><h1>Hi</h1></main>', '', ' lang="de"' ) ); },
		'expect' => array( 'count' => 1, 'subject' => 'html-lang:mismatch:de', 'title_contains' => 'does not match' ),
	),
	'fires_once_across_pages' => array(
		'setup'  => function ( $t ) {
			$t->page( $t->doc( '<main>home</main>', '', '' ) );
			$id = omens_test_add_post( array( 'post_type' => 'page', 'post_name' => 'about' ) );
			$t->page( $t->doc( '<main>about</main>', '', '' ), get_permalink( $id ) );
		},
		'expect' => array( 'count' => 1 ),
	),
	'clean' => array(
		'setup'  => function ( $t ) { $t->state()['locale'] = 'en_GB'; $t->page( $t->doc( '<main><h1>Hi</h1></main>', '', ' lang="en-GB"' ) ); },
		'expect' => array( 'count' => 0 ),
	),
	'clean_region_variant' => array(
		'setup'  => function ( $t ) { $t->state()['locale'] = 'en_US'; $t->page( $t->doc( '<main>x</main>', '', ' lang="en"' ) ); },
		'expect' => array( 'count' => 0 ),
	),
);
