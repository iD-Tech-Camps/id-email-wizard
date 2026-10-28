<?php
/**
 * Plugin Name: iD Email Wizard
 * Plugin URI: https://idtech.com
 * Description: This plugin provides an interface for designing and exporting email template HTML.
 * Version: 1.0
 * Author: Zac Eckstein for iD Tech
 * License: Private
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//define the path to the plugin file
define('IDEMAILWIZ_ROOT', __FILE__);

// Set memory and timeout limits
ini_set('memory_limit', '256M');
set_time_limit(300);


// Plugin Activation
register_activation_hook(__FILE__, 'idemailwiz_activate');
function idemailwiz_activate()
{

    //Create custom databases
    idemailwiz_create_databases();

    // Schedule an event to run on the next page load
    wp_schedule_single_event(time(), 'idemailwiz_on_next_page_load');

    //flush permalinks
    flush_rewrite_rules();

}

//delayed activation so it's after init
add_action('idemailwiz_on_next_page_load', 'idemailwiz_post_activate');
function idemailwiz_post_activate()
{
    //get the term id of the default term in the folder taxonomy
    idemailwiz_set_root_folder();
    //set the trash term
    idemailwiz_set_trash_term();
    //flush permalinks
    flush_rewrite_rules();
}

// Deactivation
register_deactivation_hook(__FILE__, 'idemailwiz_deactivate');
function idemailwiz_deactivate()
{
    flush_rewrite_rules();
}

//require files
$files = [
    'includes/functions.php',
    'includes/databases.php',
    'includes/types-and-taxes.php',
    'includes/rewrites-and-redirects.php',
    'includes/template-router.php',
    'includes/wiz-ajax.php',
    'includes/wizSnippets.php',
    'builder-v2/interactives/interactives.php',
    'builder-v2/template-request-handler.php',
    'builder-v2/template-get-and-save.php',
    'builder-v2/builder-functions.php',
    'builder-v2/builder-parts.php',
    'builder-v2/wiz-modals.php',
    'builder-v2/template-parts.php',
    'builder-v2/template-data.php',
    'builder-v2/image-handling.php',
    'builder-v2/chunks.php',
    'builder-v2/chunk-helpers.php',
    'builder-v2/wysiwyg-utils.php',
    'builder-v2/sms-template.php',
    'includes/folders/folders.php',
    'includes/iterable-functions.php',
    'includes/retired-analytics.php',
];

foreach ($files as $file) {
    require_once(plugin_dir_path(__FILE__) . $file);
}

//Options page
include(plugin_dir_path(__FILE__) . 'includes/wiz-options.php');



//Enqueue stuff
add_action('wp_enqueue_scripts', 'idemailwiz_enqueue_assets');
function idemailwiz_enqueue_assets()
{
    wp_enqueue_script('jquery');
    wp_enqueue_script('wiz-polyfill', plugin_dir_url(__FILE__) . 'js/wiz-polyfills.js', array('jquery'), '1.0', true);

    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0', true);
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array(), '4.1.0', true);

    wp_enqueue_script('jquery-ui');
    wp_enqueue_script('jquery-ui-sortable', null, array('jquery'));
    wp_enqueue_script('jquery-ui-resizable', null, array('jquery', 'jquery-ui'));

    wp_enqueue_script('sortable-js', 'https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js', array(), null, true);

    wp_enqueue_script('crush', 'https://cdn.jsdelivr.net/npm/html-crush/dist/html-crush.umd.js', array(), null, true);

    wp_enqueue_script('html2canvas', plugin_dir_url(__FILE__) . 'vendors/html2canvas/html2canvas.min.js', array(), '1.4.1', true);

    wp_enqueue_script('spectrum', plugin_dir_url(__FILE__) . 'vendors/spectrum/spectrum.js', array());

    wp_enqueue_script('tinymce', plugin_dir_url(__FILE__) . 'vendors/tinymce/js/tinymce/tinymce.min.js');

    wp_enqueue_script('editable', plugin_dir_url(__FILE__) . 'vendors/tiny-edit-in-place/jquery.editable.min.js', array());

    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

    wp_enqueue_style('spectrum-styles', plugin_dir_url(__FILE__) . 'vendors/spectrum/spectrum.css', array());

    wp_enqueue_style('select2css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array());
    wp_enqueue_style('font-awesome-6', plugin_dir_url(__FILE__) . 'vendors/Font Awesome/css/all.css', array());

    // Activate wordpress image uploader for settings pages
    if (isset($_GET['page']) && $_GET['page'] == 'idemailwiz_settings') {
        wp_enqueue_media();
        wp_enqueue_script('idemailwiz-image-upload', plugin_dir_url(__FILE__) . 'js/image-upload.js', array('jquery'), null, true);
    }

    $codemirror_path = plugin_dir_url(__FILE__) . 'vendors/codemirror-5.65.16/';

    $codemirror_files = array(

        array('codemirror', 'lib/codemirror.js', array('jquery'), '', true),
        array('codemirror-mode-css', 'mode/css/css.js', array('jquery', 'codemirror'), '', true),
        array('codemirror-lint', 'addon/lint/lint.js', array('jquery', 'codemirror'), '', true),
        array('codemirror-lint-css', 'addon/lint/css-lint.js', array('jquery', 'codemirror', 'codemirror-lint'), '', true),
        array('htmlhint', 'addon/hint/html-hint.js', array(), '1.1.4', true),
        array('codemirror-lint-html', 'addon/lint/html-lint.js', array('jquery', 'codemirror', 'codemirror-lint', 'htmlhint'), '', true),
        array('codemirror-addon-hint', 'addon/hint/show-hint.js', array('jquery', 'codemirror'), '', true),
        array('codemirror-addon-hint-css', 'addon/hint/css-hint.js', array('jquery', 'codemirror', 'codemirror-addon-hint'), '', true),
        array('codemirror-mode-xml', 'mode/xml/xml.js', array('jquery', 'codemirror'), '', true),
        array('codemirror-mode-javascript', 'mode/javascript/javascript.js', array('jquery', 'codemirror'), '', true),
        array('codemirror-mode-htmlmixed', 'mode/htmlmixed/htmlmixed.js', array('jquery', 'codemirror', 'codemirror-mode-xml', 'codemirror-mode-javascript', 'codemirror-mode-css'), '', true),
    );

    foreach ($codemirror_files as $file) {
        wp_enqueue_script($file[0], $codemirror_path . $file[1], $file[2], '', $file[4]);
    }

    $codemirror_styles = array(
        array('codemirror', 'lib/codemirror.css'),
        array('codemirror-theme', 'theme/mbo.css', array('codemirror')),
        array('codemirror-lint-style', 'addon/lint/lint.css', array('codemirror')),
        array('codemirror-hint-style', 'addon/hint/show-hint.css', array('codemirror')),
    );

    foreach ($codemirror_styles as $style) {
        wp_enqueue_style($style[0], $codemirror_path . $style[1], isset($style[2]) ? $style[2] : array(), '', 'all');
    }

    $scripts = array(
        'id-general' => array('/js/id-general.js', array('jquery')),
        'mergeTags' => array('/js/mergeTags.js', array()),

        'wiz-inits' => array('/builder-v2/js/wiz-inits.js', array('jquery', 'id-general', 'jquery-ui-resizable', 'editable', 'spectrum', 'tinymce', 'crush', 'mergeTags')),

        'utilities' => array('/builder-v2/js/utilities.js', array('wiz-inits')),
        'builder-functions' => array('/builder-v2/js/builder-functions.js', array('wiz-inits', 'utilities')),
        'template-editor' => array('/builder-v2/js/template-editor.js?v=08232024831pm', array('builder-functions')),
        'template-actions' => array('/js/folders/template-actions.js', array('builder-functions', 'folder-ui')),
        'save-functions' => array('/builder-v2/js/save-functions.js?v=1.1', array('builder-functions')),
        'import-export' => array('/builder-v2/js/import-export.js', array('builder-functions')),
        'tiny-mce-editor' => array('/builder-v2/js/tiny-mce-editor.js', array('builder-functions')),
        'wiz-tooltips' => array('/builder-v2/js/wiz-tooltips.js', array('jquery')),

        'preview-pane' => array('/builder-v2/js/preview-pane.js', array('builder-functions', 'html2canvas')),

        'wizSnippets' => array('/js/wizSnippets.js', array('jquery', 'id-general', 'codemirror')),
        'interactives' => array('/builder-v2/interactives/interactives.js', array('jquery', 'id-general', 'codemirror')),
        'interactive-rec-engine' => array('/builder-v2/interactives/rec-engine/rec-engine.js', array('jquery', 'id-general', 'codemirror', 'interactives')),
        'folder-ui' => array('/js/folders/folder-ui.js', array('jquery', 'id-general', 'select2')),
        'folder-drawer' => array('/js/folders/folder-drawer.js', array('jquery', 'id-general')),
        'folder-actions' => array('/js/folders/folder-actions.js', array('jquery', 'id-general', 'folder-ui')),
        'user-favorites' => array('/js/user-favorites.js', array('jquery', 'id-general')),
        'bulk-actions' => array('/js/folders/bulk-actions.js', array('jquery', 'id-general', 'folder-actions', 'template-actions')),
        'iterable-actions' => array('/js/iterable-actions.js', array('jquery', 'id-general', 'bulk-actions', 'template-editor')),
        'sms-template' => array('/js/sms-template.js', array('jquery', 'id-general', 'tiny-mce-editor', 'utilities', 'iterable-actions')),
    );

    $plugin_version = '1.0.0';
    wp_enqueue_style(
        'id-style',
        plugins_url('/style.css', __FILE__),
        array(),
        $plugin_version
    );

    wp_enqueue_style(
        'wiz-tooltips',
        plugins_url('/builder-v2/css/wiz-tooltips.css', __FILE__),
        array()
    );

    $wizSettings = get_option('idemailwiz_settings');

    foreach ($scripts as $handle => $script) {
        // Version each file by its own mtime rather than a fixed '1.0.0'. These
        // scripts call into each other, so a browser holding a stale copy of one
        // and a fresh copy of another breaks the page.
        $scriptFile = plugin_dir_path(__FILE__) . ltrim($script[0], '/');
        $scriptVersion = file_exists($scriptFile) ? filemtime($scriptFile) : '1.0.0';
        wp_enqueue_script($handle, plugins_url($script[0], __FILE__), $script[1], $scriptVersion, true);
        $handle_underscore = str_replace('-', '_', $handle);
        $localized = array(
            'nonce' => wp_create_nonce($handle),
            'ajaxurl' => esc_url(admin_url('admin-ajax.php')),

            'currentPost' => get_post(get_the_ID()),
            'currentPostId' => get_the_ID(),
            'stylesheet' => plugins_url('', __FILE__),
            'plugin_url' => plugin_dir_url(__FILE__),
            'site_url' => get_bloginfo('url'),
            'current_user' => wp_get_current_user(),
        );
        // Only the email sync (it calls Iterable from the browser) gets the API key.
        if ($handle === 'iterable-actions') {
            $localized['iterable_api_key'] = $wizSettings['iterable_api_key'] ?? false;
        }
        wp_localize_script($handle, 'idAjax_' . $handle_underscore, $localized);
    }

    wp_localize_script(
        'id-general',
        'idAjax',
        array(
            'plugin_url' => plugin_dir_url(__FILE__),
            'ajaxurl' => esc_url(admin_url('admin-ajax.php')),
            'wizAjaxUrl' => get_bloginfo('url') . '/idwiz-ajax/',
            'wizAjaxNonce' => wp_create_nonce('wizAjaxNonce'),
            'currentPost' => get_post(get_the_ID()),
            'currentPostId' => get_the_ID(),
            'stylesheet' => plugins_url('', __FILE__),
            'site_url' => get_bloginfo('url'),
            'current_user' => wp_get_current_user(),
        )
    );

    wp_enqueue_script('highlighterjs', '//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js', array('jquery'), '11.7.0', true);
    wp_enqueue_style('highlighter-agate', plugins_url('/styles/agate.css', __FILE__), array(), '11.7.0');
}
