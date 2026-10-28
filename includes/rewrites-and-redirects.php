<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//Custom redirects
function redirect_template_archive_url()
{
    // The Wizard is the template builder now: the home page and the old analytics pages (moved to the
    // Email Analyzer app) open the template list. Page views only: the builder's AJAX and preview frame pass.
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !get_query_var('idwiz_ajax_endpoint') && !isset($GLOBALS['wp_query']->query_vars['template-frame'])) {
        $homePath = trim((string) parse_url(home_url(), PHP_URL_PATH), '/');
        $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        if ($homePath !== '' && strpos($path . '/', $homePath . '/') === 0) {
            $path = trim(substr($path, strlen($homePath)), '/');
        }
        $retiredPaths = '#^(metrics|journeys|reports|campaigns|experiments|dashboard|initiatives?|comparisons?|promo-codes?|sync-station|campaign-monitor|course-mapping|user-profile|endpoints|playground|subject-line-builder|wiz-rest)(/|$)#';
        $options = get_option('idemailwiz_settings', array());
        $retiredPageIds = array_values(array_filter(array_map(fn($key) => (int) ($options[$key] ?? 0), array('dashboard_page', 'campaigns_page', 'reports_page', 'experiments_page'))));
        if (($path === '' && empty($_GET)) || preg_match($retiredPaths, $path) || ($retiredPageIds && is_page($retiredPageIds))) {
            wp_redirect(site_url('/templates/all'), 302);
            exit;
        }
    }

    //If someone lands on /templates, redirect them to /templates/all
    if (isset($_SERVER['REQUEST_URI']) && trim($_SERVER['REQUEST_URI'], '/') == 'templates') {
        wp_redirect(site_url('/templates/all'), 301);
        exit;
    }

    //Redirect any weird/wrong template URLs to the proper/current URL (handles cases where the slug changed but an old link was used)
    // if (is_singular('idemailwiz_template')) {
    //     $post_slug = $post->post_name;
    //     if ( isset( $_SERVER['REQUEST_URI'] ) && $_SERVER['REQUEST_URI'] != "/template/{$post->ID}/{$post_slug}/" ) {
    // 		wp_redirect( home_url( "/template/{$post->ID}/{$post_slug}/" ) . $_SERVER['QUERY_STRING'], 301 );
    // 		exit;
    // 	}

    // }
}
add_action('template_redirect', 'redirect_template_archive_url', 11);



// Custom rewrite rules and endpoints
function idemailwiz_custom_rewrite_rule()
{
    // Template editor rewrite
    add_rewrite_rule('^template/([0-9]+)/([^/]+)/?', 'index.php?post_type=idemailwiz_template&p=$matches[1]', 'top');

    // Builder preview iframe
    add_rewrite_endpoint('template-frame', EP_ROOT);

    // Custom ajax endpoint
    add_rewrite_rule('^idwiz-ajax/?$', 'index.php?idwiz_ajax_endpoint=1', 'top');
    add_rewrite_tag('%idwiz_ajax_endpoint%', '([0-9]+)');

    // Pretty URL for template search: /templates/search/{query}/
    add_rewrite_tag('%idwiz_q%', '([^/]+)');
    add_rewrite_rule(
        '^templates/search/([^/]+)/?$',
        'index.php?post_type=idemailwiz_template&idwiz_q=$matches[1]',
        'top'
    );
}

add_action('init', 'idemailwiz_custom_rewrite_rule', 10);

// Auto-flush rewrite rules when the template-search rule is first introduced
// or when its version bumps, so users don't need to manually re-save permalinks.
add_action('init', function () {
    $current_version = '2'; // 2: analytics endpoints removed
    if (get_option('idwiz_rewrite_version') !== $current_version) {
        flush_rewrite_rules(false);
        update_option('idwiz_rewrite_version', $current_version);
    }
}, 99);

// Add query var for endpoint parameter
add_filter('query_vars', function($vars) {
    $vars[] = 'endpoint';
    $vars[] = 'idwiz_q';
    return $vars;
});

// Custom permalinks for wiz templates
add_filter('post_type_link', 'custom_template_permalink', 10, 2);
function custom_template_permalink($post_link, $post)
{
    if ($post->post_type === 'idemailwiz_template') {
        return home_url("/template/{$post->ID}/" . $post->post_name . '/');
    }
    return $post_link;
}