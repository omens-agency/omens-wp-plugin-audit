<?php
/**
 * The check interface and a base class with the helpers every check wants.
 *
 * Checks are pure reads. A check that cannot run returns one `info` finding
 * saying so (see Omens_Audit_Check_Base::cannot_run()); it never fails
 * silently. A check that throws is caught by the run engine and reported as
 * `run.check-failed`, so one bad check cannot sink the run.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Omens_Audit_Check {
	/**
	 * Stable ID, e.g. `security.file-edit-enabled`. Goes into every fingerprint;
	 * renaming it reopens every finding the check has ever made.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Family: security|currency|seo|a11y|legal.
	 *
	 * @return string
	 */
	public function family();

	/**
	 * Human title for the `checks` listing and the admin screen.
	 *
	 * @return string
	 */
	public function title();

	/**
	 * Version of the check's logic, recorded on each finding.
	 *
	 * @return string
	 */
	public function version();

	/**
	 * Whether the check can run here. Return true, or a string saying why not
	 * (a missing dependency, for example). The engine turns a string into one
	 * `info` finding and skips run().
	 *
	 * @param Omens_Audit_Context $ctx Run context.
	 * @return true|string
	 */
	public function applies( Omens_Audit_Context $ctx );

	/**
	 * Run the check.
	 *
	 * @param Omens_Audit_Context $ctx Run context.
	 * @return array[] Findings built with $this->finding().
	 */
	public function run( Omens_Audit_Context $ctx );
}
