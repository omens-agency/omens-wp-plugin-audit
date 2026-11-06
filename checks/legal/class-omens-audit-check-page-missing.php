<?php
/**
 * legal.page-missing: one of the three legal pages is missing, unpublished,
 * or not linked from the rendered footer.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Page_Missing extends Omens_Audit_Check_Base {

	public function id() {
		return 'legal.page-missing';
	}

	public function family() {
		return 'legal';
	}

	public function title() {
		return 'Legal page missing or not linked from the footer';
	}

	public function severity() {
		return 'serious';
	}

	public function run( Omens_Audit_Context $ctx ) {
		$findings   = array();
		$labels     = array(
			'privacy'       => 'privacy policy',
			'terms'         => 'terms of use',
			'accessibility' => 'accessibility statement',
		);
		$dom        = $ctx->dom( home_url( '/' ) );
		$footer     = $dom ? $this->footer_hrefs( $dom ) : null;
		$legacy     = 'purchased' === $ctx->build_type;
		$severity   = $legacy ? 'moderate' : $this->severity();
		$adopt_step = 'Adopt the templates in Library/Legal Pages (web-legal-pages writes all three from the site\'s real tracker load).';

		foreach ( omens_audit_legal_pages() as $role => $id ) {
			$label = $labels[ $role ];
			if ( ! $id ) {
				$findings[] = $this->page_finding( $role, 'missing', $severity, sprintf( 'No %s page', $label ), sprintf( 'The site has no published %s%s.', $label, 'privacy' === $role ? ' (Settings > Privacy has no page selected)' : ' at the slugs web-legal-pages publishes' ), array( $adopt_step, 'privacy' === $role ? 'Then select the page under Settings > Privacy.' : 'If the page exists under another slug, name it with the omens_audit/legal_slugs filter.' ) );
				continue;
			}
			if ( 'publish' !== get_post_status( $id ) ) {
				$findings[] = $this->page_finding( $role, 'unpublished', $severity, sprintf( 'The %s page is not published', $label ), sprintf( '"%s" exists with status %s.', get_the_title( $id ), get_post_status( $id ) ), array( 'Publish it, or finish it first if it still has placeholders.' ), get_permalink( $id ) );
				continue;
			}
			if ( null === $footer ) {
				continue; // Home could not be fetched; run.fetch-failed covers that.
			}
			$permalink = get_permalink( $id );
			$path      = untrailingslashit( (string) wp_parse_url( $permalink, PHP_URL_PATH ) );
			$linked    = false;
			foreach ( $footer as $href ) {
				$hp = untrailingslashit( (string) wp_parse_url( $href, PHP_URL_PATH ) );
				if ( '' !== $hp && $hp === $path ) {
					$linked = true;
					break;
				}
			}
			if ( ! $linked ) {
				$findings[] = $this->page_finding( $role, 'unlinked', $severity, sprintf( 'The %s is not linked from the footer', $label ), sprintf( '"%s" is published at %s but no link in the rendered footer points to it; visitors and regulators expect it on every page.', get_the_title( $id ), $path ), array( 'Add it to the footer menu (Appearance > Menus) or the footer template\'s legal links.' ), $permalink );
			}
		}
		return $findings;
	}

	/**
	 * hrefs inside <footer>, or inside the last fifth of the body when there is no footer element.
	 *
	 * @param DOMDocument $dom Document.
	 * @return string[]
	 */
	private function footer_hrefs( DOMDocument $dom ) {
		$hrefs   = array();
		$footers = $dom->getElementsByTagName( 'footer' );
		if ( $footers->length ) {
			foreach ( $footers as $footer ) {
				foreach ( $footer->getElementsByTagName( 'a' ) as $a ) {
					$hrefs[] = $a->getAttribute( 'href' );
				}
			}
			return $hrefs;
		}
		$links = $dom->getElementsByTagName( 'a' );
		$n     = $links->length;
		for ( $i = (int) floor( $n * 0.8 ); $i < $n; $i++ ) {
			$hrefs[] = $links->item( $i )->getAttribute( 'href' );
		}
		return $hrefs;
	}

	/**
	 * Build a legal-page finding.
	 *
	 * @param string   $role     Role.
	 * @param string   $state    missing|unpublished|unlinked.
	 * @param string   $severity Severity.
	 * @param string   $title    Title.
	 * @param string   $summary  Summary.
	 * @param string[] $steps    Steps.
	 * @param string   $url      URL.
	 * @return array
	 */
	private function page_finding( $role, $state, $severity, $title, $summary, array $steps, $url = '' ) {
		return $this->finding(
			array(
				'subject'  => $role . ':' . $state,
				'severity' => $severity,
				'title'    => $title,
				'summary'  => $summary,
				'url'      => $url,
				'fix'      => array(
					'route'      => 'content',
					'where'      => 'unlinked' === $state ? 'the footer menu' : 'Pages',
					'steps'      => $steps,
					'confidence' => 'high',
				),
				'evidence' => array( 'state: ' . $state ),
			)
		);
	}
}
