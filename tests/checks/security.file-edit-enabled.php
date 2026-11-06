<?php
// phpcs:ignoreFile
return array(
	'fires' => array(
		'setup'  => function ( $t ) { define( 'DISALLOW_FILE_EDIT', false ); },
		'expect' => array( 'count' => 1, 'severity' => 'serious', 'route' => 'config', 'subject' => 'DISALLOW_FILE_EDIT' ),
	),
	'fires_undefined' => array(
		'expect' => array( 'count' => 1, 'summary_contains' => 'not defined' ),
	),
	'clean' => array(
		'setup'  => function ( $t ) { define( 'DISALLOW_FILE_EDIT', true ); },
		'expect' => array( 'count' => 0 ),
	),
);
