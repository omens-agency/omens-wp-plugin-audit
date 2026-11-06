<?php
/**
 * legal.review-overdue: the privacy policy or accessibility statement has not
 * been touched in 12 months (Legal Pages brief 05's annual review).
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Review_Overdue extends Omens_Audit_Check_Base {

	public function id() {
		return 'legal.review-overdue';
	}

	public function family() {
		return 'legal';
	}

	public function title() {
		return 'Legal page overdue for its annual review';
	}

	public function severity() {
		return 'moderate';
	}

	public function run( Omens_Audit_Context $ctx ) {
		/**
		 * Review interval in days. Default 365.
		 *
		 * @param int $days Days.
		 */
		$days     = max( 30, (int) apply_filters( 'omens_audit/legal_review_days', 365 ) );
		$findings = array();
		$pages    = omens_audit_legal_pages();
		foreach ( array(
			'privacy'       => 'privacy policy',
			'accessibility' => 'accessibility statement',
		) as $role => $label ) {
			$id = $pages[ $role ] ?? 0;
			if ( ! $id || 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			$post     = get_post( $id );
			$stamp    = ! empty( $post->post_modified_gmt ) && '0000-00-00 00:00:00' !== $post->post_modified_gmt ? $post->post_modified_gmt : $post->post_modified;
			$modified = strtotime( (string) $stamp . ' UTC' );
			if ( ! $modified ) {
				continue;
			}
			$age = (int) floor( ( time() - $modified ) / DAY_IN_SECONDS );
			if ( $age <= $days ) {
				continue;
			}
			$findings[] = $this->finding(
				array(
					'subject'  => $role,
					'title'    => sprintf( 'The %s was last changed %d days ago', $label, $age ),
					'summary'  => sprintf( '"%s" was last modified on %s; the annual review is %d days overdue.', get_the_title( $id ), gmdate( 'Y-m-d', $modified ), $age - $days ),
					'url'      => get_permalink( $id ),
					'fix'      => array(
						'route'      => 'human',
						'where'      => sprintf( 'the %s page (%s)', $label, omens_audit_edit_link( $id ) ),
						'steps'      => array(
							'privacy' === $role ? 'Re-run web-legal-pages against the current tracker load and republish; the cookie table has almost certainly drifted.' : 'Re-run the a11y harness and the manual pass, then update the limitations table and the date on the statement.',
							'Even if nothing changed, update the "last reviewed" date so the page says when it was last checked.',
						),
						'confidence' => 'high',
					),
					'evidence' => array( 'post_modified_gmt ' . gmdate( 'c', $modified ) ),
				)
			);
		}
		return $findings;
	}
}
