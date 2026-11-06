<?php
/**
 * Tools > Site Audit: the latest run, read-only, plus a nonce-checked
 * "Run now". No settings: configuration is by filter, in code.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capability for the screen and the run button.
 *
 * @return string
 */
function omens_audit_capability() {
	/**
	 * Capability required to see Tools > Site Audit and press Run now.
	 *
	 * @param string $cap Default manage_options.
	 */
	return (string) apply_filters( 'omens_audit/capability', 'manage_options' );
}

/**
 * Whether the current user may see the screen at all. The default on a
 * client site is Omens accounts only; omens.agency itself opens it to every
 * administrator (decision 12, 2026-11-06). A site changes the domains or the
 * rule through the two filters.
 *
 * @return bool
 */
function omens_audit_show_admin() {
	if ( ! current_user_can( omens_audit_capability() ) ) {
		return false;
	}
	$user   = wp_get_current_user();
	$domain = strtolower( (string) substr( strrchr( (string) $user->user_email, '@' ), 1 ) );
	/**
	 * Email domains whose users see the screen by default.
	 *
	 * @param string[] $domains Default omens.agency and threeomens.com.
	 */
	$domains = (array) apply_filters( 'omens_audit/admin_domains', array( 'omens.agency', 'threeomens.com' ) );
	$default = in_array( $domain, array_map( 'strtolower', $domains ), true ) || omens_audit_host() === 'omens.agency';
	/**
	 * Whether this user sees Tools > Site Audit.
	 *
	 * @param bool    $show Default: Omens account, or this is omens.agency.
	 * @param WP_User $user Current user.
	 */
	return (bool) apply_filters( 'omens_audit/show_admin', $default, $user );
}

add_action(
	'admin_menu',
	function () {
		if ( ! omens_audit_show_admin() ) {
			return;
		}
		add_management_page( 'Site Audit', 'Site Audit', omens_audit_capability(), 'omens-audit', 'omens_audit_render_screen' );
	}
);

add_action(
	'admin_post_omens_audit_run',
	function () {
		if ( ! omens_audit_show_admin() ) {
			wp_die( 'Not allowed.', 403 );
		}
		check_admin_referer( 'omens_audit_run' );
		omens_audit_run();
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'omens-audit',
					'ran'  => 1,
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}
);

/**
 * Render the screen.
 */
function omens_audit_render_screen() {
	if ( ! omens_audit_show_admin() ) {
		wp_die( 'Not allowed.', 403 );
	}
	$reason = '';
	$run    = omens_audit_get_run( $reason );
	echo '<div class="wrap"><h1>Site Audit</h1>';
	if ( isset( $_GET['ran'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		echo '<div class="notice notice-success"><p>Audit run complete.</p></div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:1em 0">';
	echo '<input type="hidden" name="action" value="omens_audit_run">';
	wp_nonce_field( 'omens_audit_run' );
	submit_button( 'Run now', 'primary', 'submit', false );
	echo ' <span class="description">Runs every check and replaces the stored run. The central maintenance audit normally does this; the button is for checking a fix.</span>';
	echo '</form>';

	if ( ! $run ) {
		echo '<p>' . esc_html( $reason ? $reason : 'No run yet.' ) . '</p></div>';
		return;
	}

	printf(
		'<p><strong>%s</strong> on %s (%s, %s build) · plugin %s · %d checks · %d findings in %s s</p>',
		esc_html( $run['run_at'] ),
		esc_html( $run['host'] ),
		esc_html( $run['environment'] ),
		esc_html( $run['build_type'] ),
		esc_html( $run['plugin_version'] ),
		count( $run['checks'] ),
		(int) $run['counts']['total'],
		esc_html( number_format_i18n( $run['duration_ms'] / 1000, 1 ) )
	);

	echo '<h2>Counts</h2><table class="widefat striped" style="max-width:40em"><thead><tr><th>Severity</th><th>Findings</th></tr></thead><tbody>';
	foreach ( $run['counts']['severity'] as $sev => $n ) {
		echo '<tr><td>' . esc_html( $sev ) . '</td><td>' . (int) $n . '</td></tr>';
	}
	echo '</tbody></table>';
	if ( $run['counts']['family'] ) {
		echo '<table class="widefat striped" style="max-width:40em;margin-top:1em"><thead><tr><th>Family</th><th>Findings</th></tr></thead><tbody>';
		foreach ( $run['counts']['family'] as $fam => $n ) {
			echo '<tr><td>' . esc_html( $fam ) . '</td><td>' . (int) $n . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	echo '<h2>Findings</h2>';
	if ( ! $run['findings'] ) {
		echo '<p>None.</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>Severity</th><th>Check</th><th>Finding</th><th>Where</th><th>Route</th></tr></thead><tbody>';
		foreach ( $run['findings'] as $f ) {
			echo '<tr>';
			echo '<td>' . esc_html( $f['severity'] ) . '</td>';
			echo '<td><code>' . esc_html( $f['check'] ) . '</code></td>';
			echo '<td><strong>' . esc_html( $f['title'] ) . '</strong><br>' . esc_html( $f['summary'] );
			if ( ! empty( $f['fix']['steps'] ) ) {
				echo '<ol style="margin:.5em 0 0 1.2em">';
				foreach ( $f['fix']['steps'] as $step ) {
					echo '<li>' . esc_html( $step ) . '</li>';
				}
				echo '</ol>';
			}
			echo '</td>';
			echo '<td>';
			if ( ! empty( $f['url'] ) ) {
				echo '<a href="' . esc_url( $f['url'] ) . '">' . esc_html( $f['url'] ) . '</a><br>';
			}
			echo esc_html( $f['fix']['where'] ) . '</td>';
			echo '<td>' . esc_html( $f['fix']['route'] ) . ' <span class="description">(' . esc_html( $f['fix']['confidence'] ) . ')</span></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	echo '<h2>Sample</h2><ol>';
	foreach ( $run['sample'] as $url ) {
		echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>' . ( in_array( $url, $run['fetched'], true ) ? '' : ' <span class="description">(not fetched this run)</span>' ) . '</li>';
	}
	echo '</ol>';

	echo '<h2>Checks</h2><table class="widefat striped" style="max-width:50em"><thead><tr><th>Check</th><th>Version</th><th>Status</th><th>ms</th></tr></thead><tbody>';
	foreach ( $run['checks'] as $id => $c ) {
		echo '<tr><td><code>' . esc_html( $id ) . '</code></td><td>' . esc_html( $c['version'] ) . '</td><td>' . esc_html( $c['status'] ) . '</td><td>' . (int) $c['ms'] . '</td></tr>';
	}
	echo '</tbody></table></div>';
}
