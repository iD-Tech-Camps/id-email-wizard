<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_filter('template_include', 'idemailwiz_template_chooser');

/**
 * Choose the appropriate template based on the current page/post.
 *
 * @param string $template The current template path.
 * @return string The updated template path.
 */
function idemailwiz_template_chooser($template)
{
    $plugin_dir = plugin_dir_path(dirname(__FILE__)); // Get the plugin directory path
    $template_dir = $plugin_dir . 'templates/';

    // Custom page templates
    $custom_pages = [
        'playground' => 'wiz-playground.php',
        'subject-line-builder' => 'subject-line-builder.php',
        'wiz-rest' => 'wiz-rest-ui.php',
    ];

    foreach ($custom_pages as $page => $file) {
        if (is_page($page) || strpos($_SERVER['REQUEST_URI'], "/$page") !== false) {
            return $template_dir . $file;
        }
    }

    // Custom post type and taxonomy templates
    if (is_singular('idemailwiz_template')) {
        if (is_wiz_sms_template(get_queried_object_id())) {
            return $plugin_dir . 'builder-v2/single-idemailwiz_template-sms.php';
        }
        return $plugin_dir . 'builder-v2/single-idemailwiz_template-v2.php';
    }
    $post_type_templates = [
        'idwiz_initiative' => 'single-initiative.php',
        'wiz_promo_code' => 'single-promo-code.php',
        'idwiz_comparison' => 'single-comparison.php',
        'wysiwyg_snippet' => 'single-snippet.php',
        'wysiwyg_interactive' => 'single-interactive.php',
    ];

    $post_type = get_post_type();
    if (isset($post_type_templates[$post_type]) && is_single()) {
        return $template_dir . $post_type_templates[$post_type];
    }

    if (is_post_type_archive('idemailwiz_template') || is_tax('idemailwiz_folder')) {
        return $template_dir . 'taxonomy-idemailwiz_folder.php';
    }

    // Template search (/templates/search/{q}/) reuses the folder archive layout.
    if (get_query_var('idwiz_q')) {
        return $template_dir . 'taxonomy-idemailwiz_folder.php';
    }


    // Custom URI-based templates
    $uri_templates = [
        '/metrics/campaign' => 'single-campaign.php',
        '/journeys' => 'archive-journeys.php',
        '/metrics/journey' => 'single-journey.php',
        '/snippets' => 'archive-snippet.php',
        '/interactives' => 'archive-interactive.php',
        '/endpoints' => 'wiz-rest-ui.php'
    ];

    foreach ($uri_templates as $uri => $file) {
        if (strpos($_SERVER['REQUEST_URI'], $uri) !== false) {
            return $template_dir . $file;
        }
    }

    // Custom endpoint templates
    $endpoint_templates = [
        'user-profile' => 'user-profile.php',

        'sync-station' => 'sync-station.php',
        'campaign-monitor' => 'campaign-monitor.php',
        'course-mapping' => 'page-course-mapping.php',
    ];

    foreach ($endpoint_templates as $endpoint => $file) {
        if (isset($GLOBALS['wp_query']->query_vars[$endpoint])) {
            return $template_dir . $file;
        }
    }

    // Iterable triggered send endpoint
    if (isset($GLOBALS['wp_query']->query_vars['endpoints/iterable-triggeredSend'])) {
        return $plugin_dir . 'endpoints/iterable-triggeredSend.php';
    }

    // Templates based on plugin settings
    $options = get_option('idemailwiz_settings');
    $setting_templates = [
        'dashboard_page' => 'dashboard.php',
        'campaigns_page' => 'campaigns-table.php',
        'experiments_page' => 'experiments.php',
        'reports_page' => 'reports.php',
    ];

    foreach ($setting_templates as $setting => $file) {
        $page_id = isset($options[$setting]) ? $options[$setting] : '';
        if ($page_id && is_page($page_id)) {
            return $template_dir . $file;
        }
    }

    return $template;
}
