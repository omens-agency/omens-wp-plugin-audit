<?php
// phpcs:ignoreFile
$plugins = function ( $t ) {
	$t->state()['plugins'] = array(
		'akismet/akismet.php'       => array( 'Name' => 'Akismet', 'Version' => '5.7.0' ),
		'hello.php'                 => array( 'Name' => 'Hello Dolly', 'Version' => '1.7.2' ),
		'revslider/revslider.php'   => array( 'Name' => 'Slider Revolution', 'Version' => '6.6.0' ),
	);
};
return array(
	'fires' => array(
		'setup'  => function ( $t ) use ( $plugins ) {
			$plugins( $t );
			$t->state()['transients']['update_plugins'] = (object) array(
				'last_checked' => time() - 3600,
				'response'     => array( 'akismet/akismet.php' => (object) array( 'new_version' => '5.7.2' ) ),
				'no_update'    => array( 'hello.php' => (object) array() ),
			);
		},
		'expect' => function ( $f, $run, $t ) {
			$t->assert( 2 === count( $f ), 'expected an akismet finding and a no-channel finding, got ' . count( $f ) );
			$by = array_column( $f, null, 'subject' );
			$t->assert( isset( $by['akismet'] ) && 'moderate' === $by['akismet']['severity'] && 'config' === $by['akismet']['fix']['route'], 'akismet finding wrong' );
			$t->assert( isset( $by['no-update-channel'] ) && 'minor' === $by['no-update-channel']['severity'] && false !== strpos( $by['no-update-channel']['detail'], 'Slider Revolution' ), 'no-channel finding wrong' );
		},
	),
	'clean' => array(
		'setup'  => function ( $t ) use ( $plugins ) {
			$plugins( $t );
			$t->state()['transients']['update_plugins'] = (object) array(
				'last_checked' => time() - 3600,
				'response'     => array(),
				'no_update'    => array( 'akismet/akismet.php' => (object) array(), 'hello.php' => (object) array(), 'revslider/revslider.php' => (object) array() ),
			);
		},
		'expect' => array( 'count' => 0 ),
	),
	'cannot_run_without_transient' => array(
		'setup'  => function ( $t ) use ( $plugins ) { $plugins( $t ); },
		'expect' => array( 'count' => 1, 'severity' => 'info', 'subject' => 'cannot-run' ),
	),
);
