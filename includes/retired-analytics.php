<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * The Wizard's analytics, data feeds, course/location syncs and reports moved to the Email Analyzer app
 * (https://id-email-analyzer.vercel.app); the Wizard is the template builder now. Removing their code
 * doesn't unschedule their WP-Cron jobs, so this does it once. Their database tables are left in place
 * for now (nothing reads them any more).
 */
add_action('init', 'idemailwiz_unschedule_retired_jobs');
function idemailwiz_unschedule_retired_jobs()
{
    if (get_option('idemailwiz_retired_jobs_cleared') === '1') {
        return;
    }

    $hooks = array(
        // Iterable sync
        'idemailwiz_process_blast_sync',
        'idemailwiz_sync_engagement_data',
        'idemailwiz_fill_sync_queue',
        'idemailwiz_sync_queue_cleanup_event',
        'idemailwiz_process_campaign_export_batch',
        'idemailwiz_export_and_queue_single_job_event',
        'idemailwiz_sync_users',
        'idemailwiz_weekly_send_sync',
        'update_journey_send_times_hook',
        'add_new_workflows_daily_hook',
        'idwiz_timeline_warm_retained',
        // Pulse catalog and seats
        'wizPulse_refresh_locations_cron',
        'wizPulse_refresh_courses_cron',
        'wizPulse_course_capacity_sync_cron',
        // Housekeeping for the above
        'wiz_log_trim_cron',
        'idemailwiz_custom_transient_cleanup',
    );
    foreach ($hooks as $hook) {
        // Clears every scheduled run of the hook, whatever arguments it was scheduled with.
        wp_unschedule_hook($hook);
    }

    update_option('idemailwiz_retired_jobs_cleared', '1');
}
