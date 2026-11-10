<?php
/**
 * Plugin Name:       Omens Site Audit
 * Plugin URI:        https://github.com/omens-agency/omens-wp-plugin-audit
 * Update URI:        https://github.com/omens-agency/omens-wp-plugin-audit
 * Description:       Checks this site for security, search, accessibility, and privacy issues and reports them to Omens. It changes nothing on your pages.
 * Version:           1.0.8
 * Requires at least: 6.4
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * Author:            Omens Agency
 * Author URI:        https://omens.agency
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       omens-audit
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Guarded so a second copy (a "-main" folder beside the release folder, the
// case the Plugins-screen notice in includes/updates.php is for) warns about
// nothing and the first copy's values stand.
defined( 'OMENS_AUDIT_VERSION' ) || define( 'OMENS_AUDIT_VERSION', '1.0.8' );
defined( 'OMENS_AUDIT_FILE' ) || define( 'OMENS_AUDIT_FILE', __FILE__ );
defined( 'OMENS_AUDIT_DIR' ) || define( 'OMENS_AUDIT_DIR', plugin_dir_path( __FILE__ ) );
defined( 'OMENS_AUDIT_SCHEMA' ) || define( 'OMENS_AUDIT_SCHEMA', 1 );
defined( 'OMENS_AUDIT_OPTION' ) || define( 'OMENS_AUDIT_OPTION', 'omens_audit_last_run' );

require_once OMENS_AUDIT_DIR . 'includes/finding.php';
require_once OMENS_AUDIT_DIR . 'includes/check.php';
require_once OMENS_AUDIT_DIR . 'includes/check-base.php';
require_once OMENS_AUDIT_DIR . 'includes/context.php';
require_once OMENS_AUDIT_DIR . 'includes/site.php';
require_once OMENS_AUDIT_DIR . 'includes/html.php';
require_once OMENS_AUDIT_DIR . 'includes/routing.php';
require_once OMENS_AUDIT_DIR . 'includes/seo-plugin.php';
require_once OMENS_AUDIT_DIR . 'includes/inventory.php';
require_once OMENS_AUDIT_DIR . 'includes/registry.php';
require_once OMENS_AUDIT_DIR . 'includes/state.php';
require_once OMENS_AUDIT_DIR . 'includes/run.php';
require_once OMENS_AUDIT_DIR . 'includes/cron.php';
require_once OMENS_AUDIT_DIR . 'includes/admin.php';
require_once OMENS_AUDIT_DIR . 'includes/updates.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once OMENS_AUDIT_DIR . 'includes/cli.php';
}

register_activation_hook( __FILE__, 'omens_audit_activate' );
register_deactivation_hook( __FILE__, 'omens_audit_deactivate' );
