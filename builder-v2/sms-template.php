<?php
/**
 * SMS templates.
 *
 * An SMS template is an idemailwiz_template post whose wp_wiz_templates JSON has
 * template_type 'sms'. It has no builder rows; it holds a plain-text message and
 * an optional MMS image, and syncs to the templates of SMS campaigns that were
 * created in Iterable (their template IDs are pasted in at sync time).
 *
 * JSON shape:
 *   { template_type: 'sms',
 *     sms: { message, image_url, message_type_id },
 *     template_options: { template_settings: { iterable-sync: { iterable_template_id, synced_templates_history } } } }
 *
 * iterable-sync is the same shape the email builder uses, so the sync history UI
 * and its make-primary / remove handlers are shared.
 *
 * Sync never changes a template's message type in Iterable. Each SMS sender is tied
 * to one message type, and changing messageTypeId through the API blanks the sender
 * (the public API has no sender field to set it back). So sync checks that each
 * Iterable template already has this template's message type, and skips it if not.
 */

// Iterable SMS message types, each with its own sender (Twilio messaging service)
function get_wiz_sms_message_types()
{
	return [
		53047 => 'Marketing SMS',
		53048 => 'Transactional SMS',
	];
}

function get_wiztemplate_type($postId)
{
	$wizTemplate = get_wiztemplate($postId);
	return $wizTemplate['template_type'] ?? 'email';
}

function is_wiz_sms_template($postId)
{
	return get_wiztemplate_type($postId) === 'sms';
}

function update_wiztemplate_data($postId, $templateData)
{
	global $wpdb;

	return $wpdb->update(
		$wpdb->prefix . 'wiz_templates',
		[
			'template_data' => wp_json_encode($templateData),
			'last_updated'  => current_time('mysql'),
			'user_id'       => get_current_user_id(),
		],
		['post_id' => $postId],
		['%s', '%s', '%d'],
		['%d']
	);
}

add_action('wp_ajax_save_wiz_sms_template', 'save_wiz_sms_template');
function save_wiz_sms_template()
{
	check_ajax_referer('sms-template', 'security');

	$postId = (int) ($_POST['post_id'] ?? 0);
	if (!$postId || !is_wiz_sms_template($postId)) {
		wp_send_json_error(['message' => 'Not an SMS template']);
	}

	$messageTypeId = (int) ($_POST['message_type_id'] ?? 0);

	$templateData = get_wiztemplate($postId);
	$templateData['sms'] = [
		'message'         => sanitize_textarea_field(wp_unslash($_POST['message'] ?? '')),
		'image_url'       => esc_url_raw(trim(wp_unslash($_POST['image_url'] ?? ''))),
		'message_type_id' => isset(get_wiz_sms_message_types()[$messageTypeId]) ? $messageTypeId : null,
	];

	if (update_wiztemplate_data($postId, $templateData) === false) {
		wp_send_json_error(['message' => 'Failed to save the SMS template']);
	}

	wp_send_json_success(['message' => 'SMS template saved']);
}

/**
 * Find another template post whose sync history already includes this Iterable template ID.
 */
function find_wiz_template_using_iterable_id($iterableTemplateId, $excludePostId)
{
	global $wpdb;

	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT post_id, template_data FROM {$wpdb->prefix}wiz_templates WHERE post_id != %d AND template_data LIKE %s",
		$excludePostId,
		'%' . $wpdb->esc_like($iterableTemplateId) . '%'
	), ARRAY_A);

	foreach ($rows as $row) {
		if (get_post_status($row['post_id']) !== 'publish') {
			continue;
		}
		$iterableSync = json_decode($row['template_data'], true)['template_options']['template_settings']['iterable-sync'] ?? [];
		$syncedIds = array_map(fn($entry) => (string) $entry['template_id'], $iterableSync['synced_templates_history'] ?? []);
		$syncedIds[] = (string) ($iterableSync['iterable_template_id'] ?? '');
		if (in_array((string) $iterableTemplateId, $syncedIds, true)) {
			return (int) $row['post_id'];
		}
	}
	return false;
}

/**
 * Push one SMS message to one Iterable template. Returns null on success or an error message.
 */
function push_sms_to_iterable_template($postId, $iterableTemplateId, $message, $imageUrl, $messageTypeId, $apiKey)
{
	$otherPostId = find_wiz_template_using_iterable_id($iterableTemplateId, $postId);
	if ($otherPostId) {
		return "Already synced from <a href='" . esc_url(get_permalink($otherPostId)) . "'>" . esc_html(get_the_title($otherPostId)) . '</a>.';
	}

	// Same guard as email: don't overwrite the template of a blast that has already sent.
	$itTemplate = get_idwiz_template($iterableTemplateId);
	$itCampaign = !empty($itTemplate['campaignId']) ? get_idwiz_campaign($itTemplate['campaignId']) : false;
	if ($itCampaign && $itCampaign['type'] === 'Blast' && $itCampaign['campaignState'] === 'Finished') {
		return "Its campaign has already sent, so the template can't be updated.";
	}

	// Check the live template: it must exist, be SMS, and already have this template's message type.
	$response = wp_remote_get('https://api.iterable.com/api/templates/sms/get?templateId=' . (int) $iterableTemplateId, [
		'headers' => ['Api-Key' => $apiKey],
		'timeout' => 30,
	]);
	if (is_wp_error($response)) {
		return 'Could not reach Iterable: ' . $response->get_error_message();
	}
	$liveTemplate = json_decode(wp_remote_retrieve_body($response), true);
	if (wp_remote_retrieve_response_code($response) >= 400 || empty($liveTemplate['templateId'])) {
		// e.g. "Template ID: 123 has type 'Email', which does not match this endpoint"
		return !empty($liveTemplate['msg']) ? 'Iterable: ' . esc_html($liveTemplate['msg']) : 'Not found as an SMS template in Iterable.';
	}
	$messageTypes = get_wiz_sms_message_types();
	$liveTypeId   = (int) ($liveTemplate['messageTypeId'] ?? 0);
	if ($liveTypeId !== (int) $messageTypeId) {
		$liveTypeName = $messageTypes[$liveTypeId] ?? "message type $liveTypeId";
		return "It's $liveTypeName in Iterable, but this template is {$messageTypes[$messageTypeId]}. "
			. "Change one to match (in Iterable, set both the message type and the sender).";
	}

	// A blank imageUrl removes any image the template has in Iterable.
	$payload = [
		'templateId' => (int) $iterableTemplateId,
		'message'    => $message,
		'imageUrl'   => $imageUrl,
	];

	$response = wp_remote_post('https://api.iterable.com/api/templates/sms/update', [
		'headers' => [
			'Content-Type' => 'application/json',
			'Api-Key'      => $apiKey,
		],
		'body'    => wp_json_encode($payload),
		'timeout' => 30,
	]);

	if (is_wp_error($response)) {
		return 'Could not reach Iterable: ' . $response->get_error_message();
	}

	$code = wp_remote_retrieve_response_code($response);
	if ($code >= 400) {
		$body = json_decode(wp_remote_retrieve_body($response), true);
		return "Iterable returned $code: " . esc_html($body['msg'] ?? wp_remote_retrieve_body($response));
	}

	return null;
}

/**
 * Push the saved message to each chosen Iterable SMS template.
 * Only updates existing templates: the campaigns (and their templates) are created in Iterable.
 */
add_action('wp_ajax_sync_wiz_sms_template', 'sync_wiz_sms_template');
function sync_wiz_sms_template()
{
	global $wpdb;

	check_ajax_referer('sms-template', 'security');

	$postId = (int) ($_POST['post_id'] ?? 0);
	if (!$postId || !is_wiz_sms_template($postId)) {
		wp_send_json_error(['message' => 'Not an SMS template']);
	}

	$templateIds = array_values(array_unique(array_filter(array_map(
		fn($id) => preg_replace('/\D/', '', $id),
		(array) ($_POST['template_ids'] ?? [])
	))));
	if (!$templateIds) {
		wp_send_json_error(['message' => 'Choose at least one Iterable template ID to sync to.']);
	}

	$templateData = get_wiztemplate($postId);
	$message      = $templateData['sms']['message'] ?? '';
	$imageUrl      = $templateData['sms']['image_url'] ?? '';
	$messageTypeId = (int) ($templateData['sms']['message_type_id'] ?? 0);
	if (trim($message) === '') {
		wp_send_json_error(['message' => 'The message is empty.']);
	}
	if (!isset(get_wiz_sms_message_types()[$messageTypeId])) {
		wp_send_json_error(['message' => 'Choose Marketing or Transactional SMS before syncing.']);
	}

	$settings = get_option('idemailwiz_settings', []);
	$apiKey   = $settings['iterable_api_key'] ?? '';
	if (!$apiKey) {
		wp_send_json_error(['message' => 'Iterable API key not found in settings']);
	}

	$results = [];
	$synced  = [];
	foreach ($templateIds as $templateId) {
		$error = push_sms_to_iterable_template($postId, $templateId, $message, $imageUrl, $messageTypeId, $apiKey);
		$results[] = ['templateId' => $templateId, 'error' => $error];
		if ($error === null) {
			$synced[] = $templateId;

			// Keep the local copy of Iterable's template in step, as update_template_after_sync does for email.
			$wpdb->update(
				$wpdb->prefix . 'idemailwiz_templates',
				['clientTemplateId' => $postId, 'message' => $message],
				['templateId' => $templateId],
				['%d', '%s'],
				['%d']
			);
		}
	}

	if ($synced) {
		$templateData['template_options']['template_settings']['iterable-sync'] ??= [];
		add_to_iterable_sync_history($templateData['template_options']['template_settings']['iterable-sync'], $synced);
		update_wiztemplate_data($postId, $templateData);
	}

	$iterableSync = $templateData['template_options']['template_settings']['iterable-sync'] ?? [];
	wp_send_json_success([
		'results'           => $results,
		'syncHistory'       => $iterableSync['synced_templates_history'] ?? [],
		'primaryTemplateId' => $iterableSync['iterable_template_id'] ?? '',
	]);
}
