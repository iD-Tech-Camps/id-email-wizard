<?php
get_header();
$postId = get_the_ID();
$wizTemplate = get_wiztemplate($postId);

$smsMessage = $wizTemplate['sms']['message'] ?? '';
$smsImageUrl = $wizTemplate['sms']['image_url'] ?? '';
$smsMessageTypeId = (int) ($wizTemplate['sms']['message_type_id'] ?? 0);
$iterableSync = $wizTemplate['template_options']['template_settings']['iterable-sync'] ?? [];
?>
<div class="templateUI-header">
	<h1 id="single-template-title" class="wizEntry-title" title="<?php echo get_the_title(); ?>" itemprop="name" data-template-id="<?php echo $postId; ?>">
		<?php echo get_the_title($postId); ?>
	</h1>
	<span class="sms-template-badge"><i class="fa-solid fa-comment-sms"></i> SMS</span>
	<div class="iDbreadcrumb">
		Located in:
		<?php echo display_template_folder_hierarchy($postId); ?>
	</div>
	<div title="New Template" class="templateUI-header-tools">
		<div class="wiz-button green show-new-template-ui">
			<i class="fa fa-plus"></i>
		</div>
		<div title="Duplicate Template" class="wiz-button green duplicate-template"
			data-postid="<?php echo $postId; ?>">
			<i class="fa-solid fa-copy"></i>
		</div>
		<div title="Move Template" class="wiz-button green moveTemplate" data-postid="<?php echo $postId; ?>">
			<i class="fa-solid fa-folder-tree"></i>
		</div>
		<div title="Delete Template" class="wiz-button red delete-template" data-postid="<?php echo $postId; ?>">
			<i class="fa-solid fa-trash"></i>
		</div>
	</div>
</div>
<div id="templateUI" class="entry-content sms-template-ui" data-postid="<?php echo $postId; ?>" itemprop="mainContentOfPage">

	<div id="sms-template-editor" class="sms-template-panel" data-postid="<?php echo $postId; ?>">
		<div class="sms-template-actions">
			<button type="button" class="wiz-button" id="sync-sms-template" title="Sync to Iterable">
				<img style="width: 20px; height: 20px;"
					src="https://idemailwiz.com/wp-content/uploads/2023/10/Iterable_square_logo-e1677898367554.png" />&nbsp;&nbsp;Sync
			</button>
			<button type="button" class="wiz-button green" id="save-sms-template">
				<i class="fa-regular fa-floppy-disk"></i>&nbsp;&nbsp;Save
			</button>
		</div>

		<div class="sms-field">
			<label>Message Type</label>
			<div class="sms-message-types">
				<?php foreach (get_wiz_sms_message_types() as $typeId => $typeName): ?>
				<label>
					<input type="radio" name="sms-message-type" value="<?php echo $typeId; ?>" <?php checked($smsMessageTypeId, $typeId); ?>>
					<?php echo esc_html($typeName); ?>
				</label>
				<?php endforeach; ?>
			</div>
			<div class="sms-field-hint">
				Must match the campaign's message type and sender in Iterable. Sync skips any template that doesn't.
			</div>
		</div>

		<div class="sms-field">
			<label for="sms-message">Message</label>
			<textarea id="sms-message" rows="10" placeholder="Hi {{firstName}}, ..."><?php echo esc_textarea($smsMessage); ?></textarea>
			<div class="sms-message-stats" id="sms-message-stats"></div>
		</div>

		<div class="sms-field">
			<label for="sms-image-url">Image URL <span class="sms-field-hint">optional, sends as MMS. Leaving it blank removes the image in Iterable on sync.</span></label>
			<input type="url" id="sms-image-url" value="<?php echo esc_attr($smsImageUrl); ?>" placeholder="https://...">
		</div>

		<div class="sms-field sms-sync-fields">
			<div class="sms-field-hint">
				Create the SMS campaigns in Iterable, then Sync and paste in their template IDs. Sync overwrites each template's message.
			</div>
			<?php echo get_iterable_sync_fields($postId, $iterableSync); ?>
		</div>
	</div>

	<div class="sms-template-panel sms-preview-panel">
		<div class="sms-phone">
			<div class="sms-bubble">
				<img id="sms-preview-image" alt="" <?php echo $smsImageUrl ? 'src="' . esc_url($smsImageUrl) . '"' : 'hidden'; ?>>
				<div id="sms-preview-text"></div>
			</div>
		</div>
	</div>
</div>

<?php get_footer(); ?>
