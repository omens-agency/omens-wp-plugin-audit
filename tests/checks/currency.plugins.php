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
				'response'     => array( 'akismet/akismet.php' => (object) array( 'new_version' => '5.7.2', 'package' => 'https://downloads.wordpress.org/plugin/akismet.5.7.2.zip' ) ),
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
	// A bundled or unlicensed plugin announces an update but offers no package: one info line, not a task.
	'fires_no_package_is_one_info_line' => array(
		'setup'  => function ( $t ) use ( $plugins ) {
			$plugins( $t );
			$t->state()['plugins']['js_composer/js_composer.php'] = array( 'Name' => 'WPBakery Page Builder', 'Version' => '7.7.2' );
			$t->state()['transients']['update_plugins'] = (object) array(
				'last_checked' => time() - 3600,
				'response'     => array(
					'revslider/revslider.php'     => (object) array( 'new_version' => '6.8.1', 'package' => '' ),
					'js_composer/js_composer.php' => (object) array( 'new_version' => '9.1' ),
				),
				'no_update'    => array( 'akismet/akismet.php' => (object) array(), 'hello.php' => (object) array() ),
			);
		},
		'expect' => function ( $f, $run, $t ) {
			$t->assert( 1 === count( $f ), 'expected one roll-up finding, got ' . count( $f ) );
			$t->assert( 'no-update-package' === $f[0]['subject'] && 'info' === $f[0]['severity'], 'roll-up subject or severity wrong' );
			$t->assert( false !== strpos( $f[0]['detail'], 'WPBakery Page Builder 7.7.2 (9.1 available)' ) && false !== strpos( $f[0]['detail'], 'Slider Revolution 6.6.0 (6.8.1 available)' ), 'detail: ' . $f[0]['detail'] );
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
