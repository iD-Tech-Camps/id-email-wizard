<?php get_header(); ?>

<?php
$queried_object = get_queried_object();

$options    = get_option('idemailwiz_settings');
$trashTerm  = (int) $options['folder_trash'];
$baseFolder = (int) $options['folder_base'];

$search_query         = (string) get_query_var('idwiz_q');
$is_search_view       = $search_query !== '';
$current_folder_id    = ( $is_search_view || is_post_type_archive() || ! is_object( $queried_object ) ) ? 0 : $queried_object->term_id;
$is_folder_fav        = $current_folder_id ? is_user_favorite($current_folder_id, 'Folder') : false;
$is_trash_view        = ($trashTerm == $current_folder_id);
$template_archive_url = get_post_type_archive_link('idemailwiz_template');
$search_home_url      = $is_search_view ? home_url('/templates/all/') : ( is_object($queried_object) && ! is_post_type_archive() ? get_term_link($queried_object) : home_url('/templates/all/') );
?>

<header class="wizHeader wizHeader--inline">
	<div class="wizHeaderInnerWrap">
		<div class="wizHeader-left">
			<h1 class="wizEntry-title single-wizcampaign-title" itemprop="name">Templates</h1>
			<h2 id="saved_state_title"></h2>
		</div>
		<div class="wizHeader-right">
			<div class="wizHeader-actions">
				<div class="wiz-button outline" id="toggleFolderDrawer" title="Browse folders" aria-expanded="false" aria-controls="folderDrawer"><i class="fa-solid fa-folder-tree"></i>&nbsp;&nbsp;Browse</div>

				&nbsp;&nbsp;|&nbsp;&nbsp;

				<?php if (!$is_search_view && $current_folder_id && $current_folder_id != $baseFolder && !$is_trash_view) { ?>
					<div class="wiz-button outline addRemoveFavorite<?php echo $is_folder_fav ? ' is-favorite' : ''; ?>" title="Add/Remove Favorite" data-objecttype="Folder" data-objectid="<?php echo $current_folder_id; ?>"><i class="fa-solid fa-star"></i></div>

					<div class="wiz-button green moveFolder" title="Move Folder" data-folderid="<?php echo $current_folder_id; ?>"><i class="fa-solid fa-folder-tree"></i></div>

					<div class="wiz-button red deleteFolder" title="Delete Folder" data-postid="<?php echo get_the_ID(); ?>"><i class="fa fa-trash"></i></div>

					&nbsp;&nbsp;|&nbsp;&nbsp;
				<?php } ?>
				<div class="wiz-button green" id="addNewFolder"><i title="Add new folder" class="fa-solid fa-folder-plus"></i>&nbsp;&nbsp;New Folder</div>

				<div class="wiz-button green show-new-template-ui"><i class="fa-solid fa-plus"></i>&nbsp;&nbsp;New
					Template</div>
			</div>
		</div>
	</div>
</header>

<div class="folder-drawer-backdrop" aria-hidden="true"></div>
<aside id="folderDrawer" class="folder-drawer" aria-hidden="true" aria-label="Folders">
	<div class="folder-drawer-inner">
		<div class="folder-drawer-header">
			<h3>Folders</h3>
			<button type="button" class="folder-drawer-close" aria-label="Close folders"><i class="fa-solid fa-xmark"></i></button>
		</div>
		<div class="folderList">
			<?php get_folder_list($current_folder_id); ?>
		</div>
	</div>
</aside>

<div class="templateFolder">
	<div class="templateTable">
		<div class="folder-breadcrumb">
			<?php if ($is_search_view) { ?>
				Search results for &ldquo;<?php echo esc_html($search_query); ?>&rdquo;
			<?php } else {
				echo display_folder_hierarchy();
			} ?>
		</div>
		<div class="templateToolbar">
			<select id="bulkActionsSelect" name="bulkActionsSelect" disabled="true">
				<option disabled selected="true">Bulk Actions</option>
				<?php if ($is_trash_view) { ?>
					<option value="restore">Restore</option>
				<?php } else { ?>
					<option value="move">Move</option>
					<option value="delete">Delete</option>
				<?php } ?>
			</select>
			<form id="search-templates-form" method="get" action="<?php echo esc_url( home_url('/templates/search/') ); ?>" role="search" data-search-base="<?php echo esc_url( home_url('/templates/search/') ); ?>" data-home-url="<?php echo esc_url($search_home_url); ?>">
				<div id="search-templates">
					<input type="search" name="q" id="live-template-search" placeholder="Search templates..." autocomplete="off" value="<?php echo esc_attr($search_query); ?>" />
				</div>
			</form>
		</div>

		<div class="templateTable-results">

		<?php
		$child_terms = get_terms(array(
			'taxonomy'   => 'idemailwiz_folder',
			'parent'     => $current_folder_id,
			'hide_empty' => false,
		));

		$ordered_subfolders = array();
		if (!$is_search_view && !empty($child_terms) && !is_wp_error($child_terms)) {
			$faves    = array();
			$nonFaves = array();
			foreach ($child_terms as $term) {
				$term->is_favorite = is_user_favorite($term->term_id, 'Folder');
				if ($term->is_favorite) {
					$faves[] = $term;
				} else {
					$nonFaves[] = $term;
				}
			}
			$ordered_subfolders = array_merge($faves, $nonFaves);
		}

		$has_rows = have_posts() || !empty($ordered_subfolders);
		?>

		<?php if ($has_rows) { ?>
			<table class="templateList">
				<thead>
					<tr>
						<th class="col-select"><input type="checkbox" id="selectAllRows" aria-label="Select all" /></th>
						<th class="col-name">Name</th>
						<th class="col-date">Updated</th>
						<th class="col-actions">Actions</th>
						<th class="col-sync"><?php if (!$is_trash_view) { ?>Sync<?php } ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($ordered_subfolders as $subfolder) {
						$isFav     = $subfolder->is_favorite;
						$folderUrl = get_term_link($subfolder);
						$rowClass  = trim('row-folder ' . ($isFav ? 'favorite' : ''));
					?>
						<tr class="<?php echo esc_attr($rowClass); ?>" data-objectid="<?php echo $subfolder->term_id; ?>" data-type="folder" data-foldertitle="<?php echo esc_attr($subfolder->name); ?>">
							<td class="col-select">
								<input type="checkbox" class="row-select" data-type="folder" data-objectid="<?php echo $subfolder->term_id; ?>" aria-label="Select folder <?php echo esc_attr($subfolder->name); ?>" />
							</td>
							<td class="col-name">
								<i class="row-icon fa-solid fa-folder"></i>
								<a class="row-name-link" href="<?php echo esc_url($folderUrl); ?>"><?php echo esc_html($subfolder->name); ?></a>
							</td>
							<td class="col-date"></td>
							<td class="templateActions col-actions">
								<i class="fa-solid fa-star addRemoveFavorite favItem<?php echo $isFav ? ' is-favorite' : ''; ?>" title="Favorite Folder" data-objecttype="Folder" data-objectid="<?php echo $subfolder->term_id; ?>"></i>
								<i class="fa-solid fa-folder-tree moveFolder" title="Move Folder" data-folderid="<?php echo $subfolder->term_id; ?>"></i>
								<i class="fa fa-trash deleteFolder" title="Delete Folder" data-folderid="<?php echo $subfolder->term_id; ?>"></i>
							</td>
							<td class="col-sync"></td>
						</tr>
					<?php } ?>

					<?php while (have_posts()) {
						the_post();
						$post_title               = get_the_title();
						$post_link                = get_permalink();
						$post_author              = get_the_author();
						$post_modified_ts         = (int) get_post_modified_time('U', true);
						$post_modified_full       = get_post_modified_time('M j, Y g:i A');
						$post_modified_iso        = get_post_modified_time('c', true);
						$post_modified_relative   = human_time_diff($post_modified_ts, current_time('timestamp', true)) . ' ago';
						$post_created_full        = get_the_time('M j, Y');
						$post_modified_by_id      = get_post_field('post_modified_by', get_the_ID());
						$post_modified_by         = get_userdata($post_modified_by_id);
						$post_modified_author     = !empty($post_modified_by) ? $post_modified_by->display_name : $post_author;
						$is_template_fav          = is_user_favorite(get_the_ID(), 'Template');
						$trashedClass             = $is_trash_view ? 'trashed' : '';
						$favClass                 = $is_template_fav ? 'favorite' : '';
						$rowClasses               = trim('row-template ' . $favClass . ' ' . $trashedClass);
						$updated_tooltip          = sprintf(
							'Updated %s by %s · Created %s by %s',
							$post_modified_full,
							$post_modified_author,
							$post_created_full,
							$post_author
						);

						$wizTemplateData  = function_exists('get_wiztemplate') ? get_wiztemplate(get_the_ID()) : array();
						$iterableSync     = $wizTemplateData['template_options']['template_settings']['iterable-sync'] ?? array();
						$itTemplateId     = $iterableSync['iterable_template_id'] ?? '';
						$syncHistory      = $iterableSync['synced_templates_history'] ?? array();
						$syncCount        = is_array($syncHistory) ? count($syncHistory) : 0;

						// "Sent" = a campaign in Iterable uses this template (or one it was synced to before).
						$campaignsByTemplate = wiz_iterable_campaigns_by_template() ?? array();
						$templateCampaign = null;
						foreach (array_merge(array($itTemplateId), array_column(is_array($syncHistory) ? $syncHistory : array(), 'template_id')) as $entryId) {
							if ((int) $entryId && !empty($campaignsByTemplate[(int) $entryId])) {
								$templateCampaign = $campaignsByTemplate[(int) $entryId][0];
								break;
							}
						}
						$iterableStatus   = $templateCampaign ? '<a class="sentIndicator" href="https://app.iterable.com/campaigns/' . $templateCampaign['id'] . '?view=Summary"> <i class="fa-regular fa-circle-check"></i> Sent</a>' : null;
					?>
						<tr id="template-<?php echo get_the_ID(); ?>" class="<?php echo esc_attr($rowClasses); ?>" data-foldertitle="<?php echo esc_attr(get_the_title()); ?>" data-objectid="<?php echo get_the_ID(); ?>" data-type="template">
							<td class="col-select">
								<input type="checkbox" class="row-select" data-type="template" data-objectid="<?php echo get_the_ID(); ?>" aria-label="Select template <?php echo esc_attr($post_title); ?>" />
							</td>
							<td class="col-name">
								<?php if (($wizTemplateData['template_type'] ?? 'email') === 'sms') { ?>
									<i class="row-icon fa-solid fa-comment-sms" title="SMS template"></i>
								<?php } else { ?>
									<i class="row-icon fa-regular fa-file"></i>
								<?php } ?>
								<?php if (!$is_trash_view) { ?>
									<a class="row-name-link" href="<?php echo esc_url($post_link); ?>"><?php echo esc_html($post_title); ?></a>
									<?php echo $iterableStatus; ?>
								<?php } else { ?>
									<span class="row-name-link"><?php echo esc_html($post_title); ?></span>
								<?php } ?>
							</td>
							<td class="col-date">
								<time datetime="<?php echo esc_attr($post_modified_iso); ?>" title="<?php echo esc_attr($updated_tooltip); ?>">
									<?php echo esc_html($post_modified_relative); ?>
								</time>
							</td>
							<td class="templateActions col-actions">
								<?php if (!$is_trash_view) { ?>
									<i class="fa-solid fa-star addRemoveFavorite favItem<?php echo $is_template_fav ? ' is-favorite' : ''; ?>" title="Favorite Template" data-objecttype="Template" data-objectid="<?php echo get_the_ID(); ?>"></i>
									<span class="moveTemplate" title="Move Template" data-postid="<?php echo get_the_ID(); ?>"><i class="fa-solid fa-folder-tree"></i></span>
									<i class="fa fa-copy duplicate-template" title="Duplicate Template" data-postid="<?php echo get_the_ID(); ?>"></i>
									<i class="fa fa-trash delete-template" title="Delete Template" data-postid="<?php echo get_the_ID(); ?>"></i>
								<?php } else { ?>
									<i title="Restore from trash" class="fa fa-trash-arrow-up restore-template" data-postid="<?php echo get_the_ID(); ?>"></i>
								<?php } ?>
							</td>
							<td class="col-sync">
								<?php
								if (!$is_trash_view) {
									if ($itTemplateId) {
										$extraSyncs = max(0, $syncCount - 1);
										echo 'T: <a href="https://app.iterable.com/templates/editor?templateId=' . esc_attr($itTemplateId) . '" target="_blank" rel="noopener">' . esc_html($itTemplateId) . '</a>';
										if ($extraSyncs > 0) {
											echo ' <span class="sync-extra" title="' . esc_attr($syncCount . ' synced template IDs total') . '">+' . (int) $extraSyncs . '</span>';
										}
										if ($templateCampaign) {
											echo '<br/>C: <a href="https://app.iterable.com/campaigns/' . esc_attr($templateCampaign[0]['id']) . '?view=Summary" target="_blank" rel="noopener">' . esc_html($templateCampaign[0]['id']) . '</a>';
										}
									} else {
										echo ' — ';
									}
								}
								?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
			<?php
			global $wp_query;
			$total_pages  = $wp_query->max_num_pages;
			$current_page = max(1, get_query_var('paged'));
			echo '<div class="pagination">';
			echo paginate_links(array(
				'base'    => get_pagenum_link(1) . '%_%',
				'format'  => 'page/%#%',
				'current' => $current_page,
				'total'   => $total_pages,
			));
			echo '</div>';
			?>
		<?php } else if ($is_search_view) { ?>
			<p class="empty-message"><em>No templates match &ldquo;<?php echo esc_html($search_query); ?>&rdquo;.</em></p>
		<?php } else { ?>
			<p class="empty-message"><em>This folder is empty....</em></p>
		<?php } ?>
		</div><!-- /.templateTable-results -->
	</div>
</div>
<?php get_footer(); ?>
