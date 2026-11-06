<?php
/**
 * Base class for checks: default version and applies(), the finding() helper
 * that fills the check's own ID, family, severity and version, and the one
 * `info` finding a check returns when it cannot do its job.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Omens_Audit_Check_Base implements Omens_Audit_Check {

	/**
	 * Default severity for this check's main finding; a site can change it
	 * through the `omens_audit/severity` filter, which the engine applies.
	 *
	 * @return string
	 */
	abstract public function severity();

	/**
	 * Check version. Bump when the logic changes in a way that changes findings.
	 *
	 * @return string
	 */
	public function version() {
		return '1.0.0';
	}

	/**
	 * Default: applies everywhere.
	 *
	 * @param Omens_Audit_Context $ctx Run context.
	 * @return true|string
	 */
	public function applies( Omens_Audit_Context $ctx ) {
		return true;
	}

	/**
	 * Build a finding for this check.
	 *
	 * @param array $args See omens_audit_finding(). `check`, `family`,
	 *                    `severity` and `check_version` default to the check's own.
	 * @return array
	 */
	protected function finding( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'check'         => $this->id(),
				'family'        => $this->family(),
				'severity'      => $this->severity(),
				'check_version' => $this->version(),
			)
		);
		return omens_audit_finding( $args );
	}

	/**
	 * The one `info` finding a check returns when it cannot do its job.
	 *
	 * @param string $reason Why.
	 * @return array
	 */
	protected function cannot_run( $reason ) {
		return omens_audit_finding(
			array(
				'check'         => $this->id(),
				'subject'       => 'cannot-run',
				'family'        => $this->family(),
				'severity'      => 'info',
				'title'         => sprintf( '%s could not run', $this->id() ),
				'summary'       => $reason,
				'fix'           => array(
					'route'      => 'human',
					'where'      => 'the audit itself',
					'steps'      => array( $reason ),
					'confidence' => 'high',
				),
				'check_version' => $this->version(),
			)
		);
	}
}
