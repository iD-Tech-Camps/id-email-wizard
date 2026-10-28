<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function wiz_ajax_redirect()
{

    if (get_query_var('idwiz_ajax_endpoint')) {
        // This is where we'll handle the request
        idwiz_process_custom_request();
        exit;
    }
}
add_action('template_redirect', 'wiz_ajax_redirect', 11);

function idwiz_process_custom_request()
{
    // Ensure this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_send_json_error('Invalid request method');
    }

    // Only signed-in users (like admin-ajax.php's wp_ajax_* actions)
    if (!is_user_logged_in()) {
        wp_send_json_error('Not signed in', 401);
    }

    // Verify nonce for security
    // if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'wizAjaxNonce')) {
    //     wp_send_json_error('Security check failed');
    // }

    // Get the action
    $action = isset($_POST['action']) ? sanitize_key($_POST['action']) : '';

    // Dispatch to the appropriate handler
    do_action("wp_ajax_{$action}", $_POST);

    // If we reach here, it means no action was taken
    wp_send_json_error('Invalid action');
}
