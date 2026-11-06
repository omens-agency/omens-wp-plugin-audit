<?php
/**
 * a11y.img-alt-filename: alt text that is a filename or otherwise meaningless.
 * Alt values come from the media library or an editor, so content unless the
 * rule finds the value hard-coded. WCAG 2.2 1.1.1.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Omens_Audit_Check_Img_Alt_Filename extends Omens_Audit_Check_Base {

	const CAP = 40;

	public function id() {
		return 'a11y.img-alt-filename';
	}

	public function family() {
		return 'a11y';
	}

	public function title() {
		return 'Alt text that is a filename or meaningless';
	}

	public function severity() {
		return 'moderate';
	}

	/**
	 * Why an alt value is meaningless, or '' when it is fine.
	 *
	 * @param string $alt Alt text.
	 * @param string $src Image URL.
	 * @return string
	 */
	public static function why_meaningless( $alt, $src ) {
		$alt = trim( (string) $alt );
		if ( '' === $alt ) {
			return '';
		}
		if ( preg_match( '/\.(jpe?g|png|gif|webp|svg|avif|bmp|tiff?)$/i', $alt ) ) {
			return 'it is a filename with its extension';
		}
		$stem = omens_audit_filename_stem( $src );
		if ( '' !== $stem && strcasecmp( preg_replace( '/[\s_-]+/', '', $alt ), preg_replace( '/[\s_-]+/', '', $stem ) ) === 0 && omens_audit_is_meaningless( $stem ) ) {
			return 'it repeats the meaningless filename';
		}
		if ( omens_audit_is_meaningless( $alt ) ) {
			return 'it is a camera default, a hash or a generic label';
		}
		if ( preg_match( '/^(image|picture|photo|graphic|img|icon|logo|placeholder|untitled)$/i', $alt ) ) {
			return 'it names the kind of thing, not what it shows';
		}
		return '';
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
				if ( omens_audit_is_hidden( $img ) ) {
					continue;
				}
				$alt = trim( $img->getAttribute( 'alt' ) );
				$src = '' !== $img->getAttribute( 'src' ) ? $img->getAttribute( 'src' ) : $img->getAttribute( 'data-src' );
				$why = self::why_meaningless( $alt, $src );
				if ( '' === $why ) {
					continue;
				}
				$key = strtolower( $alt ) . '|' . (string) wp_parse_url( (string) $src, PHP_URL_PATH );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ]  = true;
				$attachment_id = omens_audit_img_attachment_id( $img );
				$route         = omens_audit_route( $post_id, array( $alt ), array( 'attachment_id' => $attachment_id ) );
				if ( ! $attachment_id && 'code' === $route['route'] ) {
					// No attachment and the value only in the theme: a literal alt in a template.
					$route['where'] = $route['matches'] ? $route['where'] : 'the template that prints this image';
				}
				$findings[] = $this->finding(
					array(
						'subject'   => $key,
						'title'     => sprintf( 'Alt text "%s" is not a description', omens_audit_trim( $alt, 50 ) ),
						'summary'   => sprintf( 'The alt reads "%s" but %s; a screen reader user hears it as content.', omens_audit_trim( $alt, 60 ), $why ),
						'detail'    => omens_audit_outer_html( $img ),
						'url'       => $url,
						'selector'  => omens_audit_selector( $img ),
						'criterion' => 'WCAG 2.2 1.1.1',
						'fix'       => array(
							'route'      => $route['route'],
							'where'      => $route['where'],
							'steps'      => array( 'Replace it with alt text that says what the image shows, or alt="" if it is decorative, in ' . $route['where'] . '.' ),
							'confidence' => $route['confidence'],
						),
						'evidence'  => array_merge( array( 'src ' . $src, 'attachment ' . ( $attachment_id ? $attachment_id : 'none' ) ), $route['matches'] ),
					)
				);
			}
		}
		return $findings;
	}
}
