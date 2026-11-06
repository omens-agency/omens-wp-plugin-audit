<?php
/**
 * legal.consent-missing: the consent platform's script is absent from the
 * rendered home page.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Consent_Missing extends Omens_Audit_Check_Base {

	public function id() {
		return 'legal.consent-missing';
	}

	public function family() {
		return 'legal';
	}

	public function title() {
		return 'Consent banner script missing';
	}

	public function severity() {
		return 'serious';
	}

	public function applies( Omens_Audit_Context $ctx ) {
		return 'local' === $ctx->environment ? 'local environment' : true;
	}

	/**
	 * Signatures of the consent platform expected on this site.
	 *
	 * @return array{ name: string, patterns: string[] }
	 */
	public static function expected() {
		/**
		 * The consent platform to look for: name plus regular expressions matched
		 * against script src values and inline script bodies. Default CookieYes.
		 *
		 * @param array $expected { name, patterns }.
		 */
		return apply_filters(
			'omens_audit/consent_platform',
			array(
				'name'     => 'CookieYes',
				'patterns' => array( '#cdn-cookieyes\.com/client_data/#i', '#cookieyes\.com/#i', '#\bcookie-law-info\b#i', '#\bcookieyes\b#i' ),
			)
		);
	}

	public function run( Omens_Audit_Context $ctx ) {
		$dom = $ctx->dom( home_url( '/' ) );
		if ( ! $dom ) {
			return array();
		}
		$expected = self::expected();
		$scripts  = omens_audit_scripts( $dom );
		$haystack = implode( "\n", array_merge( $scripts['src'], $scripts['inline'] ) );
		foreach ( (array) $expected['patterns'] as $p ) {
			if ( preg_match( $p, $haystack ) ) {
				return array();
			}
		}
		return array(
			$this->finding(
				array(
					'subject'  => strtolower( (string) $expected['name'] ),
					'title'    => sprintf( '%s script is not on the home page', $expected['name'] ),
					'summary'  => sprintf( 'The rendered home page loads no %s script, so no consent banner shows and tags fire without consent.', $expected['name'] ),
					'url'      => home_url( '/' ),
					'fix'      => array(
						'route'      => 'config',
						'where'      => 'the consent plugin or the theme\'s consent module',
						'steps'      => array(
							sprintf( 'Check the %s plugin is active and its website ID is set (starter builds: OMENS_COOKIEYES_ID in wp-config and library/php/consent.php).', $expected['name'] ),
							'Re-check with the four-state consent capture from the Consent Configuration Standard.',
						),
						'confidence' => 'high',
					),
					'evidence' => array( count( $scripts['src'] ) . ' external script(s) and ' . count( $scripts['inline'] ) . ' inline script(s) checked' ),
				)
			),
		);
	}
}
