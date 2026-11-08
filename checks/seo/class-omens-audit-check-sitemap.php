<?php
/**
 * seo.sitemap: the sitemap answers with entries, and none of the sampled
 * entries it lists are noindexed.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Sitemap extends Omens_Audit_Check_Base {

	public function id() {
		return 'seo.sitemap';
	}

	public function family() {
		return 'seo';
	}

	public function title() {
		return 'XML sitemap missing, empty or listing noindexed pages';
	}

	public function severity() {
		return 'serious';
	}

	public function applies( Omens_Audit_Context $ctx ) {
		if ( 'production' !== $ctx->effective_environment ) {
			return 'only production has a sitemap worth checking';
		}
		return true;
	}

	public function run( Omens_Audit_Context $ctx ) {
		$seo = omens_audit_seo_plugin();
		// Where the sitemap is: robots.txt says first (every SEO plugin writes a Sitemap: line there),
		// then the detected plugin's default, then the usual suspects. Slim SEO serves /sitemap.xml with
		// core sitemaps off, and a site whose detection is wrong still gets found this way.
		$tried = array();
		$xml   = null;
		$url   = '';
		foreach ( $this->candidates( $ctx, $seo ) as $candidate ) {
			$tried[] = $candidate;
			$body    = $ctx->fetch( $candidate );
			if ( null !== $body && false !== stripos( $body, '<urlset' ) || null !== $body && false !== stripos( $body, '<sitemapindex' ) ) {
				$xml = $body;
				$url = $candidate;
				break;
			}
		}
		if ( null === $xml ) {
			$reasons = array();
			foreach ( $tried as $t ) {
				$reasons[] = wp_parse_url( $t, PHP_URL_PATH ) . ': ' . ( $ctx->failed_fetches()[ $t ] ?? 'not a sitemap' );
			}
			return array( $this->sitemap_finding( 'unreachable', home_url( $seo['sitemap'] ), sprintf( 'No sitemap answered at %s.', implode( ', ', array_map( static function ( $t ) { return wp_parse_url( $t, PHP_URL_PATH ); }, $tried ) ) ), array( 'Open the URLs in a browser. If all 404, enable the sitemap in ' . $seo['name'] . ' (or core sitemaps) and flush permalinks; if it lives elsewhere, add a Sitemap: line to robots.txt.' ), $reasons ) );
		}
		$locs = $this->locs( $xml );
		if ( ! $locs ) {
			return array( $this->sitemap_finding( 'empty', $url, 'The sitemap answered but lists no URLs.', array( 'Check the sitemap settings in ' . $seo['name'] . ': at least pages and posts should be included.' ), array( omens_audit_trim( $xml, 200 ) ) ) );
		}
		// A sitemap index: follow the first child sitemaps for real entries (sequentially, bounded).
		$entries = array();
		if ( false !== stripos( $xml, '<sitemapindex' ) ) {
			foreach ( array_slice( $locs, 0, 5 ) as $child ) {
				$child_xml = $ctx->is_own_url( $child ) ? $ctx->fetch( $child ) : null;
				if ( null !== $child_xml ) {
					$entries = array_merge( $entries, $this->locs( $child_xml ) );
				}
			}
			if ( ! $entries ) {
				return array( $this->sitemap_finding( 'empty', $url, 'The sitemap index lists child sitemaps but they contain no URLs or could not be fetched.', array( 'Open each child sitemap listed at ' . $seo['sitemap'] . ' and check it returns 200 with entries.' ), array_slice( $locs, 0, 5 ) ) );
			}
		} else {
			$entries = $locs;
		}

		// Noindexed pages in the sitemap: check only entries we already fetched.
		$findings = array();
		$sample   = array_map( 'untrailingslashit', $ctx->sample() );
		foreach ( $entries as $entry ) {
			if ( ! in_array( untrailingslashit( $entry ), $sample, true ) ) {
				continue;
			}
			$dom = $ctx->dom( trailingslashit( $entry ) === $entry ? $entry : $entry );
			if ( ! $dom ) {
				$dom = $ctx->dom( untrailingslashit( $entry ) . '/' );
			}
			if ( $dom && in_array( 'noindex', omens_audit_robots( $dom ), true ) ) {
				$findings[] = $this->sitemap_finding( 'noindex:' . wp_parse_url( $entry, PHP_URL_PATH ), $entry, sprintf( '%s is in the sitemap and carries a noindex robots meta; search engines treat that as a contradiction.', $entry ), array( 'Either index the page (remove noindex) or exclude it from the sitemap in ' . $seo['name'] . '.' ), array( 'listed in ' . $url ) );
			}
		}
		return $findings;
	}

	/**
	 * Sitemap URLs to try, in order: robots.txt Sitemap: lines on this host,
	 * the detected plugin's default, then the common paths.
	 *
	 * @param Omens_Audit_Context $ctx Context.
	 * @param array               $seo Detected SEO plugin.
	 * @return string[]
	 */
	private function candidates( Omens_Audit_Context $ctx, array $seo ) {
		$list   = array();
		$robots = $ctx->fetch( home_url( '/robots.txt' ) );
		if ( null !== $robots && preg_match_all( '/^\s*sitemap\s*:\s*(\S+)/im', $robots, $m ) ) {
			foreach ( $m[1] as $candidate ) {
				$candidate = trim( $candidate );
				if ( $ctx->is_own_url( $candidate ) && preg_match( '/\.xml(\?|$)/i', $candidate ) ) {
					$list[] = $candidate;
				}
			}
		}
		foreach ( array( $seo['sitemap'], '/sitemap_index.xml', '/sitemap.xml', '/wp-sitemap.xml' ) as $path ) {
			$list[] = home_url( $path );
		}
		return array_values( array_unique( $list ) );
	}

	/**
	 * <loc> values from a sitemap or index. AIOSEO wraps them in CDATA.
	 *
	 * @param string $xml XML.
	 * @return string[]
	 */
	private function locs( $xml ) {
		if ( ! preg_match_all( '#<loc>\s*(?:<!\[CDATA\[)?\s*([^<\s\]]+)\s*(?:\]\]>)?\s*</loc>#i', (string) $xml, $m ) ) {
			return array();
		}
		return array_values( array_unique( array_map( 'html_entity_decode', $m[1] ) ) );
	}

	/**
	 * Build a sitemap finding.
	 *
	 * @param string   $subject  Subject.
	 * @param string   $url      URL.
	 * @param string   $summary  Summary.
	 * @param string[] $steps    Steps.
	 * @param string[] $evidence Evidence.
	 * @return array
	 */
	private function sitemap_finding( $subject, $url, $summary, array $steps, array $evidence ) {
		return $this->finding(
			array(
				'subject'  => $subject,
				'title'    => 'noindex' === strtok( $subject, ':' ) ? 'Sitemap lists a noindexed page' : 'XML sitemap problem: ' . $subject,
				'summary'  => $summary,
				'url'      => $url,
				'fix'      => array(
					'route'      => 'config',
					'where'      => 'the SEO plugin\'s sitemap settings',
					'steps'      => $steps,
					'confidence' => 'high',
				),
				'evidence' => $evidence,
			)
		);
	}
}
