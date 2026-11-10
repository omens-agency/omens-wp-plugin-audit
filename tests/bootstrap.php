<?php
/**
 * WordPress stubs for the fixture runner. Just enough of core for the plugin's
 * includes and checks to run in a plain PHP process, with every piece of site
 * state in $GLOBALS['omens_test'] where a fixture can set it.
 *
 * Each fixture case runs in its own process (tests/case.php), so constants such
 * as DISALLOW_FILE_EDIT can differ between the "fires" and "clean" cases.
 *
 * Not a WordPress test suite. The stubs mirror core's signatures and the
 * behaviours the checks rely on, nothing more; tools/smoke.sh on a real local
 * site is the other half of the verification.
 *
 * @package OmensAudit
 */

// phpcs:ignoreFile

define( 'ABSPATH', sys_get_temp_dir() . '/omens-audit-abspath/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'OBJECT', 'OBJECT' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/omens-audit-wp-content' );
define( 'WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins' );
define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );

$GLOBALS['omens_test'] = array(
	'home'          => 'https://example.test',
	'env'           => 'production',
	'locale'        => 'en_US',
	'options'       => array( 'blog_public' => '1', 'active_plugins' => array() ),
	'site_options'  => array(),
	'transients'    => array(),
	'posts'         => array(),
	'postmeta'      => array(),
	'users'         => array(),
	'usermeta'      => array(),
	'roles'         => array( 'administrator' => array( 'name' => 'Administrator', 'capabilities' => array( 'manage_options' => true ) ), 'editor' => array( 'name' => 'Editor', 'capabilities' => array() ) ),
	'http'          => array(), // url (without the cache-bust arg) => array( code, body ) or 'error'.
	'theme'         => array( 'stylesheet' => 'example', 'name' => 'Example', 'version' => '1.0', 'author' => 'Example Co', 'author_uri' => '', 'parent' => null, 'dir' => null ),
	'plugins'       => array(), // file => header array
	'mu_plugins'    => array(),
	'uploads'       => null,    // basedir
	'acf'           => array(), // post_id|'option' => fields
	'db_tables'     => array(),
	'db_rows'       => array(), // table => rows
	'hooks'         => array(),
	'cron'          => array(),
	'post_types'    => array( 'post' => true, 'page' => true, 'attachment' => true ),
	'current_user'  => null,
);

function omens_test() { return $GLOBALS['omens_test']; }
function &omens_test_state() { return $GLOBALS['omens_test']; }

// --- Hooks -------------------------------------------------------------------
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['omens_test']['hooks'][ $hook ][ $prio ][] = $cb; return true; }
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { return add_filter( $hook, $cb, $prio, $args ); }
function remove_filter( $hook, $cb, $prio = 10 ) { return true; }
function has_filter( $hook, $cb = false ) { return ! empty( $GLOBALS['omens_test']['hooks'][ $hook ] ); }
function apply_filters( $hook, $value, ...$args ) {
	if ( empty( $GLOBALS['omens_test']['hooks'][ $hook ] ) ) { return $value; }
	$prios = $GLOBALS['omens_test']['hooks'][ $hook ]; ksort( $prios );
	foreach ( $prios as $cbs ) { foreach ( $cbs as $cb ) { $value = call_user_func( $cb, $value, ...$args ); } }
	return $value;
}
function do_action( $hook, ...$args ) {
	if ( empty( $GLOBALS['omens_test']['hooks'][ $hook ] ) ) { return; }
	$prios = $GLOBALS['omens_test']['hooks'][ $hook ]; ksort( $prios );
	foreach ( $prios as $cbs ) { foreach ( $cbs as $cb ) { call_user_func( $cb, ...$args ); } }
}
function register_activation_hook( $f, $cb ) {}
function register_deactivation_hook( $f, $cb ) {}
function plugin_dir_path( $f ) { return rtrim( dirname( $f ), '/' ) . '/'; }

// --- URLs, strings -----------------------------------------------------------
function home_url( $path = '', $scheme = null ) { return rtrim( $GLOBALS['omens_test']['home'], '/' ) . ( '' !== $path ? '/' . ltrim( $path, '/' ) : '' ); }
function site_url( $path = '' ) { return home_url( $path ); }
function admin_url( $path = '' ) { return home_url( 'wp-admin/' . ltrim( $path, '/' ) ); }
function wp_parse_url( $url, $component = -1 ) { return -1 === $component ? parse_url( $url ) : parse_url( $url, $component ); }
function wp_parse_args( $args, $defaults = array() ) { if ( is_object( $args ) ) { $args = get_object_vars( $args ); } elseif ( ! is_array( $args ) ) { parse_str( (string) $args, $args ); } return array_merge( $defaults, $args ); }
function add_query_arg( ...$a ) {
	if ( is_array( $a[0] ) ) { $params = $a[0]; $url = $a[1] ?? ''; } else { $params = array( $a[0] => $a[1] ); $url = $a[2] ?? ''; }
	$p = parse_url( $url ); $q = array(); if ( ! empty( $p['query'] ) ) { parse_str( $p['query'], $q ); }
	foreach ( $params as $k => $v ) { if ( false === $v ) { unset( $q[ $k ] ); } else { $q[ $k ] = $v; } }
	$base = ( $p['scheme'] ?? 'https' ) . '://' . ( $p['host'] ?? '' ) . ( isset( $p['port'] ) ? ':' . $p['port'] : '' ) . ( $p['path'] ?? '' );
	return $base . ( $q ? '?' . http_build_query( $q ) : '' ) . ( isset( $p['fragment'] ) ? '#' . $p['fragment'] : '' );
}
function remove_query_arg( $key, $url ) { return add_query_arg( $key, false, $url ); }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( $s, '/\\' ); }
function wp_json_encode( $d, $f = 0, $depth = 512 ) { return json_encode( $d, $f, $depth ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return (string) $s; }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function __( $s, $d = null ) { return $s; }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_title( $s ) { return strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', (string) $s ), '-' ) ); }
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $s ) ); }
function wp_kses_post( $s ) { return $s; }
function absint( $n ) { return abs( (int) $n ); }
function current_time( $type, $gmt = 0 ) { return 'timestamp' === $type ? time() : gmdate( 'Y-m-d H:i:s' ); }
function get_bloginfo( $show = '' ) { return 'version' === $show ? '7.1.4' : ( 'name' === $show ? 'Example' : '' ); }
function get_locale() { return $GLOBALS['omens_test']['locale']; }
function wp_get_environment_type() { return $GLOBALS['omens_test']['env']; }
function is_multisite() { return false; }
function get_super_admins() { return array(); }
function is_admin() { return false; }
function wp_normalize_path( $p ) { return str_replace( '\\', '/', $p ); }

// --- Options, transients, cron -------------------------------------------------
function get_option( $k, $default = false ) { return array_key_exists( $k, $GLOBALS['omens_test']['options'] ) ? $GLOBALS['omens_test']['options'][ $k ] : $default; }
function add_option( $k, $v = '', $d = '', $autoload = 'yes' ) { if ( array_key_exists( $k, $GLOBALS['omens_test']['options'] ) ) { return false; } $GLOBALS['omens_test']['options'][ $k ] = $v; return true; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['omens_test']['options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['omens_test']['options'][ $k ] ); return true; }
function get_site_option( $k, $default = false ) { return $GLOBALS['omens_test']['site_options'][ $k ] ?? $default; }
function get_site_transient( $k ) { return $GLOBALS['omens_test']['transients'][ $k ] ?? false; }
function get_transient( $k ) { return get_site_transient( $k ); }
function wp_next_scheduled( $hook ) { return $GLOBALS['omens_test']['cron'][ $hook ] ?? false; }
function wp_schedule_event( $ts, $rec, $hook ) { $GLOBALS['omens_test']['cron'][ $hook ] = $ts; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['omens_test']['cron'][ $hook ] ); return 1; }

// --- HTTP ---------------------------------------------------------------------
class WP_Error {
	public $errors = array();
	public function __construct( $code = '', $message = '' ) { if ( $code ) { $this->errors[ $code ][] = $message; } }
	public function get_error_message() { foreach ( $this->errors as $m ) { return $m[0]; } return ''; }
	public function get_error_code() { foreach ( $this->errors as $c => $m ) { return $c; } return ''; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['omens_test']['http_log'][] = $url;
	$key = remove_query_arg( 'omens-audit', $url );
	$map = $GLOBALS['omens_test']['http'];
	$hit = $map[ $key ] ?? $map[ rtrim( $key, '/' ) ] ?? $map[ rtrim( $key, '/' ) . '/' ] ?? null;
	if ( null === $hit ) { return array( 'response' => array( 'code' => 404 ), 'body' => 'not in fixture' ); }
	if ( 'error' === $hit ) { return new WP_Error( 'http_request_failed', 'cURL error 28: timed out (fixture)' ); }
	return array( 'response' => array( 'code' => (int) ( $hit['code'] ?? 200 ) ), 'body' => (string) ( $hit['body'] ?? '' ), 'headers' => $hit['headers'] ?? array() );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? ( $r['response']['code'] ?? '' ) : ''; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? ( $r['body'] ?? '' ) : ''; }
function wp_remote_retrieve_header( $r, $h ) { return is_array( $r ) ? ( $r['headers'][ strtolower( $h ) ] ?? '' ) : ''; }

// --- Posts --------------------------------------------------------------------
function omens_test_add_post( array $p ) {
	static $next = 100;
	$id = (int) ( $p['ID'] ?? $next++ );
	$post = (object) array_merge( array(
		'ID' => $id, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Post ' . $id, 'post_name' => 'post-' . $id,
		'post_content' => '', 'post_excerpt' => '', 'post_modified' => gmdate( 'Y-m-d H:i:s' ), 'post_modified_gmt' => gmdate( 'Y-m-d H:i:s' ),
		'post_date' => gmdate( 'Y-m-d H:i:s' ), 'post_parent' => 0, 'post_mime_type' => '', 'guid' => '', 'post_password' => '',
	), $p );
	$GLOBALS['omens_test']['posts'][ $id ] = $post;
	if ( isset( $p['meta'] ) ) { foreach ( $p['meta'] as $k => $v ) { $GLOBALS['omens_test']['postmeta'][ $id ][ $k ] = $v; } unset( $post->meta ); }
	if ( isset( $p['acf'] ) ) { $GLOBALS['omens_test']['acf'][ $id ] = $p['acf']; unset( $post->acf ); }
	return $id;
}
function get_post( $id = null, $output = OBJECT ) { $id = is_object( $id ) ? $id->ID : (int) $id; return $GLOBALS['omens_test']['posts'][ $id ] ?? null; }
function get_post_status( $id ) { $p = get_post( $id ); return $p ? $p->post_status : false; }
function get_post_type( $id ) { $p = get_post( $id ); return $p ? $p->post_type : false; }
function get_post_meta( $id, $key = '', $single = false ) {
	$m = $GLOBALS['omens_test']['postmeta'][ (int) $id ] ?? array();
	if ( '' === $key ) { return $m; }
	if ( ! array_key_exists( $key, $m ) ) { return $single ? '' : array(); }
	return $single ? $m[ $key ] : array( $m[ $key ] );
}
function get_the_title( $id ) { $p = get_post( $id ); return $p ? $p->post_title : ''; }
function get_edit_post_link( $id, $ctx = 'display' ) { return admin_url( 'post.php?post=' . (int) $id . '&action=edit' ); }
function get_permalink( $post ) {
	$p = is_object( $post ) ? $post : get_post( $post ); if ( ! $p ) { return false; }
	if ( 'attachment' === $p->post_type ) { return $p->guid; }
	return home_url( '/' . $p->post_name . '/' );
}
function get_page_by_path( $path, $output = OBJECT, $type = 'page' ) {
	foreach ( $GLOBALS['omens_test']['posts'] as $p ) { if ( $p->post_name === trim( $path, '/' ) && in_array( $p->post_type, (array) $type, true ) ) { return $p; } }
	return null;
}
function get_posts( $args = array() ) {
	$args = wp_parse_args( $args, array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 5, 'numberposts' => null, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'all', 'post__not_in' => array(), 'exclude' => array() ) );
	$types = (array) $args['post_type']; $status = (array) $args['post_status'];
	$out = array();
	foreach ( $GLOBALS['omens_test']['posts'] as $p ) {
		if ( ! in_array( $p->post_type, $types, true ) && ! in_array( 'any', $types, true ) ) { continue; }
		if ( ! in_array( $p->post_status, $status, true ) && ! in_array( 'any', $status, true ) ) { continue; }
		if ( in_array( $p->ID, (array) $args['post__not_in'], true ) || in_array( $p->ID, (array) $args['exclude'], true ) ) { continue; }
		$out[] = $p;
	}
	$key = 'modified' === $args['orderby'] ? 'post_modified' : ( 'ID' === $args['orderby'] ? 'ID' : 'post_date' );
	usort( $out, function ( $a, $b ) use ( $key, $args ) { $c = strcmp( (string) $a->$key, (string) $b->$key ); return 'ASC' === strtoupper( $args['order'] ) ? $c : -$c; } );
	$n = null !== $args['numberposts'] ? (int) $args['numberposts'] : (int) $args['posts_per_page'];
	if ( $n > 0 ) { $out = array_slice( $out, 0, $n ); }
	if ( 'ids' === $args['fields'] ) { return array_map( function ( $p ) { return $p->ID; }, $out ); }
	return $out;
}
function get_post_types( $args = array(), $output = 'names' ) {
	$types = array_keys( $GLOBALS['omens_test']['post_types'] );
	if ( ! empty( $args['public'] ) || ! empty( $args['publicly_queryable'] ) ) { $types = array_values( array_filter( $types, function ( $t ) { return 'attachment' !== $t || empty( $GLOBALS['omens_test']['post_types_private'][ $t ] ); } ) ); }
	return array_combine( $types, $types );
}
function url_to_postid( $url ) {
	$path = trim( (string) parse_url( $url, PHP_URL_PATH ), '/' );
	if ( '' === $path ) { return 0; }
	foreach ( $GLOBALS['omens_test']['posts'] as $p ) { if ( $p->post_name === $path && 'attachment' !== $p->post_type ) { return $p->ID; } }
	return 0;
}
function attachment_url_to_postid( $url ) {
	$base = preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', (string) $url );
	foreach ( $GLOBALS['omens_test']['posts'] as $p ) { if ( 'attachment' === $p->post_type && ( $p->guid === $url || $p->guid === $base ) ) { return $p->ID; } }
	return 0;
}
function wp_get_attachment_url( $id ) { $p = get_post( $id ); return $p ? $p->guid : false; }
function get_page_for_privacy_policy() { return (int) get_option( 'wp_page_for_privacy_policy' ); }
function strip_shortcodes( $s ) { return preg_replace( '/\[[^\]]*\]/', '', (string) $s ); }
function post_password_required( $p ) { return false; }
function wp_upload_dir() { $b = $GLOBALS['omens_test']['uploads'] ?: WP_CONTENT_DIR . '/uploads'; return array( 'basedir' => $b, 'baseurl' => home_url( '/wp-content/uploads' ), 'error' => false ); }

// --- Users --------------------------------------------------------------------
class WP_User {
	public $ID; public $user_login; public $user_email; public $user_registered; public $roles = array(); public $caps = array();
	public function __construct( $d ) { foreach ( $d as $k => $v ) { $this->$k = $v; } }
	public function has_cap( $c ) { foreach ( $this->roles as $r ) { if ( ! empty( $GLOBALS['omens_test']['roles'][ $r ]['capabilities'][ $c ] ) ) { return true; } } return false; }
}
function omens_test_add_user( array $u ) {
	static $next = 1;
	$id = (int) ( $u['ID'] ?? $next++ );
	$GLOBALS['omens_test']['users'][ $id ] = new WP_User( array_merge( array( 'ID' => $id, 'user_login' => 'user' . $id, 'user_email' => 'user' . $id . '@example.test', 'user_registered' => '2024-01-01 00:00:00', 'roles' => array( 'administrator' ) ), $u ) );
	if ( isset( $u['meta'] ) ) { foreach ( $u['meta'] as $k => $v ) { $GLOBALS['omens_test']['usermeta'][ $id ][ $k ] = $v; } }
	return $id;
}
function get_users( $args = array() ) {
	$args = wp_parse_args( $args, array( 'role__in' => array(), 'role' => '', 'fields' => 'all' ) );
	$roles = $args['role__in'] ? (array) $args['role__in'] : ( $args['role'] ? array( $args['role'] ) : array() );
	$out = array();
	foreach ( $GLOBALS['omens_test']['users'] as $u ) { if ( ! $roles || array_intersect( $roles, $u->roles ) ) { $out[] = $u; } }
	return $out;
}
function get_userdata( $id ) { return $GLOBALS['omens_test']['users'][ (int) $id ] ?? false; }
function get_user_by( $f, $v ) { foreach ( $GLOBALS['omens_test']['users'] as $u ) { if ( 'login' === $f && $u->user_login === $v ) { return $u; } } return false; }
function get_user_meta( $id, $key = '', $single = false ) { $m = $GLOBALS['omens_test']['usermeta'][ (int) $id ] ?? array(); if ( '' === $key ) { return $m; } if ( ! array_key_exists( $key, $m ) ) { return $single ? '' : array(); } return $single ? $m[ $key ] : array( $m[ $key ] ); }
function user_can( $u, $cap ) { $u = is_object( $u ) ? $u : get_userdata( $u ); return $u ? $u->has_cap( $cap ) : false; }
function current_user_can( $cap ) { $u = wp_get_current_user(); return $u ? $u->has_cap( $cap ) : false; }
function wp_get_current_user() { $id = $GLOBALS['omens_test']['current_user']; return $id ? get_userdata( $id ) : new WP_User( array( 'ID' => 0, 'user_email' => '' ) ); }
class WP_Roles_Stub { public $roles; public function __construct() { $this->roles = $GLOBALS['omens_test']['roles']; } }
function wp_roles() { return new WP_Roles_Stub(); }
class WP_Role_Stub {
	private $caps;
	public function __construct( $caps ) { $this->caps = (array) $caps; }
	public function has_cap( $cap ) { return ! empty( $this->caps[ $cap ] ); }
}
function get_role( $slug ) {
	$roles = $GLOBALS['omens_test']['roles'];
	return isset( $roles[ $slug ] ) ? new WP_Role_Stub( $roles[ $slug ]['capabilities'] ?? array() ) : null;
}
function count_users() {
	$avail = array();
	foreach ( $GLOBALS['omens_test']['users'] as $u ) { foreach ( (array) $u->roles as $r ) { $avail[ $r ] = ( $avail[ $r ] ?? 0 ) + 1; } }
	return array( 'total_users' => count( $GLOBALS['omens_test']['users'] ), 'avail_roles' => $avail );
}

// --- Theme, plugins -----------------------------------------------------------
class WP_Theme_Stub {
	private $d;
	public function __construct( $d ) { $this->d = $d; }
	public function get( $h ) { $map = array( 'Name' => 'name', 'Version' => 'version', 'Author' => 'author', 'AuthorURI' => 'author_uri', 'TextDomain' => 'stylesheet' ); return (string) ( $this->d[ $map[ $h ] ?? strtolower( $h ) ] ?? '' ); }
	public function get_stylesheet() { return $this->d['stylesheet']; }
	public function get_template() { return $this->d['parent'] ? $this->d['parent']['stylesheet'] : $this->d['stylesheet']; }
	public function parent() { return $this->d['parent'] ? new WP_Theme_Stub( $this->d['parent'] ) : false; }
	public function get_stylesheet_directory() { return $this->d['dir'] ?: WP_CONTENT_DIR . '/themes/' . $this->d['stylesheet']; }
	public function get_template_directory() { return $this->d['parent'] ? ( $this->d['parent']['dir'] ?? WP_CONTENT_DIR . '/themes/' . $this->d['parent']['stylesheet'] ) : $this->get_stylesheet_directory(); }
}
function wp_get_theme( $s = null ) { return new WP_Theme_Stub( $GLOBALS['omens_test']['theme'] ); }
function get_stylesheet_directory() { return wp_get_theme()->get_stylesheet_directory(); }
function get_template_directory() { return wp_get_theme()->get_template_directory(); }
function get_stylesheet() { return wp_get_theme()->get_stylesheet(); }
function get_template() { return wp_get_theme()->get_template(); }
function get_plugins( $folder = '' ) { return $GLOBALS['omens_test']['plugins']; }
function get_mu_plugins() { return $GLOBALS['omens_test']['mu_plugins']; }
function is_plugin_active( $f ) { return in_array( $f, (array) get_option( 'active_plugins', array() ), true ); }
function get_plugin_data( $f ) { return array(); }

// --- DB -------------------------------------------------------------------------
class WPDB_Stub {
	public $prefix = 'wp_'; public $usermeta = 'wp_usermeta'; public $users = 'wp_users'; public $postmeta = 'wp_postmeta'; public $posts = 'wp_posts';
	public function get_var( $q ) {
		if ( preg_match( "/SHOW TABLES LIKE '([^']+)'/", $q, $m ) ) { return in_array( $m[1], $GLOBALS['omens_test']['db_tables'], true ) ? $m[1] : null; }
		return null;
	}
	public function get_col( $q ) {
		if ( preg_match( '/FROM\s+(\S+)/', $q, $m ) && isset( $GLOBALS['omens_test']['db_rows'][ $m[1] ] ) ) { return array_map( function ( $r ) { return reset( $r ); }, $GLOBALS['omens_test']['db_rows'][ $m[1] ] ); }
		return array();
	}
	public function prepare( $q, ...$a ) { foreach ( $a as $v ) { $q = preg_replace( '/%[sd]/', is_int( $v ) ? (string) $v : "'" . $v . "'", $q, 1 ); } return $q; }
	public function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }
}
$GLOBALS['wpdb'] = new WPDB_Stub();

// --- ACF (only when a fixture turns it on) ---------------------------------------
function omens_test_enable_acf() {
	if ( ! function_exists( 'get_fields' ) ) {
		eval( 'function get_fields( $id = false, $format = true ) { $k = "option" === $id || "options" === $id ? "option" : (int) $id; return $GLOBALS["omens_test"]["acf"][ $k ] ?? array(); }' );
		eval( 'function get_field( $name, $id = false, $format = true ) { $f = get_fields( $id ); return $f[ $name ] ?? null; }' );
	}
}
