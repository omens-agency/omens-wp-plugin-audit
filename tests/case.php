<?php
/**
 * Run one fixture case in its own process.
 *
 *   php tests/case.php tests/checks/security.file-edit-enabled.php fires
 *
 * A fixture file returns an array of cases. Each case has a `setup` closure
 * that shapes $GLOBALS['omens_test'] (and may define constants), and an
 * `expect` that is either an array of expectations ( count, severity, route,
 * confidence, where_contains, title_contains, subject ) applied to the check's
 * findings, or a closure ( $findings, $run, $t ) that throws on failure.
 * Optional `check` names the check when the filename is not its ID.
 *
 * @package OmensAudit
 */

// phpcs:ignoreFile

require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/omens-wp-plugin-audit.php';

$file = $argv[1] ?? '';
$name = $argv[2] ?? '';
if ( ! is_file( $file ) || '' === $name ) {
	fwrite( STDERR, "usage: php tests/case.php <fixture.php> <case>\n" );
	exit( 2 );
}
$cases = require $file;
if ( ! isset( $cases[ $name ] ) ) {
	fwrite( STDERR, "no case '$name' in $file\n" );
	exit( 2 );
}
$case  = $cases[ $name ];
$check = $case['check'] ?? $cases['check'] ?? basename( $file, '.php' );

class Omens_Audit_Test {
	public $tmp;
	public function __construct() {
		$this->tmp = sys_get_temp_dir() . '/omens-audit-test-' . getmypid();
		mkdir( $this->tmp, 0777, true );
	}
	public function cleanup() {
		if ( is_dir( $this->tmp ) ) {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->tmp, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
			foreach ( $it as $f ) { $f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() ); }
			rmdir( $this->tmp );
		}
	}
	public function file( $rel, $content ) {
		$path = $this->tmp . '/' . ltrim( $rel, '/' );
		if ( ! is_dir( dirname( $path ) ) ) { mkdir( dirname( $path ), 0777, true ); }
		file_put_contents( $path, $content );
		return $path;
	}
	public function &state() { return $GLOBALS['omens_test']; }
	public function page( $html, $url = '' ) {
		$url = $url ?: home_url( '/' );
		$GLOBALS['omens_test']['http'][ $url ] = array( 'code' => 200, 'body' => $html );
		return $url;
	}
	public function doc( $body, $head = '', $lang = ' lang="en-US"' ) {
		return "<!DOCTYPE html><html{$lang}><head><meta charset=\"utf-8\"><title>T</title>{$head}</head><body>{$body}</body></html>";
	}
	public function fail( $msg ) { throw new RuntimeException( $msg ); }
	public function assert( $cond, $msg ) { if ( ! $cond ) { $this->fail( $msg ); } }
}

$t = new Omens_Audit_Test();
$exit = 0;
try {
	if ( isset( $case['setup'] ) ) {
		$case['setup']( $t );
	}
	$run      = omens_audit_run( array( 'checks' => array( $check ), 'dry_run' => true ) );
	$findings = array_values( array_filter( $run['findings'], function ( $f ) use ( $check ) { return $f['check'] === $check; } ) );
	$others   = array_values( array_filter( $run['findings'], function ( $f ) use ( $check ) { return $f['check'] !== $check; } ) );

	foreach ( $run['findings'] as $f ) {
		if ( 'run.check-failed' === $f['check'] ) { $t->fail( 'check threw: ' . $f['summary'] . ' at ' . $f['detail'] ); }
		if ( empty( $f['fix']['steps'] ) ) { $t->fail( 'finding has empty fix.steps: ' . $f['title'] ); }
		if ( empty( $f['title'] ) || empty( $f['summary'] ) ) { $t->fail( 'finding has empty title or summary: ' . $f['check'] ); }
		if ( 40 !== strlen( $f['fingerprint'] ) ) { $t->fail( 'bad fingerprint' ); }
	}

	$expect = $case['expect'] ?? array( 'count' => 0 );
	if ( is_callable( $expect ) ) {
		$expect( $findings, $run, $t );
	} else {
		if ( isset( $expect['count'] ) ) {
			$t->assert( count( $findings ) === (int) $expect['count'], sprintf( 'expected %d finding(s), got %d: %s', $expect['count'], count( $findings ), json_encode( array_map( function ( $f ) { return $f['title'] . ' [' . $f['subject'] . ']'; }, $findings ) ) ) );
		}
		if ( isset( $expect['min'] ) ) {
			$t->assert( count( $findings ) >= (int) $expect['min'], sprintf( 'expected at least %d finding(s), got %d', $expect['min'], count( $findings ) ) );
		}
		foreach ( $findings as $i => $f ) {
			foreach ( array( 'severity', 'subject', 'criterion' ) as $k ) {
				if ( isset( $expect[ $k ] ) ) { $t->assert( $f[ $k ] === $expect[ $k ], "finding $i: $k is '{$f[$k]}', expected '{$expect[$k]}'" ); }
			}
			foreach ( array( 'route', 'confidence' ) as $k ) {
				if ( isset( $expect[ $k ] ) ) { $t->assert( $f['fix'][ $k ] === $expect[ $k ], "finding $i: fix.$k is '{$f['fix'][$k]}', expected '{$expect[$k]}'" ); }
			}
			if ( isset( $expect['where_contains'] ) ) { $t->assert( false !== stripos( $f['fix']['where'], $expect['where_contains'] ), "finding $i: fix.where '{$f['fix']['where']}' lacks '{$expect['where_contains']}'" ); }
			if ( isset( $expect['title_contains'] ) ) { $t->assert( false !== stripos( $f['title'], $expect['title_contains'] ), "finding $i: title '{$f['title']}' lacks '{$expect['title_contains']}'" ); }
			if ( isset( $expect['summary_contains'] ) ) { $t->assert( false !== stripos( $f['summary'], $expect['summary_contains'] ), "finding $i: summary '{$f['summary']}' lacks '{$expect['summary_contains']}'" ); }
		}
	}
	echo json_encode( array( 'ok' => true, 'case' => $name, 'check' => $check, 'findings' => count( $findings ), 'other' => count( $others ), 'ms' => $run['duration_ms'] ) ), "\n";
} catch ( Throwable $e ) {
	echo json_encode( array( 'ok' => false, 'case' => $name, 'check' => $check, 'error' => $e->getMessage(), 'at' => basename( $e->getFile() ) . ':' . $e->getLine() ) ), "\n";
	$exit = 1;
}
$t->cleanup();
exit( $exit );
