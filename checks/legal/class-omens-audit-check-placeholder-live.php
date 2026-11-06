<?php
/**
 * legal.placeholder-live: a published legal page still carries a template
 * placeholder ([ INSERT …], {{…}}, [[BLOCK:…]]).
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Placeholder_Live extends Omens_Audit_Check_Base {

	public function id() {
		return 'legal.placeholder-live';
	}

	public function family() {
		return 'legal';
	}

	public function title() {
		return 'Template placeholder on a live legal page';
	}

	public function severity() {
		return 'critical';
	}

	public function run( Omens_Audit_Context $ctx ) {
		$findings = array();
		foreach ( omens_audit_legal_pages() as $role => $id ) {
			if ( ! $id || 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			$post    = get_post( $id );
			$content = (string) $post->post_content;
			if ( ! preg_match_all( '/\[\s*INSERT[^\]]{0,80}\]|\{\{[^}]{1,60}\}\}|\[\[BLOCK[^\]]{0,60}\]\]/i', $content, $m ) ) {
				continue;
			}
			$hits       = array_values( array_unique( $m[0] ) );
			$findings[] = $this->finding(
				array(
					'subject'  => $role,
					'title'    => sprintf( 'The %s page is live with %d unfilled placeholder%s', $role, count( $hits ), 1 === count( $hits ) ? '' : 's' ),
					'summary'  => sprintf( '"%s" is published and still contains %s.', get_the_title( $id ), omens_audit_trim( implode( ', ', array_slice( $hits, 0, 5 ) ), 160 ) ),
					'detail'   => implode( ' | ', array_slice( $hits, 0, 10 ) ),
					'url'      => get_permalink( $id ),
					'fix'      => array(
						'route'      => 'content',
						'where'      => sprintf( 'the %s page (%s)', $role, omens_audit_edit_link( $id ) ),
						'steps'      => array( 'Fill or remove every placeholder; the legal.py check in web-legal-pages must exit clean before the page is public.', 'If the fact is still unknown, unpublish the page until it is.' ),
						'confidence' => 'high',
					),
					'evidence' => array( count( $hits ) . ' placeholder pattern(s) in post_content' ),
				)
			);
		}
		return $findings;
	}
}
