<?php
// phpcs:ignoreFile
$custom = function ( $t ) {
	define( 'OMENS_STARTER_VERSION', '1.12.0' );
	$t->state()['plugins'] = array(
		'omens-wp-plugin-audit/omens-wp-plugin-audit.php' => array( 'Name' => 'Omens Site Audit', 'Version' => '0.1.0' ),
		'omens-wp-plugin-seo/omens-wp-plugin-seo.php'     => array( 'Name' => 'Omens SEO', 'Version' => '1.3.0' ),
		'omens-wp-plugin-media/omens-wp-plugin-media.php' => array( 'Name' => 'Omens Media', 'Version' => '1.1.0' ),
	);
	$t->state()['options']['active_plugins'] = array( 'omens-wp-plugin-audit/omens-wp-plugin-audit.php', 'omens-wp-plugin-media/omens-wp-plugin-media.php' );
};
// A purchased site running the audit and security plugins, current, nothing else wrong.
$purchased = function ( $t ) {
	$t->state()['plugins'] = array(
		'omens-wp-plugin-audit/omens-wp-plugin-audit.php'       => array( 'Name' => 'Omens Site Audit', 'Version' => '1.0.11' ),
		'omens-wp-plugin-security/omens-wp-plugin-security.php' => array( 'Name' => 'Omens Security', 'Version' => '1.0.0' ),
	);
	$t->state()['options']['active_plugins'] = array_keys( $t->state()['plugins'] );
	$t->state()['transients']['update_plugins'] = (object) array( 'last_checked' => time(), 'response' => array(), 'no_update' => array() );
};
// A custom build with every house plugin it expects, active, current, token set.
$custom_clean = function ( $t ) use ( $custom ) {
	$custom( $t );
	define( 'OMENS_PLUGIN_UPDATE_TOKEN', 'x' );
	$t->state()['plugins']['omens-wp-plugin-docs/omens-wp-plugin-docs.php'] = array( 'Name' => 'Omens Site Guide', 'Version' => '1.0.0' );
	$t->state()['options']['active_plugins'] = array_keys( $t->state()['plugins'] );
	$t->state()['transients']['update_plugins'] = (object) array( 'last_checked' => time(), 'response' => array(), 'no_update' => array() );
};
return array(
	'fires' => array(
		'setup'  => function ( $t ) use ( $custom ) {
			$custom( $t );
			$t->state()['transients']['update_plugins'] = (object) array( 'last_checked' => time(), 'response' => array( 'omens-wp-plugin-media/omens-wp-plugin-media.php' => (object) array( 'new_version' => '1.2.0' ) ), 'no_update' => array() );
		},
		'expect' => function ( $f, $run, $t ) {
			$subjects = array_column( $f, 'subject' );
			sort( $subjects );
			$t->assert( array( 'omens-wp-plugin-docs:missing', 'omens-wp-plugin-media:outdated', 'omens-wp-plugin-seo:inactive', 'update-token:token' ) === $subjects, 'got ' . json_encode( $subjects ) );
			foreach ( $f as $x ) { $t->assert( 'config' === $x['fix']['route'], 'route' ); }
		},
	),
	'fires_purchased_missing_security' => array(
		'setup'  => function ( $t ) {
			$t->state()['plugins'] = array( 'omens-wp-plugin-audit/omens-wp-plugin-audit.php' => array( 'Name' => 'Omens Site Audit', 'Version' => '0.1.0' ) );
			$t->state()['options']['active_plugins'] = array( 'omens-wp-plugin-audit/omens-wp-plugin-audit.php' );
		},
		'expect' => array( 'count' => 1, 'subject' => 'omens-wp-plugin-security:missing' ),
	),
	// A custom build that runs Yoast (the house SEO pick) is not asked for omens-wp-plugin-seo as well.
	'clean_custom_build_on_yoast' => array(
		'setup'  => function ( $t ) {
			define( 'OMENS_STARTER_VERSION', '1.9.3' );
			define( 'OMENS_PLUGIN_UPDATE_TOKEN', 'x' );
			$t->state()['plugins'] = array(
				'omens-wp-plugin-audit/omens-wp-plugin-audit.php' => array( 'Name' => 'Omens Site Audit', 'Version' => '0.1.0' ),
				'omens-wp-plugin-media/omens-wp-plugin-media.php' => array( 'Name' => 'Omens Media', 'Version' => '1.1.0' ),
				'omens-wp-plugin-docs/omens-wp-plugin-docs.php'   => array( 'Name' => 'Omens Site Guide', 'Version' => '1.0.0' ),
				'wordpress-seo/wp-seo.php'                        => array( 'Name' => 'Yoast SEO', 'Version' => '28.8' ),
			);
			$t->state()['options']['active_plugins'] = array_keys( $t->state()['plugins'] );
			$t->state()['transients']['update_plugins'] = (object) array( 'last_checked' => time(), 'response' => array(), 'no_update' => array() );
		},
		'expect' => array( 'count' => 0 ),
	),
	'clean' => array(
		'setup'  => function ( $t ) use ( $custom ) {
			$custom( $t );
			define( 'OMENS_PLUGIN_UPDATE_TOKEN', 'x' );
			$t->state()['plugins']['omens-wp-plugin-docs/omens-wp-plugin-docs.php'] = array( 'Name' => 'Omens Site Guide', 'Version' => '1.0.0' );
			$t->state()['options']['active_plugins'] = array_keys( $t->state()['plugins'] );
			$t->state()['transients']['update_plugins'] = (object) array( 'last_checked' => time(), 'response' => array(), 'no_update' => array() );
		},
		'expect' => array( 'count' => 0 ),
	),
	// November 18, 2026: the audit plugin copied onto a staging site as its Library source folder.
	'fires_installed_from_source' => array(
		'setup'  => function ( $t ) use ( $purchased ) {
			$purchased( $t );
			$t->file( 'wp-content/plugins/omens-wp-plugin-audit/omens-wp-plugin-audit.php', '<?php' );
			$t->file( 'wp-content/plugins/omens-wp-plugin-audit/tests/checks/x.php', '<?php' );
			$t->file( 'wp-content/plugins/omens-wp-plugin-audit/tools/release.sh', '#!/bin/sh' );
			$t->file( 'wp-content/plugins/omens-wp-plugin-audit/.gitattributes', 'tests/ export-ignore' );
		},
		'expect' => function ( $f, $run, $t ) {
			$t->assert( 1 === count( $f ), 'expected one finding, got ' . json_encode( array_column( $f, 'subject' ) ) );
			$x = $f[0];
			$t->assert( 'omens-wp-plugin-audit:source' === $x['subject'] && 'moderate' === $x['severity'] && 'config' === $x['fix']['route'], 'subject/severity/route: ' . json_encode( array( $x['subject'], $x['severity'], $x['fix']['route'] ) ) );
			$t->assert( false !== strpos( $x['summary'], 'holds .gitattributes, tools/, tests/' ) && false !== strpos( $x['summary'], 'version 1.0.11' ), 'summary names the markers and version: ' . $x['summary'] );
			$t->assert( 0 === strpos( $x['fix']['steps'][0], 'wp plugin install https://github.com/omens-agency/omens-wp-plugin-audit/releases/latest/download/omens-wp-plugin-audit.zip --force' ), 'public zip reinstall first: ' . $x['fix']['steps'][0] );
			$t->assert( in_array( 'found: tests', $x['evidence'], true ), 'evidence lists the markers: ' . json_encode( $x['evidence'] ) );
		},
	),
	// A private plugin symlinked to a git checkout: unlink first, then the token-gated zip.
	'fires_symlinked_private_checkout' => array(
		'setup'  => function ( $t ) use ( $custom_clean ) {
			$custom_clean( $t );
			$t->file( 'checkout/omens-wp-plugin-seo/.git/HEAD', 'ref: refs/heads/main' );
			mkdir( WP_PLUGIN_DIR, 0777, true );
			symlink( $t->tmp . '/checkout/omens-wp-plugin-seo', WP_PLUGIN_DIR . '/omens-wp-plugin-seo' );
		},
		'expect' => function ( $f, $run, $t ) {
			$t->assert( 1 === count( $f ) && 'omens-wp-plugin-seo:source' === $f[0]['subject'], 'expected the seo source finding, got ' . json_encode( array_column( $f, 'subject' ) ) );
			$steps = $f[0]['fix']['steps'];
			$t->assert( false !== strpos( $f[0]['summary'], 'is a symlink and holds .git' ), 'summary: ' . $f[0]['summary'] );
			$t->assert( false !== strpos( $steps[0], 'remove the link itself first' ) && false !== strpos( $steps[1], 'gh release download --repo omens-agency/omens-wp-plugin-seo' ), 'unlink, then the private download: ' . json_encode( $steps ) );
		},
	),
	// What a release zip unpacks to (docs/ and branding/ included, which some house zips ship) is not source.
	'clean_release_zip_layout' => array(
		'setup'  => function ( $t ) use ( $custom_clean ) {
			$custom_clean( $t );
			foreach ( array( 'omens-wp-plugin-docs.php', 'README.md', 'CHANGELOG.md', 'includes/a.php', 'assets/icon.svg', 'vendor/autoload.php', 'docs/guide.md', 'branding/logo.svg' ) as $rel ) {
				$t->file( 'wp-content/plugins/omens-wp-plugin-docs/' . $rel, 'x' );
			}
		},
		'expect' => array( 'count' => 0 ),
	),
	// A local site runs house plugins from checkouts on purpose (tools/smoke.sh).
	'clean_local_checkout' => array(
		'setup'  => function ( $t ) use ( $custom_clean ) {
			$custom_clean( $t );
			$t->state()['env'] = 'local';
			$t->file( 'wp-content/plugins/omens-wp-plugin-audit/.git/HEAD', 'ref: refs/heads/main' );
			$t->file( 'wp-content/plugins/omens-wp-plugin-audit/tests/run.php', '<?php' );
		},
		'expect' => array( 'count' => 0 ),
	),
	'clean_filtered_set' => array(
		'setup'  => function ( $t ) {
			$t->state()['plugins'] = array( 'omens-wp-plugin-audit/omens-wp-plugin-audit.php' => array( 'Name' => 'Omens Site Audit', 'Version' => '0.1.0' ) );
			$t->state()['options']['active_plugins'] = array( 'omens-wp-plugin-audit/omens-wp-plugin-audit.php' );
			add_filter( 'omens_audit/house_plugins', function () { return array( 'omens-wp-plugin-audit' ); } );
		},
		'expect' => array( 'count' => 0 ),
	),
);
