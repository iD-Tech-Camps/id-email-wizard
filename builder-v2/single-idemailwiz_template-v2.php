<?php
get_header();
$postId = get_the_ID();
$wizTemplate = get_wiztemplate($postId);
$wizTemplateObject = get_wiztemplate_object($postId);

$current_user = wp_get_current_user();
$userId = $current_user->ID;
$itTemplateId = $wizTemplate['template_options']['template_settings']['iterable-sync']['iterable_template_id'] ?? '';
$messageSettings = $wizTemplate['template_options']['message_settings'] ?? [];

?>
<div class="templateUI-header">
	<h1 id="single-template-title" class="wizEntry-title" title="<?php echo get_the_title(); ?>" itemprop="name" data-template-id="<?php echo $postId; ?>">
		<?php echo get_the_title($postId); ?>
	</h1>
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
<div id="templateUI" class="entry-content two-col-wrap" data-postid="<?php echo $postId; ?>"
	data-iterableid="<?php echo $itTemplateId; ?>" itemprop="mainContentOfPage">



	<div class="left panel-left" id="builder">
		<?php echo get_builder_pane_header($postId); ?>

		<div id="builder-pane">

			<div class="builder-tab-content wizard-tab-content --active" id="builder-tab-chunks">
				<div class="edit-plain-text-link"><i class="fa-solid fa-i-cursor"></i>&nbsp;&nbsp;Edit plain text version</div>
				<div id="edit-plain-text-content">
					<label for="plain-text-content">Plain Text Version</label>
					<div id="plain-text-content">
						<textarea id="plain-text-editor" name="plain-text-content"><?php echo $messageSettings['plain-text-content'] ?? ''; ?></textarea>
					</div>
				</div>
				<div class="builder-form-wrapper">

					<div class="builder-rows-wrapper">
						<?php

						if (! empty($wizTemplate['rows'])) {
							foreach ($wizTemplate['rows'] as $rowIndex => $row) {
								echo generate_builder_row($rowIndex, $row);
							}
						} else {
							// Generate an empty row/columnset/single-col
							echo generate_builder_row(0, ['columnSets' => [0 => ['columns' => [0 => ['activation' => 'active']]]]]);
						}
						?>
					</div>
					<div class="add-row">
						<i class="fas fa-plus-circle"></i>&nbsp;&nbsp;Add Section
					</div>

				</div>
			</div>

			<?php
			$templateOptions = $wizTemplate['template_options'] ?? [];
			$templateSettings = $templateOptions['message_settings'] ?? [];
			$templateStyles = $templateOptions['template_styles'] ?? [];

			?>


			<div class="builder-tab-content wizard-tab-content" id="builder-tab-styles">
				<div class="builder-vertical-tabs">
					<div class="template-settings-tabs">
						<div class="template-settings-tab active" data-tab="template-styles-tab-header-and-footer">
							Header & Footer
						</div>
						<div class="template-settings-tab" data-tab="template-styles-tab-body-and-background">
							Body & Background
						</div>
						<div class="template-settings-tab" data-tab="template-styles-tab-text-and-links">
							Text & Links
						</div>
						<div class="template-settings-tab" data-tab="template-styles-tab-custom-styles" id="template-styles-custom-styles-tab">
							Custom Styles
						</div>
					</div>
					<div class="template-settings-tabs-content">
						<?php //print_r( $templateStyles );                                                           
						?>
						<form id="template-styles-form">
							<?php
							$templateHeaderFooterStyles = $templateStyles['header-and-footer'] ?? [];
							?>
							<fieldset name="header-and-footer" class="template-settings-tab-content active"
								id="template-styles-tab-header-and-footer">
								<h5>Template Header</h5>
								<div class="builder-field-group flex">
									<?php
									$showHeader = $templateHeaderFooterStyles['show_id_header'] ?? true;
									?>
									<div class="builder-field-wrapper">
										<label class="checkbox-toggle-label">Show Header</label>
										<div class="wiz-checkbox-toggle">
											<input type="checkbox" class="wiz-check-toggle" data-preview-part="standard_header"
												id="template_settings_show_id_header" name="show_id_header" hidden
												<?php echo $showHeader ? 'checked' : ''; ?>>
											<label for="template_settings_show_id_header"
												class="wiz-check-toggle-display <?php echo $showHeader ? 'active' : ''; ?>"><i
													class="<?php echo $showHeader ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
										</div>

									</div>

									<div class="builder-field-wrapper template-header-logo">
										<?php
										$templateHeaderLogo = $templateHeaderFooterStyles['template_header_logo'] ?? 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/cd7e1e87-607c-4e56-ad9c-0b0d34671949.png';
										?>
										<label for="template_header_logo">Header Image</label>
										<input type="text" value="<?php echo $templateHeaderLogo; ?>" id="template_header_logo" name="template_header_logo">
									</div>
									<div class="builder-field-group flex">
										<?php
										$useDarkModeHeader = $templateHeaderFooterStyles['show_dark_mode_header'] ?? false;
										?>
										<div class="builder-field-wrapper">
											<label class="checkbox-toggle-label">Dark Mode</label>
											<div class="wiz-checkbox-toggle">
												<input type="checkbox" class="wiz-check-toggle" data-preview-part="standard_header"
													id="template_settings_show_dark_mode_header" name="show_dark_mode_header" hidden
													<?php echo $useDarkModeHeader ? 'checked' : ''; ?>>
												<label for="template_settings_show_dark_mode_header"
													class="wiz-check-toggle-display <?php echo $useDarkModeHeader ? 'active' : ''; ?>"><i
														class="<?php echo $useDarkModeHeader ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
											</div>

										</div>

										<div class="builder-field-wrapper template-header-logo-dark-mode">
											<?php
											$templateHeaderDarkLogo = $templateHeaderFooterStyles['template_header_logo_dark_mode'] ?? 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/1-Logo.png';
											?>
											<label for="template_header_logo_dark_mode">Dark Mode Header Image</label>
											<input type="text" value="<?php echo $templateHeaderDarkLogo; ?>" id="template_header_logo_dark_mode" name="template_header_logo_dark_mode">
										</div>
									</div>

									<?php $headerPadding = $templateHeaderFooterStyles['header_padding'] ?? '20px 0'; ?>
									<div class='builder-field-wrapper header-padding small-input'><label
											for='header-chunk-padding'>Header Padding<span class="wiz-tooltip-trigger" data-tooltip="CSS shorthand: one value (all sides), two values (top/bottom, left/right), or four values (top, right, bottom, left)." tabindex="0">?</span></label>
										<input type='text' name='header_padding' id='header-padding' data-preview-part="standard_header"
											value='<?php echo $headerPadding; ?>'>
									</div>


								</div>
								<h5>Template Footer</h5>

								<div class="builder-field-group flex">
									<div class="builder-field-wrapper centered">
										<?php $showFooter = $templateHeaderFooterStyles['show_id_footer'] ?? true; ?>
										<label class="checkbox-toggle-label">Show Footer</label>
										<div class="wiz-checkbox-toggle">
											<input type="checkbox" class="wiz-check-toggle" data-preview-part="standard_footer"
												id="template_settings_show_id_footer" name="show_id_footer" hidden
												<?php echo $showFooter ? 'checked' : ''; ?>>
											<label for="template_settings_show_id_footer"
												class="wiz-check-toggle-display <?php echo $showFooter ? 'active' : ''; ?>"><i
													class="<?php echo $showFooter ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
										</div>

									</div>
									<div class="builder-field-wrapper centered">
										<?php $showUnsub = $templateHeaderFooterStyles['show_unsub'] ?? true; ?>
										<label class="checkbox-toggle-label">Show Unsub</label>
										<div class="wiz-checkbox-toggle">
											<input type="checkbox" class="wiz-check-toggle" data-preview-part="standard_footer"
												id="template_settings_show_unsub" name="show_unsub" hidden <?php echo $showUnsub ? 'checked' : ''; ?>>
											<label for="template_settings_show_unsub"
												class="wiz-check-toggle-display <?php echo $showUnsub ? 'active' : ''; ?>"><i
													class="<?php echo $showUnsub ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
										</div>

									</div>
								</div>
								<div class="builder-field-group flex">

									<?php
									$forceWhiteTextDevices = [
										[
											'id' => "footer_" . "force-white-text-desktop",
											'name' => 'footer_force_white_text_on_desktop',
											'display' => 'desktop',
											'value' => true,
											'label'
											=> '<i class="fa-solid fa-desktop"></i>'
										],
										[
											'id' => "footer_" . "force-white-text-mobile",
											'name' => 'footer_force_white_text_on_mobile',
											'display' => 'mobile',
											'value' => true,
											'label'
											=> '<i class="fa-solid fa-mobile-screen-button"></i>'
										]
									];

									$forceWhiteTextDesktop = $templateHeaderFooterStyles['footer_force_white_text_on_desktop'] ?? false;
									$forceWhiteTextMobile = $templateHeaderFooterStyles['footer_force_white_text_on_mobile'] ?? false;

									?>

									<div
										class='button-group-wrapper builder-field-wrapper chunk-force-white-text-devices'>
										<label class='button-group-label'>Force Gmail white text on:<span class="wiz-tooltip-trigger" data-tooltip="Use this to force text to remain white on backgrounds that won't invert in dark mode, like images." tabindex="0">?</span></label>
										<div class='button-group conditional checkbox'>
											<?php foreach ($forceWhiteTextDevices as $opt) { ?>

												<?php
												$fieldID = $opt['id'];
												$isChecked = isset($templateHeaderFooterStyles[$opt['name']]) && $templateHeaderFooterStyles[$opt['name']] ? 'checked' : '';
												?>

												<input type='checkbox' id='<?php echo $fieldID; ?>' data-preview-part='standard_footer'
													name='<?php echo $opt['name']; ?>'
													value='<?php echo $opt['value']; ?>' <?php echo $isChecked; ?>>
												<label for='<?php echo $fieldID; ?>' class='button-label'
													title='<?php echo $opt['display']; ?>'>
													<?php echo $opt['label']; ?>
												</label>

											<?php } ?>
										</div>
									</div>



								</div>
								<div class="builder-field-group flex">
									<div class="builder-field-wrapper template-footer-color">
										<?php
										$templateFooterColor = $templateHeaderFooterStyles['template_footer_color'] ?? 'transparent';
										?>
										<label for="template_styles_footer_color">Footer BG</label>
										<fieldset name="footer-background" id="template-footer-background">
											<?php echo generate_background_settings_module($templateHeaderFooterStyles['footer-background'] ?? [], '', false, 'standard_footer'); ?>
										</fieldset>
									</div>
								</div>

								<h5>Social Media Icons</h5>
								<div class="builder-field-group social-media-icons-group">
									<?php
									// Define social media icon slots (5 slots for flexibility)
									for ($i = 1; $i <= 5; $i++) {
										$iconImage = $templateHeaderFooterStyles["social_icon_{$i}_image"] ?? '';
										$iconLink = $templateHeaderFooterStyles["social_icon_{$i}_link"] ?? '';
										
										// Set default values for all 5 icons if empty (backward compatibility)
										if ($i === 1 && empty($iconImage)) {
											$iconImage = 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/iD%20Tech_Social%20Icon%20Updates__fb.png';
											$iconLink = 'https://www.facebook.com/computercamps';
										} elseif ($i === 2 && empty($iconImage)) {
											$iconImage = 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/iD%20Tech_Social%20Icon%20Updates__tt.png';
											$iconLink = 'https://www.tiktok.com/@idtechcamps';
										} elseif ($i === 3 && empty($iconImage)) {
											$iconImage = 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/iD%20Tech_Social%20Icon%20Updates__ig.png';
											$iconLink = 'https://www.instagram.com/idtech/';
										} elseif ($i === 4 && empty($iconImage)) {
											$iconImage = 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/iD%20Tech_Social%20Icon%20Updates__in.png';
											$iconLink = 'https://www.linkedin.com/company/id-tech-camps';
										} elseif ($i === 5 && empty($iconImage)) {
											$iconImage = 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/iD%20Tech_Social%20Icon%20Updates__yt.png';
											$iconLink = 'https://www.youtube.com/@idtechcamps';
										}
									?>
									<div class="social-icon-pair">
										<h6>Social Icon <?php echo $i; ?></h6>
										<div class="builder-field-wrapper">
											<label for="social_icon_<?php echo $i; ?>_image">Icon Image URL</label>
											<input type="text" 
												   id="social_icon_<?php echo $i; ?>_image" 
												   name="social_icon_<?php echo $i; ?>_image" 
												   value="<?php echo esc_attr($iconImage); ?>"
												   data-preview-part="standard_footer"
												   placeholder="Image URL">
										</div>
										<div class="builder-field-wrapper">
											<label for="social_icon_<?php echo $i; ?>_link">Icon Link URL</label>
											<input type="text" 
												   id="social_icon_<?php echo $i; ?>_link" 
												   name="social_icon_<?php echo $i; ?>_link" 
												   value="<?php echo esc_attr($iconLink); ?>"
												   data-preview-part="standard_footer"
												   placeholder="https://...">
										</div>
									</div>
									<?php } ?>
								</div>

							</fieldset>
							<fieldset name="body-and-background" class="template-settings-tab-content"
								id="template-styles-tab-body-and-background">
								<?php
								$bodyAndBackgroundStyles = $templateStyles['body-and-background'] ?? [];
								$templateWidth = $bodyAndBackgroundStyles['template_width'] ?? '648';
								$bodyBackground = $bodyAndBackgroundStyles['body-background'] ?? [];
								$pageBackground = $bodyAndBackgroundStyles['page-background'] ?? [];
								?>
								<div class="builder-field-group">
									<div class="builder-field-wrapper template_width">

										<h5>Template Width</h5>
										<span class="input-post-text-wrap"><input type="number" data-preview-part="fullTemplate"
												id="template_styles_template_width" name="template_width"
												class="builder-field" value="<?php echo $templateWidth; ?>"><span
												class="input-post-text">px</span></span>
									</div>
								</div>
								<fieldset name="body-background">
									<div class="builder-field-group">
										<div class="builder-field-wrapper body-background">
											<h5>Body Background</h5>
											<?php
											echo generate_background_settings_module($bodyBackground, 'body_background_', false, 'body_start');
											?>
										</div>
									</div>
								</fieldset>
								<fieldset name="page-background">
									<div class="builder-field-group">
										<div class="builder-field-wrapper body-background">
											<h5>Page Background</h5>
											<?php
											echo generate_background_settings_module($pageBackground, 'page_background_', false, 'body_start');
											?>
										</div>
									</div>

								</fieldset>
							</fieldset>
							<div class="template-settings-tab-content" id="template-styles-tab-text-and-links">
								<?php
								$templateFontStyles = $templateStyles['font-styles'] ?? [];
								?>
								<fieldset name="font-styles">
									<div class="builder-field-group inline">
										<div class='builder-field-wrapper flex'>
											<div class="builder-field-wrapper">
												<?php
												$templateFontSize = $templateFontStyles['template_font_size'] ?? '16px';
												?>
												<label for="template_styles_font_size">Font Size</label>
												<input type="text" id="template_styles_font_size" data-preview-part="fullTemplate"
													name="template_font_size" class="builder-field"
													value="<?php echo $templateFontSize; ?>">
											</div>
											<div class="builder-field-wrapper">
												<?php
												$templateLineHeight = $templateFontStyles['template_line_height'] ?? '1.5';
												?>
												<label for="template_styles_line_height">Line Height<span class="wiz-tooltip-trigger" data-tooltip="Controls spacing between lines of text. Use a number (1.5) or unit value (24px). Higher = more space." tabindex="0">?</span></label>
												<input type="text" id="template_styles_line_height"
													name="template_line_height" class="builder-field"
													value="<?php echo $templateLineHeight; ?>">
											</div>
										</div>
									</div>
								</fieldset>
								<fieldset name="text-styles">
									<?php
									$textStyles = $templateStyles['text-styles'] ?? [];
									$templateTextColor = $textStyles['text_styles_text_color'] ?? '#000000';
									$templateDarkModeTextColor = $textStyles['text_styles_dark_mode_text_color'] ?? '#FFFFFF';
									?>
									<div class="builder-field-group flex">
										<div class="builder-field-wrapper">
											<label for="text_styles_text_color">Text Color</label>
											<input class="builder-colorpicker" type="color" data-preview-part="fullTemplate"
												name="text_styles_text_color" id="text_styles_text_color"
												data-color-value="<?php echo $templateTextColor; ?>">
										</div>
										<div class="builder-field-wrapper">
											<label for="text_styles_dark_mode_text_color">Dark Mode Text Color</label>
											<input class="builder-colorpicker" type="color" data-preview-part="fullTemplate"
												name="text_styles_dark_mode_text_color" id="text_styles_dark_mode_text_color"
												data-color-value="<?php echo $templateDarkModeTextColor; ?>">
										</div>
									</div>
								</fieldset>
								<fieldset name="link-styles">
									<?php

									$linkStyles = $templateStyles['link-styles'] ?? [];

									$linkStyleOptions = [
										['id' => 'template_styles_underline_links', 'value' => 'underline', 'label' => '<strong><u style="font-family: serif;">U</u></strong>'],
										['id' => 'template_styles_italic_links', 'value' => 'italic', 'label' => '<strong><em style="font-family: serif;">I</em></strong>'],
										['id' => 'template_styles_bold_links', 'value' => 'bold', 'label' => '<strong style="font-family: serif;">B</strong>']
									];
									?>
									<div class="builder-field-group flex">
										<div class='button-group-wrapper builder-field-wrapper link-style-options'>
											<label>Link Styles</label>
											<div class="button-group checkbox">
												<?php foreach ($linkStyleOptions as $opt) : ?>
												<?php
												$defaultVal = $opt['value'] === 'underline';
												$isChecked = (isset($linkStyles[$opt['value']]) ? $linkStyles[$opt['value']] === true : $defaultVal) ? 'checked' : '';
												?>
													<input type='checkbox' id='<?php echo $opt['id']; ?>' data-preview-part="fullTemplate"
														name='<?php echo $opt['value']; ?>' value='true' <?php echo $isChecked; ?>>
													<label for='<?php echo $opt['id']; ?>' class='button-label'>
														<?php echo $opt['label']; ?>
													</label>
												<?php endforeach; ?>
											</div>
										</div>

										<div class="builder-field-wrapper template-link-color centered">
											<?php
											$templateLinkColor = $linkStyles['template_link_style_color'] ?? '#0073dd';
											?>
											<label for="template_style_link_color">Links</label>
											<input class="builder-colorpicker" type="color" data-preview-part="fullTemplate"
												name="template_link_style_color" id="template_style_link_color"
												data-color-value="<?php echo $templateLinkColor; ?>">
										</div>
										<div class="builder-field-wrapper template-visited-link-color centered">
											<?php
											$templateLinkHoverColor = $linkStyles['template_link_style_hover_color'] ?? $templateLinkColor;
											?>
											<label for="template_link_style_hover_color">Hover</label>
											<input class="builder-colorpicker" type="color" data-preview-part="fullTemplate"
												name="template_link_style_hover_color"
												id="template_link_style_hover_color"
												data-color-value="<?php echo $templateLinkHoverColor; ?>">
										</div>

									</div>
								</fieldset>
							</div>

							<fieldset name="custom-styles" class="template-settings-tab-content"
								id="template-styles-tab-custom-styles">
								<?php
								$customStyles = $templateStyles['custom-styles'] ?? [];
								$includeDarkModeSupport = $customStyles['dark-mode-support'] ?? false;
								?>
								<div class="builder-field-wrapper">
									<label class="checkbox-toggle-label">Include Dark Mode Support Meta Tag<span class="wiz-tooltip-trigger" data-tooltip="Adds meta tag that tells email clients (like Apple Mail) this template supports dark mode styling." tabindex="0">?</span></label>
									<div class="wiz-checkbox-toggle">
										<input type="checkbox" class="wiz-check-toggle" data-preview-part="email_head"
											id="template_styles_dark_mode_support" name="dark-mode-support" hidden
											<?php echo $includeDarkModeSupport ? 'checked' : ''; ?>>
										<label for="template_styles_dark_mode_support"
											class="wiz-check-toggle-display <?php echo $includeDarkModeSupport ? 'active' : ''; ?>"><i
												class="<?php echo $includeDarkModeSupport ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
									</div>

								</div>

								<?php

								?>
								<div class="builder-field-wrapper block">
									<label for="template_styles_additional_css">Additional CSS</label>
									<div class="field-description"><?php echo htmlspecialchars('<style></style>'); ?> tags must be used. Multiple are allowed.</div>
									<textarea id="template_styles_additional_css" name="additional_template_css" data-preview-part="email_head"
										class="builder-field"><?php echo $customStyles['additional_template_css'] ?? ''; ?></textarea>
								</div>

								<?php // Hidden source-of-truth for custom <head> HTML. Edited via the modal in the code (</>) tab. ?>
								<textarea id="template_styles_additional_head_html" name="additional_head_html" hidden><?php echo $customStyles['additional_head_html'] ?? ''; ?></textarea>

							</fieldset>

						</form>
					</div>
				</div>
			</div>
			<div class="builder-tab-content wizard-tab-content" id="builder-tab-message-settings">
				<!-- Template Settings Section -->
				<div class="template-settings">
					<form id="template-settings-form">
						<?php
						$email_type = $templateSettings['email_type'] ?? 'promotional';
						$message_type_id = $messageSettings['message_type_id'] ?? '';
						
						// Set default message type ID based on email type if not set
						if (empty($message_type_id)) {
							$message_type_id = $email_type === 'transactional' ? '52620' : '52634';
						}
						?>
						<div class="builder-field-group">
							<div class="builder-field-wrapper">
								<label>Email Channel & Message Type</label>
								<div class="button-group radio">
									<div class="channel-toggles">
										<input type="radio" id="template_settings_email_type_promotional"
											name="email_type" value="promotional" hidden <?php echo $email_type == 'promotional' ? 'checked' : ''; ?>>
										<label for="template_settings_email_type_promotional"
											class="button-label"><i class="fa-solid fa-bullhorn"></i>
											Promotional</label>
										
										<input type="radio" id="template_settings_email_type_transactional"
											name="email_type" value="transactional" hidden <?php echo $email_type == 'transactional' ? 'checked' : ''; ?>>
										<label for="template_settings_email_type_transactional"
											class="button-label"><i class="fa-solid fa-file-invoice-dollar"></i>
											Transactional</label>
									</div>
									
									<div class="message-types-wrapper">
										<div class="message-types promotional-types <?php echo $email_type == 'promotional' ? 'active' : ''; ?>">
											<select class="message-type-select" <?php echo $email_type != 'promotional' ? 'disabled' : ''; ?>>
												<option value="52634" <?php echo $message_type_id == '52634' ? 'selected' : ''; ?>>Program News & Offers</option>
												<option value="52641" <?php echo $message_type_id == '52641' ? 'selected' : ''; ?>>Event Invites & Updates</option>
												<option value="52635" <?php echo $message_type_id == '52635' ? 'selected' : ''; ?>>Referral Progress Reports</option>
												<option value="52636" <?php echo $message_type_id == '52636' ? 'selected' : ''; ?>>Tech & Education Insights</option>
											</select>
										</div>
										<div class="message-types transactional-types <?php echo $email_type == 'transactional' ? 'active' : ''; ?>">
											<select class="message-type-select" <?php echo $email_type != 'transactional' ? 'disabled' : ''; ?>>
												<option value="52620" <?php echo $message_type_id == '52620' ? 'selected' : ''; ?>>Transactional</option>
											</select>
										</div>
									</div>
								</div>
								<input type="hidden" id="template_settings_message_type_id" name="message_type_id" value="<?php echo esc_attr($message_type_id); ?>">
							</div>
						</div>
						<div class="builder-field-group flex distribute">
							<div class="builder-field-wrapper block">
								<label for="template_settings_subject_line">Subject Line</label>

								<input type="text" id="template_settings_subject_line" name="subject_line"
									class="builder-field"
									value="<?php echo isset($templateSettings['subject_line']) ? esc_attr($templateSettings['subject_line']) : ''; ?>">
								<div class="message-settings-merge-tags" data-field="#template_settings_subject_line">
									Insert:
									<div class="insert-merge-tag" data-insert="{{{snippet 'FirstName' 'your child'}}}">
										Student First</div>
									<div class="insert-merge-tag" data-insert="{{defaultIfEmpty 'FirstName' 'Friend'}}">
										Client First</div>
								</div>
							</div>

							<div class="builder-field-wrapper block">
								<label for="template_settings_preview_text">Preview Text</label>

								<input type="text" id="template_settings_preview_text" name="preview_text"
									class="builder-field"
									value="<?php echo isset($templateSettings['preview_text']) ? esc_attr($templateSettings['preview_text']) : ''; ?>">
								<div class="message-settings-merge-tags" data-field="#template_settings_preview_text">
									Insert:
									<div class="insert-merge-tag" data-insert="{{{snippet 'FirstName' 'your child'}}}">
										Student First</div>
									<div class="insert-merge-tag" data-insert="{{defaultIfEmpty 'FirstName' 'Friend'}}">
										Client First</div>
								</div>
							</div>
						</div>
						<div class="builder-field-group flex distribute">
							<div class="builder-field-wrapper block">
								<label for="template_settings_from_name">From Name</label>

								<input type="text" id="template_settings_from_name" name="from_name"
									class="builder-field"
									value="<?php echo $templateSettings['from_name'] ?? 'iD Tech Camps'; ?>">
							</div>

							<div class="builder-field-wrapper block">
								<label for="template_settings_reply_to">Reply To</label>
								<input type="email" id="template_settings_reply_to" name="reply_to"
									class="builder-field"
									value="<?php echo $templateSettings['reply_to'] ?? 'hello@idtech.com'; ?>">
							</div>
						</div>

						<div class="builder-field-wrapper block">
							<label for="template_settings_fine_print_disclaimer">Fine Print/Disclaimer</label>
							<textarea id="template_settings_fine_print_disclaimer" name="fine_print_disclaimer" data-preview-part="fine_print"
								class="builder-field"><?php echo $templateSettings['fine_print_disclaimer'] ?? ''; ?></textarea>
						</div>
						<div class="builder-field-group flex noWrap">
							<div class="builder-field-wrapper block">
								<label for="template_settings_ga_campaign_name">GA Campaign Name</label>

								<input type="text" id="template_settings_ga_campaign_name" name="ga_campaign_name"
									class="builder-field"
									value="<?php echo isset($templateSettings['ga_campaign_name']) ? $templateSettings['ga_campaign_name'] : '{{campaignId}}'; ?>">
								<div class="field-description">Leave blank to turn off GA tracking. <code>{{campaignId}}</code> = Iterable campaign ID.</div>
							</div>
							<div class="builder-field-wrapper block">
								<label>UTM Parameters</label>
								<fieldset name="utm_parameters" data-save-as="key_value_pairs">
									<?php
									$utmParams = $templateSettings['utm_parameters'] ?? [];
									if (!empty($utmParams)) {
										$paramIndex = 0;
										foreach ($utmParams as $key => $value) {
											echo get_utm_term_fieldset($paramIndex, $key, $value);
											$paramIndex++;
										}
									} else {
										echo '<div class="no-utm-message field-description">No UTM parameters set.</div>';
									}
									?>
								</fieldset>
								<button class="wiz-button small green" id="add_utm_parameter">Add Parameter</button>
							</div>
						</div>
						<h4>Gmail annotations</h4>
						<fieldset name="gmail-annotations">
							<div class="builder-field-group flex distribute">
								<div class="builder-field-wrapper">
									<label>Description</label>
									<input type="text" id="template_settings_gmail_description" name="gmail_description" class="builder-field" value="<?php echo isset($templateSettings['gmail-annotations']['gmail_description']) ? $templateSettings['gmail-annotations']['gmail_description'] : ''; ?>">
								</div>
								<div class="builder-field-wrapper">
									<label>Promo Code</label>
									<input type="text" id="template_settings_gmail_promo_code" name="gmail_promo_code" class="builder-field" value="<?php echo isset($templateSettings['gmail-annotations']['gmail_promo_code']) ? $templateSettings['gmail-annotations']['gmail_promo_code'] : ''; ?>">
								</div>
								<div class="builder-field-wrapper">
									<label>Start Date</label>
									<input type="date" id="template_settings_gmail_start_date" name="gmail_start_date" class="builder-field" value="<?php echo isset($templateSettings['gmail-annotations']['gmail_start_date']) ? $templateSettings['gmail-annotations']['gmail_start_date'] : ''; ?>">
								</div>
								<div class="builder-field-wrapper">
									<label>End Date</label>
									<input type="date" id="template_settings_gmail_end_date" name="gmail_end_date" class="builder-field" value="<?php echo isset($templateSettings['gmail-annotations']['gmail_end_date']) ? $templateSettings['gmail-annotations']['gmail_end_date'] : ''; ?>">
								</div>
							</div>
							<div class="builder-field-group flex distribute">
								<div class="builder-field-wrapper">
									<label>Image URL</label>
									<input type="text" id="template_settings_gmail_image_url" name="gmail_image_url" class="builder-field" value="<?php echo isset($templateSettings['gmail-annotations']['gmail_image_url']) ? $templateSettings['gmail-annotations']['gmail_image_url'] : ''; ?>">
								</div>
								<div class="builder-field-wrapper">
									<label>Image Link</label>
									<input type="text" id="template_settings_gmail_image_link" name="gmail_image_link" class="builder-field" value="<?php echo isset($templateSettings['gmail-annotations']['gmail_image_link']) ? $templateSettings['gmail-annotations']['gmail_image_link'] : ''; ?>">
								</div>
							</div>
						</fieldset>

					</form>
				</div>
			</div>


			<div class="builder-tab-content wizard-tab-content" id="builder-tab-mocks">
				<?php

				?>
				<div class="mockup-tabs-wrapper">
					<ul class="mockup-tabs">
						<li class="active" data-tab="desktop-mockup"><i class="fas fa-desktop"></i>&nbsp;&nbsp;Desktop</li>
						<li data-tab="mobile-mockup"><i class="fas fa-mobile-alt"></i>&nbsp;&nbsp;Mobile</li>
					</ul>
				</div>
				<?php
				$mockups = $wizTemplate['mockups'] ?? [];
				$mockup_types = ['desktop', 'mobile'];
				foreach ($mockup_types as $index => $type) : ?>
					<?php $mockup = $mockups[$type] ?? ''; ?>
					<div class="mockup-tab-content<?php echo $index === 0 ? '' : ' hidden'; ?>"
						id="<?php echo $type; ?>-mockup">
						<div class="mockup-uploader<?php echo empty($mockup) ? '' : ' hidden'; ?>">
							<input type="file" class="mockup-upload-field" id="<?php echo $type; ?>-mockup-upload"
								name="<?php echo $type; ?>-mockup" accept=".jpg, .jpeg, .png, .gif, .webp"
								data-preview=".<?php echo $type; ?>-mockup-preview"
								data-url="#<?php echo $type; ?>-mockup-url">
							<label for="<?php echo $type; ?>-mockup-upload" class="upload-label">Drag and drop or click
								to
								upload</label>
						</div>
						<div
							class="mockup-display <?php echo $type; ?>-mockup-preview<?php echo empty($mockup) ? ' hidden' : ''; ?>">
							<div class="mockup-actions">
								<button class="wiz-button green upload-new-mock" data-type="<?php echo $type; ?>">Upload
									New</button>
								<button class="wiz-button red remove-mock" data-type="<?php echo $type; ?>"><i
										class="fa-solid fa-xmark"></i></button>
							</div>
							<div class="mockup-image <?php echo $type; ?>-mockup-image">
								<img src="<?php echo $mockup; ?>" />
							</div>
						</div>
						<input type="hidden" id="<?php echo $type; ?>-mockup-url" name="<?php echo $type; ?>-mockup-url"
							value="<?php echo $mockup; ?>">
					</div>
				<?php endforeach; ?>
			</div>

			<div class="builder-tab-content wizard-tab-content" id="builder-tab-code">
				<div class="builder-code-wrapper">
					<div class="builder-code-actions">
						<button id="copyCode" data-code-in="#templateCode" class="wiz-button green"><i
								class="fa-solid fa-copy"></i>&nbsp;&nbsp;Copy
							HTML</button>
						<button id="viewMsoCode" class="wiz-button green" data-post-id="<?php echo $postId ?>"><i
								class="fa-solid fa-code"></i>&nbsp;&nbsp;View MSO Code</button>
						<button id="editHeadCode" class="wiz-button green" data-post-id="<?php echo $postId ?>"><i
								class="fa-solid fa-pen-to-square"></i>&nbsp;&nbsp;Edit &lt;head&gt; Code</button>
						<button id="viewJson" class="wiz-button green" data-post-id="<?php echo $postId ?>"><i
								class="fa-solid fa-code"></i>&nbsp;&nbsp;View JSON</button>
						<button id="exportJson" class="wiz-button green" data-post-id="<?php echo $postId ?>">
							<i class="fa-solid fa-file-export"></i>&nbsp;&nbsp;Export JSON</button>
						<button id="importJson" class="wiz-button green" data-post-id="<?php echo $postId ?>">
							<i class="fa-solid fa-file-import"></i>&nbsp;&nbsp;Import JSON</button>
					</div>
					<pre
						id="templateCode"><code><?php echo 'Loading HTML...'; ?></code></pre>
				</div>
			</div>

			<div class="builder-tab-content wizard-tab-content" id="builder-tab-settings">
				<div class="builder-field-group">
					<h4>Sync Settings</h4>
					<?php echo get_iterable_sync_fields($postId, $templateOptions['template_settings']['iterable-sync'] ?? []); ?>
				</div>
				<div class="builder-field-group">
					<h4>Interface Settings</h4>
					<fieldset name="interface-settings">
						<div class="builder-field-wrapper centered">
							<label class="checkbox-toggle-label">Save Collapse States<span class="wiz-tooltip-trigger" data-tooltip="Remember which sections are expanded/collapsed when you save. Useful for keeping your workspace organized." tabindex="0">?</span></label>
							<div class="wiz-checkbox-toggle">
								<?php $saveCollapseStates = $templateOptions['template_settings']['interface-settings']['save_collapse_states'] ?? true; ?>
								<input type="checkbox" class="wiz-check-toggle"
									id="builder_settings_save_collapse_states" name="save_collapse_states" hidden
									<?php echo $saveCollapseStates ? 'checked' : ''; ?>>
								<label for="builder_settings_save_collapse_states"
									class="wiz-check-toggle-display <?php echo $saveCollapseStates ? 'active' : ''; ?>"><i
										class="<?php echo $saveCollapseStates ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
							</div>

						</div>
						<div class="builder-field-wrapper centered">
							<label class="checkbox-toggle-label">Auto-Collapse Rows</label>
							<div class="wiz-checkbox-toggle">
								<?php $autoCollapseRows = $templateOptions['template_settings']['interface-settings']['auto_collapse_rows'] ?? true; ?>
								<input type="checkbox" class="wiz-check-toggle"
									id="builder_settings_auto_collapse_rows" name="auto_collapse_rows" hidden <?php echo $autoCollapseRows ? 'checked' : ''; ?>>
								<label for="builder_settings_auto_collapse_rows"
									class="wiz-check-toggle-display <?php echo $autoCollapseRows ? 'active' : ''; ?>"><i
										class="<?php echo $autoCollapseRows ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
							</div>

						</div>
						<div class="builder-field-wrapper centered">
							<label class="checkbox-toggle-label">Auto-Collapse Column Sets</label>
							<div class="wiz-checkbox-toggle">
								<?php $autoCollapseColsets = $templateOptions['template_settings']['interface-settings']['auto_collapse_columnsets'] ?? true; ?>
								<input type="checkbox" class="wiz-check-toggle"
									id="builder_settings_auto_collapse_columnsets" name="auto_collapse_columnsets"
									hidden <?php echo $autoCollapseColsets ? 'checked' : ''; ?>>
								<label for="builder_settings_auto_collapse_columnsets"
									class="wiz-check-toggle-display <?php echo $autoCollapseColsets ? 'active' : ''; ?>"><i
										class="<?php echo $autoCollapseColsets ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
							</div>

						</div>
						<div class="builder-field-wrapper centered">
							<label class="checkbox-toggle-label">Auto-Collapse chunks</label>
							<div class="wiz-checkbox-toggle">
								<?php $autoCollapsechunks = $templateOptions['template_settings']['interface-settings']['auto_collapse_chunks'] ?? true; ?>
								<input type="checkbox" class="wiz-check-toggle"
									id="builder_settings_auto_collapse_chunks" name="auto_collapse_chunks" hidden
									<?php echo $autoCollapsechunks ? 'checked' : ''; ?>>
								<label for="builder_settings_auto_collapse_chunks"
									class="wiz-check-toggle-display <?php echo $autoCollapsechunks ? 'active' : ''; ?>"><i
										class="<?php echo $autoCollapsechunks ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
							</div>

						</div>
					</fieldset>
				</div>


			</div>

		</div> <!-- End .builder-pane-->

	</div>

	<div class="right panel-right" id="preview" type="text/html">

		<?php
		echo get_template_actions_bar($postId);
		?>


		<div id="templatePreview">

			<div id="templatePreview-status">
			</div>
			<iframe id="previewFrame" src="<?php echo home_url('template-frame/' . $postId); ?>"></iframe>
		</div>

	</div>
</div>





<?php
get_footer();
?>