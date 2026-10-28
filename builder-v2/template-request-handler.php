<?php
add_action('template_redirect', 'idwiz_handle_preview_frame_redirect', 20);
// Loads our default iframe content (styles and initial loader)
function idwiz_handle_preview_frame_redirect()
{
	global $wp_query;

	// Handle build-template
	if (isset($wp_query->query_vars['template-frame'])) {

		// Preview pane styles
		include dirname(plugin_dir_path(__FILE__)) . '/builder-v2/preview-pane-styles.html';

		echo '<div class="template-preview-loader">Loading template...</div>';
		exit;
	}
}
