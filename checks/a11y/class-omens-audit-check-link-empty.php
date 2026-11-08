<?php
/**
 * a11y.link-empty: links and buttons with no accessible name. Routed by href
 * through the content-versus-code rule. WCAG 2.2 2.4.4 and 4.1.2.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Link_Empty extends Omens_Audit_Check_Base {

	const CAP = 40;

	public function id() {
		return 'a11y.link-empty';
	}

	public function family() {
		return 'a11y';
	}

	public function title() {
		return 'Links and buttons with no accessible name';
	}

	public function severity() {
		return 'serious';
	}

	public function run( Omens_Audit_Context $ctx ) {
		$findings = array();
		$seen     = array();
		foreach ( $ctx->sample() as $url ) {
			$dom = $ctx->dom( $url );
			if ( ! $dom ) {
				continue;
			}
			$post_id = omens_audit_url_post_id( $url );
			foreach ( omens_audit_elements( $dom, array( 'a', 'button' ) ) as $el ) {
				if ( count( $findings ) >= self::CAP ) {
					break 2;
				}
				$tag = strtolower( $el->tagName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				if ( 'a' === $tag && ! $el->hasAttribute( 'href' ) ) {
					continue; // An anchor without href is not a link.
				}
				if ( omens_audit_is_hidden( $el ) ) {
					continue;
				}
				if ( $el->hasAttribute( 'data-wp-bind--aria-label' ) ) {
					continue; // WordPress core binds the name at runtime (the image lightbox trigger button).
				}
				if ( '' !== omens_audit_accessible_name( $el ) ) {
					continue;
				}
				$href = 'a' === $tag ? $el->getAttribute( 'href' ) : '';
				$key  = $tag . '|' . ( '' !== $href ? $href : omens_audit_selector( $el ) );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;

				$route = omens_audit_route( $post_id, array( $href ) );
				if ( 'button' === $tag ) {
					// A button has no href. Its id or data attributes are the only keys, and a
					// button with neither has no usable key: rule 4, low confidence.
					$route = omens_audit_route( $post_id, array( $el->getAttribute( 'id' ), $el->getAttribute( 'data-action' ), $el->getAttribute( 'name' ) ) );
				}
				$findings[] = $this->finding(
					array(
						'subject'   => $key,
						'title'     => sprintf( '%s with no accessible name on %s', 'a' === $tag ? 'Link' : 'Button', wp_parse_url( $url, PHP_URL_PATH ) ),
						'summary'   => sprintf( 'Screen readers announce this %s as "%s" with nothing to say what it does%s.', 'a' === $tag ? 'link' : 'button', 'a' === $tag ? 'link' : 'button', '' !== $href ? '; it points to ' . omens_audit_trim( $href, 80 ) : '' ),
						'detail'    => omens_audit_outer_html( $el ),
						'url'       => $url,
						'selector'  => omens_audit_selector( $el ),
						'criterion' => 'a' === $tag ? 'WCAG 2.2 2.4.4, 4.1.2' : 'WCAG 2.2 4.1.2',
						'fix'       => array(
							'route'      => $route['route'],
							'where'      => $route['where'],
							'steps'      => 'content' === $route['route']
								? array( 'Give the link visible text, or alt text on the image inside it, that says where it goes, in ' . $route['where'] . '.' )
								: array( 'Add text inside the element, or aria-label, or a visually hidden span naming the destination or action; an icon-only control needs one of these.' ),
							'confidence' => $route['confidence'],
						),
						'evidence'  => array_merge( array( '' !== $href ? 'href ' . $href : 'no href' ), $route['matches'] ),
					)
				);
			}
		}
		return $findings;
	}
}
