<?php
/**
 * HTML helpers for the rendered-sample checks: parse with DOMDocument, build a
 * readable selector for an element, read robots meta, find scripts, and map a
 * sample URL back to the entry it renders.
 *
 * DOMDocument rather than WP_HTML_Tag_Processor because the checks need the
 * tree (ancestors, children, text content), not a token stream.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse HTML into a DOMDocument. Returns null on empty input.
 *
 * @param string $html HTML.
 * @return DOMDocument|null
 */
function omens_audit_dom( $html ) {
	$html = (string) $html;
	if ( '' === trim( $html ) ) {
		return null;
	}
	$dom  = new DOMDocument();
	$prev = libxml_use_internal_errors( true );
	// Declare the encoding for libxml without touching the document's own <meta>.
	$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	return $dom;
}

/**
 * All elements matching a tag name (or several), in document order.
 *
 * @param DOMDocument     $dom  Document.
 * @param string|string[] $tags Tag names.
 * @return DOMElement[]
 */
function omens_audit_elements( DOMDocument $dom, $tags ) {
	$out = array();
	foreach ( (array) $tags as $tag ) {
		foreach ( $dom->getElementsByTagName( $tag ) as $el ) {
			$out[] = $el;
		}
	}
	return $out;
}

/**
 * A short, stable selector for an element: tag chain from the nearest
 * landmark or id, with nth-of-type where siblings share a tag.
 *
 * @param DOMElement $el Element.
 * @return string
 */
function omens_audit_selector( DOMElement $el ) {
	$parts = array();
	$node  = $el;
	$depth = 0;
	while ( $node instanceof DOMElement && $depth < 8 ) {
		$tag = strtolower( $node->tagName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$id  = $node->getAttribute( 'id' );
		if ( '' !== $id && preg_match( '/^[A-Za-z][\w-]*$/', $id ) ) {
			$parts[] = '#' . $id;
			break;
		}
		$nth   = 1;
		$sib   = $node->previousSibling; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$count = 0;
		while ( $sib ) {
			if ( $sib instanceof DOMElement && strtolower( $sib->tagName ) === $tag ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				++$nth;
			}
			$sib = $sib->previousSibling; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
		$next = $node->nextSibling; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		while ( $next ) {
			if ( $next instanceof DOMElement && strtolower( $next->tagName ) === $tag ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				++$count;
			}
			$next = $next->nextSibling; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
		$parts[] = ( $nth > 1 || $count > 0 ) ? $tag . ':nth-of-type(' . $nth . ')' : $tag;
		if ( in_array( $tag, array( 'main', 'header', 'footer', 'nav', 'body' ), true ) ) {
			break;
		}
		$node = $node->parentNode; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		++$depth;
	}
	return implode( ' > ', array_reverse( $parts ) );
}

/**
 * Outer HTML of an element, trimmed for a finding's detail.
 *
 * @param DOMElement $el  Element.
 * @param int        $max Max length.
 * @return string
 */
function omens_audit_outer_html( DOMElement $el, $max = 300 ) {
	$html = $el->ownerDocument ? $el->ownerDocument->saveHTML( $el ) : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	return omens_audit_trim( (string) $html, $max );
}

/**
 * The robots directives on a page: the meta tag's content, lowercased tokens.
 *
 * @param DOMDocument $dom Document.
 * @return string[]
 */
function omens_audit_robots( DOMDocument $dom ) {
	$tokens = array();
	foreach ( $dom->getElementsByTagName( 'meta' ) as $meta ) {
		if ( 'robots' !== strtolower( $meta->getAttribute( 'name' ) ) ) {
			continue;
		}
		foreach ( explode( ',', strtolower( $meta->getAttribute( 'content' ) ) ) as $token ) {
			$tokens[] = trim( $token );
		}
	}
	return array_values( array_filter( array_unique( $tokens ) ) );
}

/**
 * Script sources and inline bodies on a page, plus iframe and img sources,
 * which is what tracker signatures match against.
 *
 * @param DOMDocument $dom Document.
 * @return array{src: string[], inline: string[]}
 */
function omens_audit_scripts( DOMDocument $dom ) {
	$src    = array();
	$inline = array();
	foreach ( $dom->getElementsByTagName( 'script' ) as $s ) {
		$u = $s->getAttribute( 'src' );
		if ( '' !== $u ) {
			$src[] = $u;
		} elseif ( '' !== trim( $s->textContent ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$inline[] = $s->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
	}
	foreach ( array( 'iframe', 'img', 'link' ) as $tag ) {
		foreach ( $dom->getElementsByTagName( $tag ) as $el ) {
			$u = $el->getAttribute( 'link' === $tag ? 'href' : 'src' );
			if ( '' !== $u ) {
				$src[] = $u;
			}
		}
	}
	return array(
		'src'    => $src,
		'inline' => $inline,
	);
}

/**
 * The accessible name of a link or button, roughly as a browser computes it:
 * aria-labelledby text, aria-label, text content, img alt, svg title, title.
 *
 * @param DOMElement $el Element.
 * @return string
 */
function omens_audit_accessible_name( DOMElement $el ) {
	$dom = $el->ownerDocument; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	$by  = trim( $el->getAttribute( 'aria-labelledby' ) );
	if ( '' !== $by && $dom ) {
		$text = '';
		foreach ( preg_split( '/\s+/', $by ) as $id ) {
			$ref = $dom->getElementById( $id );
			if ( $ref ) {
				$text .= ' ' . $ref->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
		}
		if ( '' !== trim( $text ) ) {
			return trim( preg_replace( '/\s+/', ' ', $text ) );
		}
	}
	$label = trim( $el->getAttribute( 'aria-label' ) );
	if ( '' !== $label ) {
		return $label;
	}
	$text = trim( preg_replace( '/\s+/', ' ', $el->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	if ( '' !== $text ) {
		return $text;
	}
	foreach ( $el->getElementsByTagName( 'img' ) as $img ) {
		if ( '' !== trim( $img->getAttribute( 'alt' ) ) ) {
			return trim( $img->getAttribute( 'alt' ) );
		}
	}
	foreach ( $el->getElementsByTagName( 'svg' ) as $svg ) {
		if ( '' !== trim( $svg->getAttribute( 'aria-label' ) ) ) {
			return trim( $svg->getAttribute( 'aria-label' ) );
		}
		foreach ( $svg->getElementsByTagName( 'title' ) as $title ) {
			if ( '' !== trim( $title->textContent ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				return trim( $title->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
		}
	}
	foreach ( $el->getElementsByTagName( '*' ) as $child ) {
		if ( '' !== trim( $child->getAttribute( 'aria-label' ) ) ) {
			return trim( $child->getAttribute( 'aria-label' ) );
		}
	}
	if ( 'input' === strtolower( $el->tagName ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$v = trim( $el->getAttribute( 'value' ) );
		if ( '' !== $v && in_array( strtolower( $el->getAttribute( 'type' ) ), array( 'submit', 'button', 'reset' ), true ) ) {
			return $v;
		}
	}
	return trim( $el->getAttribute( 'title' ) );
}

/**
 * Whether an element is hidden from assistive technology.
 *
 * @param DOMElement $el Element.
 * @return bool
 */
function omens_audit_is_hidden( DOMElement $el ) {
	$node = $el;
	while ( $node instanceof DOMElement ) {
		if ( 'true' === strtolower( $node->getAttribute( 'aria-hidden' ) ) || $node->hasAttribute( 'hidden' ) ) {
			return true;
		}
		if ( preg_match( '/display\s*:\s*none|visibility\s*:\s*hidden/i', $node->getAttribute( 'style' ) ) ) {
			return true;
		}
		$node = $node->parentNode; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
	return false;
}

/**
 * Whether an element sits inside a given ancestor tag.
 *
 * @param DOMElement $el  Element.
 * @param string     $tag Ancestor tag name.
 * @return DOMElement|null The ancestor.
 */
function omens_audit_ancestor( DOMElement $el, $tag ) {
	$node = $el->parentNode; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	while ( $node instanceof DOMElement ) {
		if ( strtolower( $node->tagName ) === strtolower( $tag ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			return $node;
		}
		$node = $node->parentNode; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
	return null;
}

/**
 * The post a sample URL renders, or 0 (an archive, the blog page, a 404).
 *
 * @param string $url URL.
 * @return int
 */
function omens_audit_url_post_id( $url ) {
	$url = remove_query_arg( 'omens-audit', (string) $url );
	if ( untrailingslashit( $url ) === untrailingslashit( home_url( '/' ) ) ) {
		$front = (int) get_option( 'page_on_front' );
		return 'page' === get_option( 'show_on_front' ) && $front ? $front : 0;
	}
	$id = (int) url_to_postid( $url );
	/**
	 * Map a sample URL to the entry it renders, for a custom routing scheme.
	 *
	 * @param int    $id  Post ID or 0.
	 * @param string $url URL.
	 */
	return (int) apply_filters( 'omens_audit/url_post_id', $id, $url );
}

/**
 * Attachment ID for an <img>: the wp-image-N class first, then the src.
 *
 * @param DOMElement $img Image element.
 * @return int
 */
function omens_audit_img_attachment_id( DOMElement $img ) {
	if ( preg_match( '/\bwp-image-(\d+)\b/', $img->getAttribute( 'class' ), $m ) ) {
		return (int) $m[1];
	}
	$src = $img->getAttribute( 'src' );
	if ( '' === $src ) {
		$src = $img->getAttribute( 'data-src' );
	}
	if ( '' === $src ) {
		return 0;
	}
	if ( 0 === strpos( $src, '//' ) ) {
		$scheme = wp_parse_url( home_url(), PHP_URL_SCHEME );
		$src    = ( $scheme ? $scheme : 'https' ) . ':' . $src;
	} elseif ( 0 === strpos( $src, '/' ) ) {
		$src = home_url( $src );
	}
	return (int) attachment_url_to_postid( $src );
}

/**
 * The stem of an image filename from its src, for alt comparisons.
 *
 * @param string $src Image URL.
 * @return string
 */
function omens_audit_filename_stem( $src ) {
	$path = (string) wp_parse_url( (string) $src, PHP_URL_PATH );
	$stem = pathinfo( $path, PATHINFO_FILENAME );
	$stem = preg_replace( '/-\d{2,5}x\d{2,5}$/', '', $stem );
	$stem = preg_replace( '/[-_](scaled|rotated|edited|copy)$/i', '', $stem );
	$stem = preg_replace( '/@[2-4]x$/i', '', $stem );
	return trim( (string) $stem );
}

/**
 * Whether a filename stem carries no meaning (a camera default, a hash, a
 * generic label). Uses omens-wp-plugin-media's function when it is active,
 * otherwise the ported patterns in data/meaningless-filenames.json.
 *
 * @param string $name Filename or stem.
 * @return bool
 */
function omens_audit_is_meaningless( $name ) {
	if ( function_exists( 'omens_media_is_meaningless' ) ) {
		return (bool) omens_media_is_meaningless( $name );
	}
	$stem = omens_audit_filename_stem( $name );
	if ( '' === $stem ) {
		return true;
	}
	static $patterns = null;
	if ( null === $patterns ) {
		$json     = json_decode( (string) file_get_contents( OMENS_AUDIT_DIR . 'data/meaningless-filenames.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$patterns = is_array( $json ) && ! empty( $json['patterns'] ) ? $json['patterns'] : array();
	}
	foreach ( $patterns as $p ) {
		if ( preg_match( '/' . $p . '/i', $stem ) ) {
			return true;
		}
	}
	return ! preg_match( '/\p{L}/u', $stem );
}

/**
 * Whether an HTML response is a gate or holding page rather than the site:
 * a password field (or a maintenance/coming-soon title) with no main, nav or
 * footer landmark. Cheap string tests on purpose; this runs before parsing.
 *
 * @param string $html Response body.
 * @return bool
 */
function omens_audit_looks_gated( $html ) {
	$html         = (string) $html;
	$has_landmark = preg_match( '/<(main|footer|nav)\b/i', $html ) || preg_match( '/role=["\'](main|contentinfo|navigation)["\']/i', $html );
	if ( $has_landmark ) {
		return false;
	}
	if ( preg_match( '/<input[^>]+type=["\']password["\']/i', $html ) ) {
		return true;
	}
	if ( preg_match( '/<title>\s*(maintenance|coming soon|under construction|site under maintenance)\b/i', $html ) ) {
		return true;
	}
	/**
	 * Decide for yourself whether a response is a gate page (for a custom holding page).
	 *
	 * @param bool   $gated Default false.
	 * @param string $html  Response body.
	 */
	return (bool) apply_filters( 'omens_audit/looks_gated', false, $html );
}
