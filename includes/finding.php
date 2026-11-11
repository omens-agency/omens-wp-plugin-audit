<?php
/**
 * The finding: one shape shared with the a11y harness and qa.py.
 *
 * See PLAN section 4.2. The fingerprint is sha1( host|check|subject ), where
 * the subject is the thing the finding is about (a URL plus selector, a plugin
 * slug, a user ID) and never the run date, so the same problem fingerprints
 * the same way every month and BugHerd dedupes on it.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Severities, most severe first. `info` is never filed to BugHerd.
 *
 * @return string[]
 */
function omens_audit_severities() {
	return array( 'critical', 'serious', 'moderate', 'minor', 'info' );
}

/**
 * Fix routes. On build_type = purchased sites, `code` becomes `human` (the
 * run engine does that, not the check).
 *
 * @return string[]
 */
function omens_audit_routes() {
	return array( 'code', 'content', 'config', 'vendor', 'human' );
}

/**
 * Build a finding in the shared schema, filling defaults and the fingerprint.
 *
 * @param array $args {
 *     Finding fields.
 *
 *     @type string   $check     Check ID, e.g. `security.file-edit-enabled`. Required.
 *     @type string   $subject   What the finding is about, for the fingerprint. Required.
 *     @type string   $family    security|currency|seo|a11y|legal|performance|run. Required.
 *     @type string   $severity  critical|serious|moderate|minor|info. Required.
 *     @type string   $title     Short title. Required.
 *     @type string   $summary   One sentence.
 *     @type string   $detail    What was found; offending HTML trimmed to 300 chars.
 *     @type string   $url       Page URL, or ''.
 *     @type string   $selector  CSS selector, or ''.
 *     @type string   $criterion WCAG criterion such as "WCAG 2.2 1.1.1", or 'none'.
 *     @type array    $fix       { route, where, steps[], confidence }.
 *     @type string[] $evidence  What was measured.
 *     @type string   $check_version Check version.
 * }
 * @param string $host Site host for the fingerprint; defaults to the current site's.
 * @return array
 */
function omens_audit_finding( array $args, $host = '' ) {
	// Text lifted from a site (titles, markup, option values) can hold a broken byte sequence,
	// and wpdb refuses to store an option that does: the run then fails to save.
	array_walk_recursive(
		$args,
		function ( &$v ) {
			$v = omens_audit_clean_utf8( $v );
		}
	);
	$host = '' !== $host ? $host : omens_audit_host();
	$fix  = wp_parse_args(
		isset( $args['fix'] ) && is_array( $args['fix'] ) ? $args['fix'] : array(),
		array(
			'route'      => 'human',
			'where'      => '',
			'steps'      => array(),
			'confidence' => 'high',
		)
	);
	if ( ! in_array( $fix['route'], omens_audit_routes(), true ) ) {
		$fix['route'] = 'human';
	}
	if ( ! in_array( $fix['confidence'], array( 'high', 'medium', 'low' ), true ) ) {
		$fix['confidence'] = 'low';
	}
	$fix['steps'] = array_values( array_map( 'strval', (array) $fix['steps'] ) );

	$severity = isset( $args['severity'] ) ? (string) $args['severity'] : 'info';
	if ( ! in_array( $severity, omens_audit_severities(), true ) ) {
		$severity = 'info';
	}

	$check   = isset( $args['check'] ) ? (string) $args['check'] : 'run.unknown';
	$subject = isset( $args['subject'] ) ? (string) $args['subject'] : '';

	return array(
		'fingerprint'   => sha1( $host . '|' . $check . '|' . $subject ),
		'check'         => $check,
		'subject'       => $subject,
		'family'        => isset( $args['family'] ) ? (string) $args['family'] : strtok( $check, '.' ),
		'source'        => 'plugin',
		'severity'      => $severity,
		'title'         => isset( $args['title'] ) ? (string) $args['title'] : $check,
		'summary'       => isset( $args['summary'] ) ? (string) $args['summary'] : '',
		'detail'        => isset( $args['detail'] ) ? omens_audit_trim( (string) $args['detail'], 300 ) : '',
		'url'           => isset( $args['url'] ) ? (string) $args['url'] : '',
		'selector'      => isset( $args['selector'] ) ? (string) $args['selector'] : '',
		'criterion'     => isset( $args['criterion'] ) && '' !== $args['criterion'] ? (string) $args['criterion'] : 'none',
		'fix'           => $fix,
		'evidence'      => array_values( array_map( 'strval', isset( $args['evidence'] ) ? (array) $args['evidence'] : array() ) ),
		'check_version' => isset( $args['check_version'] ) ? (string) $args['check_version'] : '0.0.0',
	);
}

/**
 * Trim a string to a byte length, marking the cut.
 *
 * @param string $text   Text.
 * @param int    $length Max length.
 * @return string
 */
function omens_audit_trim( $text, $length ) {
	$text = trim( (string) preg_replace( '/\s+/', ' ', (string) $text ) );
	if ( strlen( $text ) <= $length ) {
		return $text;
	}
	// Cut on a character boundary. A cut inside a multibyte character (a curly quote, an en
	// dash, an accented letter) leaves invalid UTF-8, which json_encode and wpdb both refuse.
	$room = max( 0, (int) $length - 3 ); // The ellipsis is three bytes.
	if ( function_exists( 'mb_strcut' ) ) {
		$cut = mb_strcut( $text, 0, $room, 'UTF-8' );
	} else {
		$cut = (string) preg_replace( '/[\xC0-\xFF][\x80-\xBF]*$/', '', substr( $text, 0, $room ) );
	}
	return $cut . '…';
}

/**
 * A string with any invalid UTF-8 removed; other values unchanged.
 *
 * @param mixed $value Value.
 * @return mixed
 */
function omens_audit_clean_utf8( $value ) {
	if ( ! is_string( $value ) || '' === $value || ! preg_match( '/[\x80-\xFF]/', $value ) ) {
		return $value;
	}
	if ( function_exists( 'mb_check_encoding' ) && mb_check_encoding( $value, 'UTF-8' ) ) {
		return $value;
	}
	if ( function_exists( 'mb_convert_encoding' ) ) {
		$prev = function_exists( 'mb_substitute_character' ) ? mb_substitute_character() : null;
		if ( null !== $prev ) {
			mb_substitute_character( 'none' );
		}
		$clean = mb_convert_encoding( $value, 'UTF-8', 'UTF-8' );
		if ( null !== $prev ) {
			mb_substitute_character( $prev );
		}
		return (string) $clean;
	}
	return function_exists( 'wp_check_invalid_utf8' ) ? wp_check_invalid_utf8( $value, true ) : (string) preg_replace( '/[\x80-\xFF]/', '', $value );
}

/**
 * The host the fingerprints and the stored run are keyed by.
 *
 * @return string
 */
function omens_audit_host() {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	return strtolower( (string) $host );
}

/**
 * Edit link for an entry that works with no current user (WP-CLI), unlike
 * get_edit_post_link(), which returns null when nobody is logged in.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function omens_audit_edit_link( $post_id ) {
	return admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' );
}
