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

    // Custom post type and taxonomy templates
    if (is_singular('idemailwiz_template')) {
        if (is_wiz_sms_template(get_queried_object_id())) {
            return $plugin_dir . 'builder-v2/single-idemailwiz_template-sms.php';
        }
        return $plugin_dir . 'builder-v2/single-idemailwiz_template-v2.php';
    }
    $post_type_templates = [
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
        '/snippets' => 'archive-snippet.php',
        '/interactives' => 'archive-interactive.php',
    ];

    foreach ($uri_templates as $uri => $file) {
        if (strpos($_SERVER['REQUEST_URI'], $uri) !== false) {
            return $template_dir . $file;
        }
    }

    return $template;
}
