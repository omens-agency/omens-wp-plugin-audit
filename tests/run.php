<?php
/**
 * Run every fixture case, each in its own PHP process, and refuse to pass if
 * any registered check lacks a fixture with both a `fires` and a `clean` case.
 *
 *   php tests/run.php            # everything
 *   php tests/run.php a11y       # fixtures whose name contains "a11y"
 *
 * @package OmensAudit
 */

// phpcs:ignoreFile

require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/omens-wp-plugin-audit.php';

$filter = $argv[1] ?? '';
$files  = glob( __DIR__ . '/checks/*.php' );
$pass   = 0;
$fail   = 0;
$seen   = array();

foreach ( $files as $file ) {
	$id = basename( $file, '.php' );
	if ( '' !== $filter && false === strpos( $id, $filter ) ) {
		continue;
	}
	$cases = require $file;
	$check = $cases['check'] ?? $id;
	unset( $cases['check'] );
	$seen[ $check ] = array_keys( $cases );
	foreach ( $cases as $name => $case ) {
		$cmd = sprintf( '%s -d display_errors=stderr %s %s %s 2>&1', escapeshellarg( PHP_BINARY ), escapeshellarg( __DIR__ . '/case.php' ), escapeshellarg( $file ), escapeshellarg( $name ) );
		$out = shell_exec( $cmd );
		$res = json_decode( trim( (string) strrchr( "\n" . trim( (string) $out ), "\n" ) ), true );
		if ( is_array( $res ) && ! empty( $res['ok'] ) ) {
			++$pass;
			printf( "  ok   %-36s %-28s %d finding(s) %d ms\n", $check, $name, $res['findings'], $res['ms'] );
		} else {
			++$fail;
			printf( "  FAIL %-36s %-28s %s\n", $check, $name, is_array( $res ) ? $res['error'] . ' (' . $res['at'] . ')' : trim( (string) $out ) );
		}
	}
}

if ( '' === $filter ) {
	foreach ( omens_audit_checks() as $id => $check ) {
		if ( empty( $seen[ $id ] ) ) {
			++$fail;
			echo "  FAIL $id has no fixture file (tests/checks/$id.php)\n";
			continue;
		}
		foreach ( array( 'fires', 'clean' ) as $need ) {
			if ( ! in_array( $need, $seen[ $id ], true ) ) {
				++$fail;
				echo "  FAIL $id fixture has no '$need' case\n";
			}
		}
	}
	// The tracker signatures are shared with web-legal-pages; warn if the two copies drift.
	$skills_copy = dirname( __DIR__, 4 ) . '/Skills/web-legal-pages/scripts/signatures.json';
	if ( is_file( $skills_copy ) && sha1_file( $skills_copy ) !== sha1_file( dirname( __DIR__ ) . '/data/trackers.json' ) ) {
		echo "  WARN data/trackers.json differs from Skills/web-legal-pages/scripts/signatures.json; copy the canonical one across.\n";
	}
}

printf( "== %d passed, %d failed\n", $pass, $fail );
exit( $fail ? 1 : 0 );
