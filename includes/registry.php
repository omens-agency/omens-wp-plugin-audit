<?php
/**
 * The check registry. Checks are classes in checks/, listed here, and a theme
 * can add or remove checks through the `omens_audit/checks` filter without
 * forking the plugin.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The registered checks, keyed by ID.
 *
 * @return array<string, Omens_Audit_Check>
 */
function omens_audit_checks() {
	static $checks = null;
	if ( null !== $checks ) {
		return $checks;
	}
	$classes = array(
		'Omens_Audit_Check_File_Edit_Enabled' => 'security/class-omens-audit-check-file-edit-enabled.php',
	);
	$list    = array();
	foreach ( $classes as $class => $file ) {
		if ( ! class_exists( $class ) ) {
			require_once OMENS_AUDIT_DIR . 'checks/' . $file;
		}
		$check                = new $class();
		$list[ $check->id() ] = $check;
	}

	/**
	 * Add, replace or remove checks. Keys are check IDs; values implement
	 * Omens_Audit_Check. Unset a key to disable a check on this site.
	 *
	 * @param array<string, Omens_Audit_Check> $list Checks by ID.
	 */
	$list   = apply_filters( 'omens_audit/checks', $list );
	$checks = array();
	foreach ( (array) $list as $id => $check ) {
		if ( $check instanceof Omens_Audit_Check ) {
			$checks[ $check->id() ] = $check;
		}
	}
	return $checks;
}
