<?php
/**
 * The monthly WP-Cron fallback. The central run is the scheduler and calls
 * `wp omens-audit run` explicitly; this exists so the latest run on the site is
 * never older than a month if the central run skips it. On Pressable the
 * platform runs due events every few minutes whether or not anyone visits
 * (spike S3), so this fires on time.
 *
 * @package OmensAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OMENS_AUDIT_CRON_HOOK = 'omens_audit_monthly_run';

add_filter(
	'cron_schedules', // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- monthly, on purpose.
	function ( $schedules ) {
		$schedules['omens_audit_monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => 'Every 30 days (Omens Site Audit)',
		);
		return $schedules;
	}
);

add_action(
	OMENS_AUDIT_CRON_HOOK,
	function () {
		omens_audit_run();
	}
);

/**
 * Whether the fallback schedule is wanted on this site.
 *
 * @return bool
 */
function omens_audit_schedule_enabled() {
	/**
	 * Turn the monthly WP-Cron fallback off. Default on.
	 *
	 * @param bool $enabled Schedule the fallback.
	 */
	return (bool) apply_filters( 'omens_audit/schedule', true );
}

/**
 * Activation: schedule the fallback (first run a day out, so a fresh install
 * does not audit itself mid-setup).
 */
function omens_audit_activate() {
	if ( omens_audit_schedule_enabled() && ! wp_next_scheduled( OMENS_AUDIT_CRON_HOOK ) ) {
		wp_schedule_event( time() + DAY_IN_SECONDS, 'omens_audit_monthly', OMENS_AUDIT_CRON_HOOK );
	}
}

/**
 * Deactivation: unschedule. The stored run stays until uninstall.
 */
function omens_audit_deactivate() {
	wp_clear_scheduled_hook( OMENS_AUDIT_CRON_HOOK );
}

// Keep the schedule true to the filter on every request: a site that filters
// it off after activation loses the event, and a site whose event was lost (a
// cleared cron option, a database copied without it) gets it back without
// having to deactivate and reactivate.
add_action(
	'init',
	function () {
		$next = wp_next_scheduled( OMENS_AUDIT_CRON_HOOK );
		if ( omens_audit_schedule_enabled() && ! $next ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'omens_audit_monthly', OMENS_AUDIT_CRON_HOOK );
		} elseif ( ! omens_audit_schedule_enabled() && $next ) {
			wp_clear_scheduled_hook( OMENS_AUDIT_CRON_HOOK );
		}
	}
);
