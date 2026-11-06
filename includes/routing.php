<?php
/**
 * The content-versus-code rule (PLAN section 4.1). Decides whether a defect
 * in rendered HTML came from something an editor controls (route `content`)
 * or from a template (route `code`), by searching for the element's key in
 * the places editors put things, and in the theme's own source.
 *
 * This decides which BugHerd tasks reach a pull request, so it is explicit
 * about every branch and each branch has a fixture:
 *
 *   1. missing attribute       -> code (the check decides this, not this file)
 *   2. key found in editor content (post_content, ACF fields, ACF options,
 *      or the media library for an attachment) and not in the theme -> content
 *   3. key found nowhere in editor content -> code
 *   4. key found in both editor content and the theme, or no usable key
 *      -> confidence low (web-audit-fix proposes, never changes)
 *   5. on purchased sites, code -> human (the run engine does that)
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route a rendered defect to content or code.
 *
 * @param int      $post_id Post the page renders, or 0.
 * @param string[] $keys    Strings that identify the element where an editor
 *                          would have typed or chosen it: an href, link text,
 *                          an image filename stem, "wp-image-123".
 * @param array    $opts    { attachment_id: int } for images.
 * @return array {
 *     @type string   $route      content|code
 *     @type string   $where      Where to fix it.
 *     @type string   $confidence high|medium|low
 *     @type string[] $matches    Human labels of every place the key was found.
 * }
 */
function omens_audit_route( $post_id, array $keys, array $opts = array() ) {
	$keys    = array_values( array_unique( array_filter( array_map( 'omens_audit_route_key', $keys ) ) ) );
	$post_id = (int) $post_id;
	$matches = array();
	$title   = $post_id ? get_the_title( $post_id ) : '';

	// The media library is where an attachment's alt lives, whatever placed the image.
	$attachment_id = (int) ( $opts['attachment_id'] ?? 0 );
	$library       = '';
	if ( $attachment_id && get_post( $attachment_id ) ) {
		$library = sprintf( 'Media Library alt for attachment %d', $attachment_id );
		$keys[]  = 'wp-image-' . $attachment_id;
		$url     = wp_get_attachment_url( $attachment_id );
		if ( $url ) {
			$keys[] = omens_audit_filename_stem( $url );
		}
		$keys = array_values( array_unique( array_filter( $keys ) ) );
	}

	if ( $post_id ) {
		$post = get_post( $post_id );
		if ( $post && omens_audit_route_haystack_has( (string) $post->post_content, $keys ) ) {
			$matches[] = sprintf( 'the content of "%s" (%s)', $title, omens_audit_edit_link( $post_id ) );
		}
		if ( function_exists( 'get_fields' ) ) {
			foreach ( omens_audit_route_flatten( (array) get_fields( $post_id ) ) as $path => $value ) {
				if ( omens_audit_route_haystack_has( $value, $keys ) ) {
					$matches[] = sprintf( 'ACF field `%s` on "%s"', $path, $title );
				}
			}
		}
	}
	if ( function_exists( 'get_fields' ) ) {
		foreach ( omens_audit_route_flatten( (array) get_fields( 'option' ) ) as $path => $value ) {
			if ( omens_audit_route_haystack_has( $value, $keys ) ) {
				$matches[] = sprintf( 'ACF options field `%s`', $path );
			}
		}
	}

	$theme_files = $keys ? omens_audit_route_theme_files_with( $keys ) : array();

	if ( $library ) {
		// An attachment's alt is editable whatever placed the image; where the
		// image is placed only tells us how sure we are the editor sees it.
		return array(
			'route'      => 'content',
			'where'      => $library . ( $matches ? ' (also ' . implode( '; ', $matches ) . ')' : '' ),
			'confidence' => $matches ? 'high' : ( $theme_files ? 'medium' : 'medium' ),
			'matches'    => array_merge( array( $library ), $matches ),
		);
	}

	if ( ! $keys ) {
		return array(
			'route'      => 'code',
			'where'      => 'template (the element has no stable key to search for)',
			'confidence' => 'low',
			'matches'    => array(),
		);
	}

	if ( $matches && $theme_files ) {
		return array(
			'route'      => 'content',
			'where'      => $matches[0] . '; also hard-coded in ' . implode( ', ', $theme_files ),
			'confidence' => 'low',
			'matches'    => array_merge( $matches, $theme_files ),
		);
	}
	if ( $matches ) {
		return array(
			'route'      => 'content',
			'where'      => $matches[0],
			'confidence' => 'high',
			'matches'    => $matches,
		);
	}
	if ( $theme_files ) {
		return array(
			'route'      => 'code',
			'where'      => implode( ', ', $theme_files ),
			'confidence' => 'high',
			'matches'    => $theme_files,
		);
	}
	return array(
		'route'      => 'code',
		'where'      => 'template (not found in the theme source by text; search for the element by its selector)',
		'confidence' => 'medium',
		'matches'    => array(),
	);
}

/**
 * Normalise a search key; drop ones too short or generic to mean anything.
 *
 * @param mixed $key Key.
 * @return string
 */
function omens_audit_route_key( $key ) {
	$key = trim( (string) $key );
	if ( strlen( $key ) < 3 || in_array( $key, array( '#', '/', '#!', '#top', 'javascript:void(0)', 'javascript:;' ), true ) ) {
		return '';
	}
	return $key;
}

/**
 * Whether a haystack contains any key (case-insensitive, with a path-only
 * variant of absolute same-site URLs).
 *
 * @param string   $haystack Text.
 * @param string[] $keys     Keys.
 * @return bool
 */
function omens_audit_route_haystack_has( $haystack, array $keys ) {
	$haystack = (string) $haystack;
	if ( '' === $haystack ) {
		return false;
	}
	foreach ( $keys as $key ) {
		if ( false !== stripos( $haystack, $key ) ) {
			return true;
		}
		$path = omens_audit_route_path_variant( $key );
		if ( '' !== $path && false !== stripos( $haystack, $path ) ) {
			return true;
		}
	}
	return false;
}

/**
 * For an absolute URL on this site, the path-only form editors often store.
 *
 * @param string $key Key.
 * @return string
 */
function omens_audit_route_path_variant( $key ) {
	$home = untrailingslashit( home_url() );
	if ( 0 === stripos( $key, $home . '/' ) ) {
		$path = substr( $key, strlen( $home ) );
		return strlen( $path ) >= 3 ? $path : '';
	}
	return '';
}

/**
 * Flatten ACF fields to path => string, including image and link arrays.
 *
 * @param array  $fields Fields.
 * @param string $prefix Path prefix.
 * @return array<string, string>
 */
function omens_audit_route_flatten( array $fields, $prefix = '' ) {
	$out = array();
	foreach ( $fields as $name => $value ) {
		$path = '' === $prefix ? (string) $name : $prefix . '.' . $name;
		if ( is_array( $value ) ) {
			$out = array_merge( $out, omens_audit_route_flatten( $value, $path ) );
		} elseif ( is_object( $value ) ) {
			$out = array_merge( $out, omens_audit_route_flatten( get_object_vars( $value ), $path ) );
		} elseif ( is_scalar( $value ) ) {
			$out[ $path ] = (string) $value;
			if ( is_int( $value ) && in_array( strtolower( (string) $name ), array( 'id', 'image', 'photo', 'logo' ), true ) ) {
				$out[ $path . '#attachment' ] = 'wp-image-' . $value;
			}
		}
	}
	return $out;
}

/**
 * Theme PHP files (child and parent) that contain any key. Cached per run.
 *
 * @param string[] $keys Keys.
 * @return string[] Relative file paths.
 */
function omens_audit_route_theme_files_with( array $keys ) {
	static $sources = null;
	if ( null === $sources ) {
		$sources = array();
		$dirs    = array_unique( array( get_stylesheet_directory(), get_template_directory() ) );
		$budget  = 4 * 1024 * 1024;
		foreach ( $dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS ), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD );
			foreach ( $it as $file ) {
				$path = $file->getPathname();
				if ( ! preg_match( '/\.(php|twig|html)$/i', $path ) || false !== strpos( $path, '/node_modules/' ) || false !== strpos( $path, '/vendor/' ) ) {
					continue;
				}
				$size = (int) $file->getSize();
				if ( $size > $budget ) {
					break 2;
				}
				$budget -= $size;
				$sources[ basename( $dir ) . substr( $path, strlen( $dir ) ) ] = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}
	}
	$hits = array();
	foreach ( $sources as $rel => $content ) {
		if ( omens_audit_route_haystack_has( $content, $keys ) ) {
			$hits[] = $rel;
		}
	}
	return $hits;
}
