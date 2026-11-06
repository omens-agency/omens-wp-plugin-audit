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
		'Omens_Audit_Check_Php_In_Uploads'      => 'security/class-omens-audit-check-php-in-uploads.php',
		'Omens_Audit_Check_Admins_Without_2fa'  => 'security/class-omens-audit-check-admins-without-2fa.php',
		'Omens_Audit_Check_File_Edit_Enabled'   => 'security/class-omens-audit-check-file-edit-enabled.php',
		'Omens_Audit_Check_Debug_Display'       => 'security/class-omens-audit-check-debug-display.php',
		'Omens_Audit_Check_Currency_Plugins'    => 'currency/class-omens-audit-check-currency-plugins.php',
		'Omens_Audit_Check_House_Plugins'       => 'currency/class-omens-audit-check-house-plugins.php',
		'Omens_Audit_Check_Indexing_Blocked'    => 'seo/class-omens-audit-check-indexing-blocked.php',
		'Omens_Audit_Check_Sitemap'             => 'seo/class-omens-audit-check-sitemap.php',
		'Omens_Audit_Check_Description_Missing' => 'seo/class-omens-audit-check-description-missing.php',
		'Omens_Audit_Check_Lang_Missing'        => 'a11y/class-omens-audit-check-lang-missing.php',
		'Omens_Audit_Check_Img_Alt_Missing'     => 'a11y/class-omens-audit-check-img-alt-missing.php',
		'Omens_Audit_Check_Link_Empty'          => 'a11y/class-omens-audit-check-link-empty.php',
		'Omens_Audit_Check_Img_Alt_Filename'    => 'a11y/class-omens-audit-check-img-alt-filename.php',
		'Omens_Audit_Check_Placeholder_Live'    => 'legal/class-omens-audit-check-placeholder-live.php',
		'Omens_Audit_Check_Page_Missing'        => 'legal/class-omens-audit-check-page-missing.php',
		'Omens_Audit_Check_Consent_Missing'     => 'legal/class-omens-audit-check-consent-missing.php',
		'Omens_Audit_Check_Tracker_Drift'       => 'legal/class-omens-audit-check-tracker-drift.php',
		'Omens_Audit_Check_Review_Overdue'      => 'legal/class-omens-audit-check-review-overdue.php',
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
