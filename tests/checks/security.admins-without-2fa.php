<?php
// phpcs:ignoreFile
return array(
	'fires' => array(
		'setup'  => function ( $t ) {
			$t->state()['options']['active_plugins'] = array( 'wp-2fa/wp-2fa.php' );
			omens_test_add_user( array( 'user_login' => 'jake', 'meta' => array( 'wp_2fa_enabled_methods' => array( 'totp' ) ) ) );
			omens_test_add_user( array( 'user_login' => 'contractor' ) );
			omens_test_add_user( array( 'user_login' => 'editor', 'roles' => array( 'editor' ) ) );
		},
		'expect' => array( 'count' => 1, 'severity' => 'serious', 'route' => 'human', 'title_contains' => 'contractor' ),
	),
	'fires_no_plugin' => array(
		'setup'  => function ( $t ) { omens_test_add_user( array( 'user_login' => 'a' ) ); omens_test_add_user( array( 'user_login' => 'b' ) ); },
		'expect' => array( 'count' => 1, 'subject' => 'no-2fa-plugin', 'route' => 'config', 'summary_contains' => '2 administrator' ),
	),
	'fires_wordfence_table' => array(
		'setup'  => function ( $t ) {
			$t->state()['options']['active_plugins'] = array( 'wordfence-login-security/wordfence-login-security.php' );
			$t->state()['db_tables'] = array( 'wp_wfls_secrets' );
			$u1 = omens_test_add_user( array( 'user_login' => 'enrolled' ) );
			omens_test_add_user( array( 'user_login' => 'bare' ) );
			$t->state()['db_rows']['wp_wfls_secrets'] = array( array( 'user_id' => $u1 ) );
		},
		'expect' => array( 'count' => 1, 'title_contains' => 'bare' ),
	),
	'clean' => array(
		'setup'  => function ( $t ) {
			$t->state()['options']['active_plugins'] = array( 'two-factor/two-factor.php' );
			omens_test_add_user( array( 'user_login' => 'jake', 'meta' => array( '_two_factor_enabled_providers' => array( 'Two_Factor_Totp' ) ) ) );
			omens_test_add_user( array( 'user_login' => 'sso', 'ID' => 77 ) );
			add_filter( 'omens_audit/user_has_2fa', function ( $has, $id ) { return 77 === $id ? true : $has; }, 10, 2 );
		},
		'expect' => array( 'count' => 0 ),
	),
);
