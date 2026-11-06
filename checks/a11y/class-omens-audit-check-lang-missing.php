<?php
/**
 * a11y.lang-missing: <html> has no lang, or one that does not match the site locale.
 * A template attribute, so route code. WCAG 2.2 3.1.1.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Lang_Missing extends Omens_Audit_Check_Base {

	public function id() {
		return 'a11y.lang-missing';
	}

	public function family() {
		return 'a11y';
	}

	public function title() {
		return 'Page language missing or wrong';
	}

	public function severity() {
		return 'serious';
	}

	public function run( Omens_Audit_Context $ctx ) {
		$findings = array();
		$seen     = array();
		$locale   = strtolower( str_replace( '_', '-', get_locale() ) );
		$primary  = strtok( $locale, '-' );
		foreach ( $ctx->sample() as $url ) {
			$dom = $ctx->dom( $url );
			if ( ! $dom ) {
				continue;
			}
			$html = $dom->getElementsByTagName( 'html' )->item( 0 );
			if ( ! $html ) {
				continue;
			}
			$lang = strtolower( trim( $html->getAttribute( 'lang' ) ) );
			$key  = '' === $lang ? 'missing' : 'mismatch:' . $lang;
			if ( '' !== $lang && strtok( $lang, '-' ) === $primary ) {
				continue;
			}
			if ( isset( $seen[ $key ] ) ) {
				continue; // One finding per distinct defect; the template is the same on every page.
			}
			$seen[ $key ] = true;
			$findings[]   = $this->finding(
				array(
					'subject'   => 'html-lang:' . $key,
					'title'     => '' === $lang ? 'The <html> element has no lang attribute' : sprintf( '<html lang="%s"> does not match the site locale %s', $lang, $locale ),
					'summary'   => '' === $lang ? 'Screen readers fall back to their default voice and pronounce the page in the wrong language.' : sprintf( 'Assistive technology will read the page as "%s" while WordPress says the content is "%s".', $lang, $locale ),
					'detail'    => omens_audit_outer_html( $html, 120 ),
					'url'       => $url,
					'selector'  => 'html',
					'criterion' => 'WCAG 2.2 3.1.1',
					'fix'       => array(
						'route'      => 'code',
						'where'      => 'header.php (or the template that prints <html>)',
						'steps'      => array( 'Print the attribute with WordPress\'s own value: <html <?php language_attributes(); ?>>.', 'If the site really is in another language, set Settings > General > Site Language to match.' ),
						'confidence' => 'high',
					),
					'evidence'  => array( 'get_locale(): ' . get_locale(), 'first seen on ' . $url ),
				)
			);
		}
		return $findings;
	}
}
