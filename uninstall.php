<?php
/**
 * Uninstall: remove the stored run and the cron event. Nothing else was created.
 *
 * @package OmensAudit
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'omens_audit_last_run' );
wp_clear_scheduled_hook( 'omens_audit_monthly_run' );
