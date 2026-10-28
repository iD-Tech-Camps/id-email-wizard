<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Register custom post types
add_action('init', 'idwiz_register_custom_post_types', 0);
function idwiz_register_custom_post_types()
{
    $templateLabels = array(
        'name' => 'Templates',
        'singular_name' => 'Template',
        // Add other labels as needed
    );

    $templateArgs = array(
        'labels' => $templateLabels,
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
        'show_in_rest' => true, // This is required if you want to use this post type with Gutenberg
        'rewrite' => array(
            'slug' => 'template', // This is the base slug for your templates
            'with_front' => false, // This ensures that the slug is exactly what you specify, not prepended with a front base
        ),
    );




    register_post_type('idemailwiz_template', $templateArgs);

    register_post_type('wysiwyg_snippet', [
        'labels' => ['name' => __('Snippets'), 'singular_name' => __('Snippet')],
        'public' => true,
        'has_archive' => 'snippets',
        'rewrite' => ['slug' => 'snippet'],
        'supports' => ['title', 'editor', 'custom-fields'],
        'delete_with_user' => false,
        'capability_type' => 'post',
        'show_in_rest' => true,
        'show_in_menu' => 'edit.php?post_type=idemailwiz_template',
    ]);

    register_post_type('wysiwyg_interactive', [
        'labels' => ['name' => __('Interactives'), 'singular_name' => __('Interactive')],
        'public' => true,
        'has_archive' => 'interactives',
        'rewrite' => ['slug' => 'interactive'],
        'supports' => ['title', 'editor', 'custom-fields'],
        'delete_with_user' => false,
        'capability_type' => 'post',
        'show_in_rest' => true,
        'show_in_menu' => 'edit.php?post_type=idemailwiz_template',
        'has_archive' => 'interactives',
    ]);
}

// The idemailwiz_folder taxonomy is registered in includes/folders/taxonomy.php.