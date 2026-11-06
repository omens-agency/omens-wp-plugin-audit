<?php
// phpcs:ignoreFile
return array(
	'fires' => array(
		'setup'  => function ( $t ) { $t->page( $t->doc( '<main>x</main>', '<script src="https://www.googletagmanager.com/gtag/js?id=G-1"></script>' ) ); },
		'expect' => array( 'count' => 1, 'severity' => 'serious', 'route' => 'config', 'subject' => 'cookieyes' ),
	),
	'clean' => array(
		'setup'  => function ( $t ) { $t->page( $t->doc( '<main>x</main>', '<script id="cookieyes" src="https://cdn-cookieyes.com/client_data/abc123/script.js"></script>' ) ); },
		'expect' => array( 'count' => 0 ),
	),
	'clean_other_platform_by_filter' => array(
		'setup'  => function ( $t ) {
			add_filter( 'omens_audit/consent_platform', function () { return array( 'name' => 'iubenda', 'patterns' => array( '#cdn\.iubenda\.com#i' ) ); } );
			$t->page( $t->doc( '<main>x</main>', '<script src="https://cdn.iubenda.com/cs/iubenda_cs.js"></script>' ) );
		},
		'expect' => array( 'count' => 0 ),
	),
	'skipped_locally' => array(
		'setup'  => function ( $t ) { $t->state()['env'] = 'local'; $t->page( $t->doc( '<main>x</main>' ) ); },
		'expect' => array( 'count' => 1, 'severity' => 'info', 'subject' => 'not-applicable' ),
	),
);
