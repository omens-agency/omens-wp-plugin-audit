<?php
/**
 * security.file-edit-enabled: the theme and plugin file editors are reachable
 * from wp-admin because DISALLOW_FILE_EDIT is not defined as true.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_File_Edit_Enabled extends Omens_Audit_Check_Base {

	public function id() {
		return 'security.file-edit-enabled';
	}

	public function family() {
		return 'security';
	}

	public function title() {
		return 'File editors reachable (DISALLOW_FILE_EDIT not set)';
	}

	public function severity() {
		return 'serious';
	}

	public function run( Omens_Audit_Context $ctx ) {
		if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
			return array();
		}
		$steps = array(
			"Add define( 'DISALLOW_FILE_EDIT', true ); to wp-config.php above the \"stop editing\" line.",
		);
		if ( 'custom' === $ctx->build_type ) {
			$steps[] = 'Or, on a starter build, confirm library/php/security.php is loading: it defines the constant as a fallback.';
		} else {
			$steps[] = 'Or enable the omens-wp-plugin-security filter omens/security/disallow_file_edit on this site.';
		}
		return array(
			$this->finding(
				array(
					'subject'  => 'DISALLOW_FILE_EDIT',
					'title'    => 'Theme and plugin file editors are reachable in wp-admin',
					'summary'  => 'DISALLOW_FILE_EDIT is ' . ( defined( 'DISALLOW_FILE_EDIT' ) ? 'defined but false' : 'not defined' ) . ', so any administrator can edit PHP files from the browser.',
					'detail'   => 'An attacker with an administrator session gets code execution through Appearance > Theme File Editor. The Security Hardening Standard requires the constant on every site.',
					'fix'      => array(
						'route'      => 'config',
						'where'      => 'wp-config.php',
						'steps'      => $steps,
						'confidence' => 'high',
					),
					'evidence' => array( 'defined(DISALLOW_FILE_EDIT): ' . ( defined( 'DISALLOW_FILE_EDIT' ) ? ( DISALLOW_FILE_EDIT ? 'true' : 'false' ) : 'no' ) ),
				)
			),
		);
	}
}
