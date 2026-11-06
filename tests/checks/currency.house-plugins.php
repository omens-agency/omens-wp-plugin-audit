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
	'clean_filtered_set' => array(
		'setup'  => function ( $t ) {
			$t->state()['plugins'] = array( 'omens-wp-plugin-audit/omens-wp-plugin-audit.php' => array( 'Name' => 'Omens Site Audit', 'Version' => '0.1.0' ) );
			$t->state()['options']['active_plugins'] = array( 'omens-wp-plugin-audit/omens-wp-plugin-audit.php' );
			add_filter( 'omens_audit/house_plugins', function () { return array( 'omens-wp-plugin-audit' ); } );
		},
		'expect' => array( 'count' => 0 ),
	),
);
