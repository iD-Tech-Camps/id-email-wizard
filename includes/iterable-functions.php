<?php



//Get all the postdata needed to create or update a template in Iterable
function idemailwiz_get_template_data_for_iterable()
{
	//check nonce
	check_ajax_referer('iterable-actions', 'security');

	// Check that post_id is defined
	if (!isset($_POST['post_id'])) {
		wp_send_json(
			array(
				'status' => 'error',
				'message' => 'Post ID is missing',
			)
		);
	}

	$post_id = $_POST['post_id'];

	$wizTemplate = get_wiztemplate($post_id);

	$iterableSyncSettings = $wizTemplate['template_options']['template_settings']['iterable-sync'] ?? [];

	$messageSettings = $wizTemplate['template_options']['message_settings'];

	$current_user = wp_get_current_user();

	// Format link params
	$iterableUtms = [];

	if (isset($messageSettings['utm_parameters']) && is_array($messageSettings['utm_parameters'])) {
		foreach ($messageSettings['utm_parameters'] as $key => $value) {
			$iterableUtms[] = [
				'key' => $key,
				'value' => $value
			];
		}
	}	

	$templateFields = array(
		'preheader' => html_entity_decode($messageSettings['preview_text'], ENT_QUOTES, 'UTF-8') ?? '',
		'fromName' => $messageSettings['from_name'] ?? 'iD Tech Camps',
		'googleAnalyticsCampaignName' => $messageSettings['ga_campaign_name'] ?? '',
		'linkParams' => $iterableUtms,
		'plainText' => $messageSettings['plain-text-content'] ?? '',
	);

	// Get email type and set appropriate message type ID
	$email_type = $messageSettings['email_type'] ?? 'promotional';

	// Set message type ID based on email type
	$default_message_type_id = $email_type === 'transactional' ? '52620' : '52634';
	$message_type_id = $messageSettings['message_type_id'] ?? $default_message_type_id;

	// Ensure message type ID is an integer for Iterable API
	$message_type_id = intval($message_type_id);

	// Define from/replyTo emails - use form values if set, otherwise use defaults based on type
	$default_from_email = $email_type === 'transactional' ? 'info@idtechnotifications.com' : 'info@idtechonline.com';
	$default_reply_to_email = $email_type === 'transactional' ? 'info@idtechnotifications.com' : 'hello@idtechonline.com';
	
	$from_email = $messageSettings['from_email'] ?? $default_from_email;
	$reply_to_email = $messageSettings['reply_to'] ?? $default_reply_to_email;

	$reqTemplateFields = array(
		'templateName' => html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8'),
		'emailSubject' =>  html_entity_decode($messageSettings['subject_line'], ENT_QUOTES, 'UTF-8')  ?? '',
		'messageType' => $email_type,
		'messageTypeId' => $message_type_id,
		'fromEmail' => $from_email,
		'replyToEmail' => $reply_to_email,
		'createdBy' => $current_user->user_email,
		'postId' => $post_id,
	);

	$missing = array();

	foreach ($reqTemplateFields as $key => $field) {
		if (!$field) {
			$missing[] = $key;
		}
	}

	$response['alreadySent'] = false;
	if (empty($missing)) {
		$templateFields = array_merge($reqTemplateFields, $templateFields);
		$response = array(
			'status' => 'success',
			'fields' => $templateFields,
		);

		// Iterable template ID (primary)
		$templateId = $iterableSyncSettings['iterable_template_id'] ?? '';
		
		// Get sync history
		$syncHistory = $iterableSyncSettings['synced_templates_history'] ?? [];
		
		$response['syncHistory'] = $syncHistory;
		$response['primaryTemplateId'] = $templateId;

		if ($templateId) {
			// Get wiz campaign based on templateId
			$wizTemplate = get_idwiz_template($templateId);
			if ($wizTemplate) {
				$wizCampaign = get_idwiz_campaign($wizTemplate['campaignId']);
				if ($wizCampaign && $wizCampaign['campaignState'] === 'Finished') {
					$response['alreadySent'] = true;
				}
			}
		}
	} else {
		$response = array(
			'status' => 'error',
			'message' => 'Required fields are missing: ' . implode(',', $missing),
			'alreadySent' => false
		);
	}



	wp_send_json($response);
}
add_action('wp_ajax_idemailwiz_get_template_data_for_iterable', 'idemailwiz_get_template_data_for_iterable');

function check_duplicate_itTemplateId()
{
	// Iterable template ID from POST request
	$templateId = $_POST['template_id'] ?? false;
	// WizTemplate post ID from POST request
	$post_id = $_POST['post_id'] ?? false;

	if ($post_id) {
		// Fetch all wizTemplate posts
		$args = array(
			'post_type' => 'idemailwiz_template',
			'posts_per_page' => -1,
			'post__not_in' => array($post_id), // Exclude the current post 
			//TODO: This won't work when we need to look at variations from the same post
		);

		$wizTemplates = get_posts($args);
		foreach ($wizTemplates as $wizTemplate) {
			// Get the wizTemplate data
			$wizData = get_wiztemplate($wizTemplate->ID);
			$wizTemplateControlId = $wizData['template_options']['template_settings']['iterable-sync']['iterable_template_id'] ?? false;
			//TODO: also look for variations template IDs

			if ($wizTemplateControlId && $wizTemplateControlId == $templateId) {
				// Duplicate found
				$response = array(
					'status' => 'error',
					'message' => "The template ID you entered is already synced to template <a href='" . get_edit_post_link($wizTemplate->ID) . "'>" . $wizTemplate->ID . "</a>"
				);
				wp_send_json($response);
				return; // Exit function
			}
		}

		// If no duplicates are found
		wp_send_json(array('status' => 'success'));
		return; // Exit function
	} else {
		if ($templateId == 'new') {
			wp_send_json(array('status' => 'success'));
			return;
		}
		// Handle case where necessary POST data is not set
		wp_send_json(array('status' => 'error', 'message' => 'Missing template ID or post ID.'));
		return; // Exit function
	}
}
add_action('wp_ajax_check_duplicate_itTemplateId', 'check_duplicate_itTemplateId');



function update_template_after_sync()
{
	global $wpdb;

	//check nonce
	check_ajax_referer('iterable-actions', 'security');


	$template_id = $_POST['template_id'];
	$post_id = $_POST['post_id'] ?? null;

	$wizTemplate = get_idwiz_template($template_id);

	$template_name = $_POST['template_name'] ?? $wizTemplate['name'];



	// Update the custom database table
	$wpdb->update(
		$wpdb->prefix . 'idemailwiz_templates',
		array('clientTemplateId' => $post_id, 'name' => $template_name),
		array('templateId' => $template_id),
		array('%d', '%s'),
		array('%d')
	);

	$response = array(
		'status' => 'success',
		'message' => 'WizTemplate updated!',
	);
	wp_send_json($response);
}
add_action('wp_ajax_update_template_after_sync', 'update_template_after_sync');


/**
 * Update the sync history for a template after syncing to Iterable
 * Adds new template IDs to history and sets primary ID if not already set
 */
function update_iterable_sync_history()
{
	global $wpdb;

	check_ajax_referer('iterable-actions', 'security');

	$post_id = $_POST['post_id'] ?? null;
	$synced_template_ids = $_POST['synced_template_ids'] ?? [];

	if (!$post_id || empty($synced_template_ids)) {
		wp_send_json(array(
			'status' => 'error',
			'message' => 'Missing post ID or synced template IDs'
		));
		return;
	}

	// Get current template data
	$table_name = $wpdb->prefix . 'wiz_templates';
	$templateDataJSON = $wpdb->get_var($wpdb->prepare(
		"SELECT template_data FROM $table_name WHERE post_id = %d",
		$post_id
	));

	if (!$templateDataJSON) {
		wp_send_json(array(
			'status' => 'error',
			'message' => 'Template not found'
		));
		return;
	}

	$templateData = json_decode($templateDataJSON, true);

	// Initialize sync settings if not exists
	if (!isset($templateData['template_options']['template_settings']['iterable-sync'])) {
		$templateData['template_options']['template_settings']['iterable-sync'] = [];
	}

	add_to_iterable_sync_history($templateData['template_options']['template_settings']['iterable-sync'], $synced_template_ids);

	// Save back to database
	$wpdb->update(
		$table_name,
		array('template_data' => json_encode($templateData)),
		array('post_id' => $post_id),
		array('%s'),
		array('%d')
	);

	wp_send_json(array(
		'status' => 'success',
		'message' => 'Sync history updated',
		'syncHistory' => $templateData['template_options']['template_settings']['iterable-sync']['synced_templates_history'],
		'primaryTemplateId' => $templateData['template_options']['template_settings']['iterable-sync']['iterable_template_id'] ?? ''
	));
}
add_action('wp_ajax_update_iterable_sync_history', 'update_iterable_sync_history');

/**
 * Record a sync to these Iterable template IDs in a template's iterable-sync settings.
 * New IDs are added to the history (the first one becomes primary if there's no primary yet);
 * IDs already in the history get a fresh timestamp.
 */
function add_to_iterable_sync_history(array &$iterableSync, array $synced_template_ids)
{
	// Get current history or initialize empty array
	$syncHistory = $iterableSync['synced_templates_history'] ?? [];
	$currentPrimaryId = $iterableSync['iterable_template_id'] ?? '';

	// Process each synced template ID
	$now = current_time('c'); // ISO 8601 format
	foreach ($synced_template_ids as $templateId) {
		$templateId = strval($templateId);
		
		// Check if this ID already exists in history
		$existingIndex = null;
		foreach ($syncHistory as $index => $entry) {
			if (strval($entry['template_id']) === $templateId) {
				$existingIndex = $index;
				break;
			}
		}

		if ($existingIndex !== null) {
			// Update existing entry's timestamp
			$syncHistory[$existingIndex]['synced_at'] = $now;
		} else {
			// Add new entry
			$isPrimary = empty($currentPrimaryId);
			$syncHistory[] = array(
				'template_id' => $templateId,
				'synced_at' => $now,
				'is_primary' => $isPrimary
			);

			// Set as primary if no primary exists (first-synced logic)
			if ($isPrimary) {
				$currentPrimaryId = $templateId;
				$iterableSync['iterable_template_id'] = $templateId;
			}
		}
	}

	// Update the history
	$iterableSync['synced_templates_history'] = $syncHistory;
}


/**
 * Remove a template ID from sync history
 */
function remove_from_iterable_sync_history()
{
	global $wpdb;

	check_ajax_referer('iterable-actions', 'security');

	$post_id = $_POST['post_id'] ?? null;
	$template_id_to_remove = $_POST['template_id'] ?? null;

	if (!$post_id || !$template_id_to_remove) {
		wp_send_json(array(
			'status' => 'error',
			'message' => 'Missing post ID or template ID'
		));
		return;
	}

	// Get current template data
	$table_name = $wpdb->prefix . 'wiz_templates';
	$templateDataJSON = $wpdb->get_var($wpdb->prepare(
		"SELECT template_data FROM $table_name WHERE post_id = %d",
		$post_id
	));

	if (!$templateDataJSON) {
		wp_send_json(array(
			'status' => 'error',
			'message' => 'Template not found'
		));
		return;
	}

	$templateData = json_decode($templateDataJSON, true);
	$iterableSync = &$templateData['template_options']['template_settings']['iterable-sync'];
	$syncHistory = $iterableSync['synced_templates_history'] ?? [];

	// Filter out the template ID to remove
	$syncHistory = array_filter($syncHistory, function($entry) use ($template_id_to_remove) {
		return strval($entry['template_id']) !== strval($template_id_to_remove);
	});
	$syncHistory = array_values($syncHistory); // Re-index array

	// If removed template was the primary, clear it (or set to next in history)
	if (strval($iterableSync['iterable_template_id'] ?? '') === strval($template_id_to_remove)) {
		if (!empty($syncHistory)) {
			// Set the oldest entry as the new primary
			usort($syncHistory, function($a, $b) {
				return strtotime($a['synced_at']) - strtotime($b['synced_at']);
			});
			$iterableSync['iterable_template_id'] = $syncHistory[0]['template_id'];
			$syncHistory[0]['is_primary'] = true;
		} else {
			$iterableSync['iterable_template_id'] = '';
		}
	}

	$iterableSync['synced_templates_history'] = $syncHistory;

	// Save back to database
	$wpdb->update(
		$table_name,
		array('template_data' => json_encode($templateData)),
		array('post_id' => $post_id),
		array('%s'),
		array('%d')
	);

	wp_send_json(array(
		'status' => 'success',
		'message' => 'Template removed from sync history',
		'syncHistory' => $syncHistory,
		'primaryTemplateId' => $iterableSync['iterable_template_id'] ?? ''
	));
}
add_action('wp_ajax_remove_from_iterable_sync_history', 'remove_from_iterable_sync_history');


/**
 * Set a template ID from sync history as the primary template
 */
function set_primary_iterable_template()
{
	global $wpdb;

	check_ajax_referer('iterable-actions', 'security');

	$post_id = $_POST['post_id'] ?? null;
	$template_id = $_POST['template_id'] ?? null;

	if (!$post_id || !$template_id) {
		wp_send_json(array(
			'status' => 'error',
			'message' => 'Missing post ID or template ID'
		));
		return;
	}

	$table_name = $wpdb->prefix . 'wiz_templates';
	$templateDataJSON = $wpdb->get_var($wpdb->prepare(
		"SELECT template_data FROM $table_name WHERE post_id = %d",
		$post_id
	));

	if (!$templateDataJSON) {
		wp_send_json(array(
			'status' => 'error',
			'message' => 'Template not found'
		));
		return;
	}

	$templateData = json_decode($templateDataJSON, true);
	$iterableSync = &$templateData['template_options']['template_settings']['iterable-sync'];
	$syncHistory = $iterableSync['synced_templates_history'] ?? [];

	// Verify the template ID exists in sync history
	$found = false;
	foreach ($syncHistory as &$entry) {
		if (strval($entry['template_id']) === strval($template_id)) {
			$entry['is_primary'] = true;
			$found = true;
		} else {
			$entry['is_primary'] = false;
		}
	}
	unset($entry);

	if (!$found) {
		wp_send_json(array(
			'status' => 'error',
			'message' => 'Template ID not found in sync history'
		));
		return;
	}

	$iterableSync['iterable_template_id'] = strval($template_id);
	$iterableSync['synced_templates_history'] = $syncHistory;

	$wpdb->update(
		$table_name,
		array('template_data' => json_encode($templateData)),
		array('post_id' => $post_id),
		array('%s'),
		array('%d')
	);

	wp_send_json(array(
		'status' => 'success',
		'message' => 'Primary template updated',
		'syncHistory' => $syncHistory,
		'primaryTemplateId' => strval($template_id)
	));
}
add_action('wp_ajax_set_primary_iterable_template', 'set_primary_iterable_template');


/**
 * Clear the primary template ID without removing it from sync history
 */
function clear_primary_iterable_template()
{
	global $wpdb;

	check_ajax_referer('iterable-actions', 'security');

	$post_id = $_POST['post_id'] ?? null;

	if (!$post_id) {
		wp_send_json(array(
			'status' => 'error',
			'message' => 'Missing post ID'
		));
		return;
	}

	$table_name = $wpdb->prefix . 'wiz_templates';
	$templateDataJSON = $wpdb->get_var($wpdb->prepare(
		"SELECT template_data FROM $table_name WHERE post_id = %d",
		$post_id
	));

	if (!$templateDataJSON) {
		wp_send_json(array(
			'status' => 'error',
			'message' => 'Template not found'
		));
		return;
	}

	$templateData = json_decode($templateDataJSON, true);
	$iterableSync = &$templateData['template_options']['template_settings']['iterable-sync'];
	$syncHistory = $iterableSync['synced_templates_history'] ?? [];

	// Clear primary from all entries
	foreach ($syncHistory as &$entry) {
		$entry['is_primary'] = false;
	}
	unset($entry);

	$iterableSync['iterable_template_id'] = '';
	$iterableSync['synced_templates_history'] = $syncHistory;

	$wpdb->update(
		$table_name,
		array('template_data' => json_encode($templateData)),
		array('post_id' => $post_id),
		array('%s'),
		array('%d')
	);

	wp_send_json(array(
		'status' => 'success',
		'message' => 'Primary template cleared',
		'syncHistory' => $syncHistory,
		'primaryTemplateId' => ''
	));
}
add_action('wp_ajax_clear_primary_iterable_template', 'clear_primary_iterable_template');
