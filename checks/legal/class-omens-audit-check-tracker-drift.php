<?php
/**
 * legal.tracker-drift: a known tracker in the rendered sample that the
 * privacy policy does not name. Signatures are the web-legal-pages set,
 * copied to data/trackers.json.
 *
 * "hosting analytics" (Jetpack Stats and the Pressable visit counter, which
 * the platform injects on every Pressable site) is moderate, not serious,
 * and its fix is the HOSTING_ANALYTICS block of the Legal Pages privacy
 * template rather than a cookie-table row (decision 2026-11-07).
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Tracker_Drift extends Omens_Audit_Check_Base {

	public function id() {
		return 'legal.tracker-drift';
	}

	public function family() {
		return 'legal';
	}

	public function title() {
		return 'Tracker on the site that the privacy policy does not name';
	}

	public function severity() {
		return 'serious';
	}

	/**
	 * Tracker signatures.
	 *
	 * @return array[] { match: string[], tool, purpose, category, provider }
	 */
	public static function signatures() {
		static $sigs = null;
		if ( null === $sigs ) {
			$json = json_decode( (string) file_get_contents( OMENS_AUDIT_DIR . 'data/trackers.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$sigs = is_array( $json ) ? $json : array();
		}
		/**
		 * Add or remove tracker signatures (same shape as data/trackers.json).
		 *
		 * @param array[] $sigs Signatures.
		 */
		return (array) apply_filters( 'omens_audit/tracker_signatures', $sigs );
	}

	/**
	 * Signature categories that a privacy policy must disclose, and the
	 * severity when one is missing. "strictly necessary" (the consent
	 * platform itself, CAPTCHA, the CDN, payment processors) is never drift.
	 *
	 * @return array<string, string> category => severity.
	 */
	public static function disclosure_categories() {
		/**
		 * Which tracker categories count as drift when the policy omits them.
		 *
		 * @param array<string, string> $cats category => severity.
		 */
		return (array) apply_filters(
			'omens_audit/tracker_categories',
			array(
				'analytics'                         => 'serious',
				'advertising'                       => 'serious',
				'advertising and session recording' => 'serious',
				'marketing'                         => 'serious',
				'session recording'                 => 'serious',
				'chat'                              => 'serious',
				'accessibility'                     => 'moderate',
				'embedded media'                    => 'moderate',
				'hosting analytics'                 => 'moderate',
			)
		);
	}

	public function applies( Omens_Audit_Context $ctx ) {
		$pages = omens_audit_legal_pages();
		if ( empty( $pages['privacy'] ) || 'publish' !== get_post_status( $pages['privacy'] ) ) {
			return 'no published privacy policy to compare against (legal.page-missing reports that)';
		}
		return true;
	}

	public function run( Omens_Audit_Context $ctx ) {
		$privacy = get_post( omens_audit_legal_pages()['privacy'] );
		$policy  = strtolower( wp_strip_all_tags( (string) $privacy->post_content ) );
		$cats    = self::disclosure_categories();
		$found   = array(); // tool => [ first url, matched string ].
		foreach ( $ctx->sample() as $url ) {
			$dom = $ctx->dom( $url );
			if ( ! $dom ) {
				continue;
			}
			$scripts  = omens_audit_scripts( $dom );
			$haystack = strtolower( implode( "\n", array_merge( $scripts['src'], $scripts['inline'] ) ) );
			foreach ( self::signatures() as $sig ) {
				if ( isset( $found[ $sig['tool'] ] ) || ! isset( $cats[ $sig['category'] ?? '' ] ) ) {
					continue;
				}
				foreach ( (array) $sig['match'] as $needle ) {
					if ( '' !== $needle && false !== strpos( $haystack, strtolower( $needle ) ) ) {
						$found[ $sig['tool'] ] = array( $url, $needle, $sig );
						break;
					}
				}
			}
		}

		$findings = array();
		foreach ( $found as $tool => $hit ) {
			list( $url, $needle, $sig ) = $hit;
			if ( $this->policy_names( $policy, $sig ) ) {
				continue;
			}
			$hosting = 'hosting analytics' === ( $sig['category'] ?? '' );
			if ( $hosting ) {
				$summary = sprintf( '%s (%s) is injected by the hosting platform on %s and the privacy policy names neither the tool nor its provider.', $tool, $sig['provider'] ?? 'unknown provider', wp_parse_url( $url, PHP_URL_PATH ) );
				$where   = 'the privacy policy, section 5: the HOSTING_ANALYTICS block of the Legal Pages privacy template';
				$steps   = array(
					sprintf( 'Add the HOSTING_ANALYTICS block from Library/Legal Pages/templates/Privacy Policy.md (section 5) to the policy; it names %s and the other platform counter with what each collects.', $tool ),
					'Or, for the Pressable visit counter only: ask Pressable Customer Success to switch the account to Pressable Insights Page Views, which counts server-side and injects no script; then delete that bullet from the block.',
				);
			} else {
				$summary = sprintf( '%s (%s, %s) was found on %s and the privacy policy names neither the tool nor its provider.', $tool, $sig['category'] ?? 'tracker', $sig['provider'] ?? 'unknown provider', wp_parse_url( $url, PHP_URL_PATH ) );
				$where   = 'the privacy policy\'s cookie and recipient tables';
				$steps   = array(
					sprintf( 'Either add %s (%s) to the cookie table with its purpose: %s.', $tool, $sig['provider'] ?? '', $sig['purpose'] ?? '' ),
					'Or remove the tag if nobody uses it. Then re-run web-legal-pages so the table is generated from the real tracker load.',
				);
			}
			$findings[] = $this->finding(
				array(
					'subject'  => sanitize_title( $tool ),
					'severity' => $cats[ $sig['category'] ],
					'title'    => sprintf( 'Privacy policy does not name %s', $tool ),
					'summary'  => $summary,
					'detail'   => 'matched "' . $needle . '"',
					'url'      => $url,
					'fix'      => array(
						'route'      => 'content',
						'where'      => $where,
						'steps'      => $steps,
						'confidence' => 'high',
					),
					'evidence' => array( 'signature "' . $needle . '" on ' . $url, 'policy searched for: ' . implode( ', ', $this->names( $sig ) ) ),
				)
			);
		}
		return $findings;
	}

	/**
	 * Names that would count as the policy mentioning this tracker.
	 *
	 * @param array $sig Signature.
	 * @return string[]
	 */
	private function names( array $sig ) {
		$names    = array( strtolower( (string) $sig['tool'] ) );
		$provider = strtolower( (string) ( $sig['provider'] ?? '' ) );
		if ( '' !== $provider ) {
			$names[] = $provider;
			$short   = trim( preg_replace( '/\b(llc|inc\.?|ltd\.?|limited|corporation|corp\.?|gmbh|s\.?a\.?|b\.?v\.?|co\.?)\b/i', '', $provider ) );
			if ( '' !== $short && $short !== $provider ) {
				$names[] = trim( $short, ' ,.' );
			}
		}
		/**
		 * Extra names that count as naming a tracker in the policy.
		 *
		 * @param string[] $names Lowercase names.
		 * @param array    $sig   Signature.
		 */
		return array_values( array_unique( array_filter( (array) apply_filters( 'omens_audit/tracker_names', $names, $sig ) ) ) );
	}

	/**
	 * Whether the policy text mentions the tool or its provider.
	 *
	 * @param string $policy Lowercase policy text.
	 * @param array  $sig    Signature.
	 * @return bool
	 */
	private function policy_names( $policy, array $sig ) {
		foreach ( $this->names( $sig ) as $name ) {
			if ( strlen( $name ) >= 3 && false !== strpos( $policy, $name ) ) {
				return true;
			}
		}
		return false;
	}
}
