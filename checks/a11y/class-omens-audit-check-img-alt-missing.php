<?php
/**
 * a11y.img-alt-missing: images without an alt attribute (code) or with an
 * empty alt that is not decorative (routed by the content-versus-code rule).
 * WCAG 2.2 1.1.1.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Img_Alt_Missing extends Omens_Audit_Check_Base {

	const CAP = 40;

	public function id() {
		return 'a11y.img-alt-missing';
	}

	public function family() {
		return 'a11y';
	}

	public function title() {
		return 'Images without alternative text';
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
			foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
				if ( count( $findings ) >= self::CAP ) {
					break 2;
				}
				if ( omens_audit_is_hidden( $img ) || 'presentation' === $img->getAttribute( 'role' ) || 'none' === $img->getAttribute( 'role' ) ) {
					continue;
				}
				// WordPress core markup that is correct as printed: the image-lightbox template binds alt at
				// runtime (data-wp-bind--alt), and comment avatars from get_avatar() carry alt="" by design.
				if ( $img->hasAttribute( 'data-wp-bind--alt' ) || preg_match( '/(^|\s)avatar(\s|$)/', $img->getAttribute( 'class' ) ) || false !== strpos( $img->getAttribute( 'src' ), 'gravatar.com/avatar/' ) ) {
					continue;
				}
				$src = '' !== $img->getAttribute( 'src' ) ? $img->getAttribute( 'src' ) : $img->getAttribute( 'data-src' );
				if ( 0 === strpos( $src, 'data:' ) && ! $img->hasAttribute( 'data-src' ) ) {
					continue; // Inline tracking pixels and placeholders.
				}
				$key = $src . '|' . ( $img->hasAttribute( 'alt' ) ? 'empty' : 'missing' );
				if ( isset( $seen[ $key ] ) ) {
					continue; // The same logo in the header of every page is one finding.
				}

				if ( ! $img->hasAttribute( 'alt' ) ) {
					$seen[ $key ] = true;
					$findings[]   = $this->finding(
						array(
							'subject'   => $this->subject( $src, 'missing' ),
							'title'     => 'Image with no alt attribute on ' . wp_parse_url( $url, PHP_URL_PATH ),
							'summary'   => 'The <img> has no alt attribute at all, so the template printed it without one; screen readers announce the filename.',
							'detail'    => omens_audit_outer_html( $img ),
							'url'       => $url,
							'selector'  => omens_audit_selector( $img ),
							'criterion' => 'WCAG 2.2 1.1.1',
							'fix'       => array(
								'route'      => 'code',
								'where'      => $this->template_hint( $img ),
								'steps'      => array(
									'Print the image with wp_get_attachment_image() so alt comes from the Media Library, or add alt="" if it is decorative.',
									'For a theme asset (logo, icon), give it a literal alt that names what it is, or alt="" plus visible text beside it.',
								),
								'confidence' => 'high',
							),
							'evidence'  => array( 'src ' . $src ),
						)
					);
					continue;
				}

				if ( '' !== trim( $img->getAttribute( 'alt' ) ) ) {
					continue;
				}
				// Empty alt: decorative unless context says the image carries meaning.
				$attachment_id = omens_audit_img_attachment_id( $img );
				$library_alt   = $attachment_id ? trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) : '';
				$in_link       = omens_audit_ancestor( $img, 'a' ) || omens_audit_ancestor( $img, 'button' );
				$figure        = omens_audit_ancestor( $img, 'figure' );
				$only_in_fig   = $figure && 1 === $this->meaningful_children( $figure );
				$reason        = '';
				$control       = omens_audit_ancestor( $img, 'a' );
				if ( ! $control ) {
					$control = omens_audit_ancestor( $img, 'button' );
				}
				if ( $in_link && $control && '' === trim( omens_audit_accessible_name( $control ) ) ) {
					$reason = 'it is the only content of a link or button, which then has no name';
				} elseif ( '' !== $library_alt ) {
					$reason = sprintf( 'the Media Library holds alt text for it ("%s") that this template dropped', omens_audit_trim( $library_alt, 60 ) );
				} elseif ( $only_in_fig ) {
					$reason = 'it is the only content of a <figure>, so it is presented as content';
				}
				if ( '' === $reason ) {
					continue; // Decorative.
				}
				$seen[ $key ] = true;
				$route        = omens_audit_route( $post_id, array( omens_audit_filename_stem( $src ) ), array( 'attachment_id' => $attachment_id ) );
				if ( '' !== $library_alt ) {
					// The alt exists in the library and the template did not print it: that is template code.
					$route['route']      = 'code';
					$route['where']      = $this->template_hint( $img );
					$route['confidence'] = 'high';
				}
				$findings[] = $this->finding(
					array(
						'subject'   => $this->subject( $src, 'empty' ),
						'title'     => 'Image with empty alt that is not decorative on ' . wp_parse_url( $url, PHP_URL_PATH ),
						'summary'   => 'The image has alt="" but ' . $reason . '.',
						'detail'    => omens_audit_outer_html( $img ),
						'url'       => $url,
						'selector'  => omens_audit_selector( $img ),
						'criterion' => 'WCAG 2.2 1.1.1',
						'fix'       => array(
							'route'      => $route['route'],
							'where'      => $route['where'],
							'steps'      => 'content' === $route['route']
								? array( 'Write alt text that says what the image shows or does (for a linked image, where the link goes), in ' . $route['where'] . '.', 'If it really is decorative, take it out of the link or give the link its own text.' )
								: array( 'Have the template print the Media Library alt (wp_get_attachment_image(), or get_post_meta( $id, "_wp_attachment_image_alt", true )).', 'If the image is the only content of a link, give the link an aria-label or visible text.' ),
							'confidence' => $route['confidence'],
						),
						'evidence'  => array_merge( array( 'src ' . $src, 'attachment ' . ( $attachment_id ? $attachment_id : 'none' ) ), $route['matches'] ),
					)
				);
			}
		}
		return $findings;
	}

	/**
	 * Count element children that carry content (ignoring whitespace text).
	 *
	 * @param DOMElement $el Element.
	 * @return int
	 */
	private function meaningful_children( DOMElement $el ) {
		$n = 0;
		foreach ( $el->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( $child instanceof DOMElement ) {
				++$n;
			} elseif ( '' !== trim( $child->textContent ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				++$n;
			}
		}
		return $n;
	}

	/**
	 * Stable subject for an image defect: path of the src, without size suffix or query.
	 *
	 * @param string $src  Image URL.
	 * @param string $kind missing|empty.
	 * @return string
	 */
	private function subject( $src, $kind ) {
		$path = (string) wp_parse_url( (string) $src, PHP_URL_PATH );
		$path = preg_replace( '/-\d{2,5}x\d{2,5}(\.[a-z0-9]+)$/i', '$1', $path );
		return ( '' !== $path ? $path : 'inline' ) . '|' . $kind;
	}

	/**
	 * Where in the theme to look, from the element's context.
	 *
	 * @param DOMElement $img Image.
	 * @return string
	 */
	private function template_hint( DOMElement $img ) {
		$region = 'a template part';
		foreach ( array( 'header', 'footer', 'nav', 'main', 'aside' ) as $tag ) {
			if ( omens_audit_ancestor( $img, $tag ) ) {
				$region = $tag . '.php or the ' . $tag . ' template part';
				break;
			}
		}
		$class = trim( $img->getAttribute( 'class' ) );
		return $region . ( '' !== $class ? ' (img.' . strtok( $class, ' ' ) . ')' : '' );
	}
}
