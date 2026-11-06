<?php
// phpcs:ignoreFile
$uploads = function ( $t, array $files ) {
	foreach ( $files as $rel => $body ) { $t->file( 'uploads/' . $rel, $body ); }
	$t->state()['uploads'] = $t->tmp . '/uploads';
};
return array(
	'fires' => array(
		'setup'  => function ( $t ) use ( $uploads ) {
			$uploads( $t, array( '2026/11/photo.jpg' => 'jpg', '2026/11/wp-conf1g.php' => '<?php eval(base64_decode($_POST["x"]));', 'index.php' => '' ) );
		},
		'expect' => array( 'count' => 1, 'severity' => 'critical', 'route' => 'human', 'subject' => '2026/11/wp-conf1g.php' ),
	),
	'fires_phtml_and_nonstandard_index' => array(
		'setup'  => function ( $t ) use ( $uploads ) {
			$uploads( $t, array( 'a/shell.phtml' => '<?php', 'b/index.php' => '<?php echo "not silence"; system($_GET["c"]);' ) );
		},
		'expect' => array( 'count' => 2 ),
	),
	'clean' => array(
		'setup'  => function ( $t ) use ( $uploads ) {
			$uploads( $t, array(
				'index.php'            => '',
				'2026/index.php'       => "<?php\n// Silence is golden.\n",
				'2026/11/photo.jpg'    => 'jpg',
				'2026/11/doc.pdf'      => 'pdf',
				'wp-migrate-db/x.php'  => '<?php // allowlisted plugin path',
				'custom-vendor/ok.php' => '<?php // allowed by filter',
			) );
			add_filter( 'omens_audit/uploads_allow', function ( $a ) { $a[] = 'custom-vendor/*'; return $a; } );
		},
		'expect' => array( 'count' => 0 ),
	),
	'overflow_caps_listing' => array(
		'setup'  => function ( $t ) use ( $uploads ) {
			$files = array();
			for ( $i = 0; $i < 60; $i++ ) { $files[ "d/s$i.php" ] = '<?php'; }
			$uploads( $t, $files );
		},
		'expect' => function ( $f, $run, $t ) { $t->assert( 51 === count( $f ), 'expected 50 + overflow, got ' . count( $f ) ); $t->assert( 'overflow' === end( $f )['subject'], 'last finding should be the overflow note' ); },
	),
);
