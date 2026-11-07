<?php
/**
 * security.debug-display: PHP errors would print to visitors on production.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Debug_Display extends Omens_Audit_Check_Base {

	public function id() {
		return 'security.debug-display';
	}

	public function family() {
		return 'security';
	}

	public function title() {
		return 'Debug output visible on production';
	}

	public function severity() {
		return 'serious';
	}

	public function applies( Omens_Audit_Context $ctx ) {
		return 'production' === $ctx->effective_environment ? true : 'only runs on production (this is ' . $ctx->effective_environment . ' by host name)';
	}

	public function run( Omens_Audit_Context $ctx ) {
		$reasons = array();
		$debug   = defined( 'WP_DEBUG' ) && WP_DEBUG;
		if ( defined( 'WP_DEBUG_DISPLAY' ) ) {
			if ( WP_DEBUG_DISPLAY && $debug ) {
				$reasons[] = 'WP_DEBUG and WP_DEBUG_DISPLAY are both true';
			}
		} elseif ( $debug ) {
			$reasons[] = 'WP_DEBUG is true and WP_DEBUG_DISPLAY is not defined, so WordPress displays errors';
		}
		$display = strtolower( (string) ini_get( 'display_errors' ) );
		/**
		 * The SAPI the ini value belongs to. PHP's CLI forces display_errors on, so
		 * under WP-CLI the ini value says nothing about the web server; the ini
		 * branch only counts for a web SAPI. Filterable for the fixtures.
		 *
		 * @param string $sapi PHP_SAPI.
		 */
		$sapi = (string) apply_filters( 'omens_audit/php_sapi', PHP_SAPI );
		// WordPress itself forces display_errors off when WP_DEBUG_DISPLAY is false, so the ini value only counts otherwise.
		if ( 'cli' !== $sapi && in_array( $display, array( '1', 'on', 'true', 'stdout' ), true ) && ! ( defined( 'WP_DEBUG_DISPLAY' ) && ! WP_DEBUG_DISPLAY ) ) {
			$reasons[] = 'PHP display_errors is ' . $display;
		}
		if ( ! $reasons ) {
			return array();
		}
		return array(
			$this->finding(
				array(
					'subject'  => 'debug-display',
					'title'    => 'PHP errors would be shown to visitors',
					'summary'  => implode( '; ', $reasons ) . '.',
					'detail'   => 'A notice or fatal prints file paths and code to the page, which is information disclosure and breaks layouts.',
					'fix'      => array(
						'route'      => 'config',
						'where'      => 'wp-config.php',
						'steps'      => array(
							"Set define( 'WP_DEBUG', false ); on production, or keep WP_DEBUG with define( 'WP_DEBUG_DISPLAY', false ); and define( 'WP_DEBUG_LOG', true );.",
							'If display_errors comes from php.ini or the host, turn it off there (Pressable: PHP settings in the site panel).',
						),
						'confidence' => 'high',
					),
					'evidence' => $reasons,
				)
			),
		);
	}
}
