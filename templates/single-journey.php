<?php get_header(); ?>
<?php
// Retrieve the journey ID from the URL
$journeyId = $_GET['id'] ?? false;

// Check if the startDate and endDate parameters are present in the $_GET array, if not, default
$startDate = $_GET['startDate'] ?? date('Y-m-01');
$endDate = $_GET['endDate'] ?? date('Y-m-d');

if ($journeyId && get_idwiz_journey($journeyId)) {
	$journey = get_idwiz_journey($journeyId);

	// Get ALL campaigns associated with this journey (we'll filter inactive via JS)
	$journeyCampaigns = get_idwiz_campaigns([
		'workflowId' => $journeyId
	]);

	// Separate active and inactive campaign IDs for sync button
	$activeCampaignIds = [];
	foreach ($journeyCampaigns as $campaign) {
		if ($campaign['campaignState'] === 'Running' || $campaign['campaignState'] === 'Finished') {
			$activeCampaignIds[] = $campaign['id'];
		}
	}
	$journeyCampaignIds = !empty($activeCampaignIds) ? $activeCampaignIds : array_column($journeyCampaigns, 'id');
	$journeyName = $journey['name'];

	// Calculate journey send dates from campaigns
	$journeyFirst = null;
	$journeyLast = null;
	
	foreach ($journeyCampaigns as $campaign) {
		if (isset($campaign['startAt'])) {
			$journeyFirst = $journeyFirst === null ? $campaign['startAt'] : min($journeyFirst, $campaign['startAt']);
			$journeyLast = $journeyLast === null ? $campaign['startAt'] : max($journeyLast, $campaign['startAt']);
		}
	}

	$journeyStartDate = $journeyFirst ? date('m/d/Y', (int)($journeyFirst / 1000)) : 'N/A';
	$journeyEndDate = $journeyLast ? date('m/d/Y', (int)($journeyLast / 1000)) : 'N/A';

	$campaigns = $journeyCampaigns;
?>
	<?php $activeTab = $_GET['view'] ?? 'Campaigns'; ?>
	<header class="wizHeader single-journey-header">
		<h1 class="wizEntry-title single-wizcampaign-title" title="<?php echo esc_attr(is_array($journeyName) ? implode(', ', $journeyName) : $journeyName); ?>" itemprop="name">
			<?php echo esc_html(is_array($journeyName) ? implode(', ', $journeyName) : $journeyName); ?>
		</h1>
		<div class="wizHeaderInnerWrap">
			<div class="wizHeader-left">
				<div class="wizEntry-meta">
					<strong>Journey</strong>
					<a href="https://app.iterable.com/workflows/<?php echo $journeyId; ?>/edit" target="_blank" title="View in Iterable">
						<?php echo $journeyId; ?>
					</a>
					<?php if (!empty($journey['journeyType'])): ?>
						&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
						<span class="journey-type"><?php echo esc_html(is_array($journey['journeyType']) ? implode(', ', $journey['journeyType']) : $journey['journeyType']); ?></span>
					<?php endif; ?>
					&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
					<?php echo count($journeyCampaigns); ?> campaigns
					&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
					Send dates: <?php echo $journeyStartDate; ?> - <?php echo $journeyEndDate; ?>
					<?php 
					// Show journey status
					$journeyStatus = '';
					if (isset($journey['enabled']) && !$journey['enabled']) {
						$journeyStatus = 'Disabled';
					} elseif (isset($journey['isArchived']) && $journey['isArchived']) {
						$journeyStatus = 'Archived';
					} else {
						$journeyStatus = 'Active';
					}
					?>
					&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
					Status: <span class="journey-status-text <?php echo strtolower($journeyStatus); ?>"><?php echo $journeyStatus; ?></span>
				</div>
				<div id="header-tabs">
					<a href="<?php echo add_query_arg(['view' => 'Campaigns']); ?>" class="campaign-tab <?php if ($activeTab == 'Campaigns') { echo 'active'; } ?>">
						Campaigns Table
					</a>
					<a href="<?php echo add_query_arg(['view' => 'Timeline']); ?>" class="campaign-tab <?php if ($activeTab == 'Timeline') { echo 'active'; } ?>">
						Timeline
					</a>
				</div>
			</div>
			<div class="wizHeader-right">
				<div class="wizHeader-actions">
					<button class="wiz-button green sync-journey" data-journeyids="<?php echo htmlspecialchars(json_encode($journeyCampaignIds)); ?>">
						<i class="fa-solid fa-arrows-rotate"></i>&nbsp;&nbsp;Sync Journey
					</button>
					<a href="<?php echo get_bloginfo('url') . '/sync-station'; ?>" class="wiz-button gray" id="viewSyncStation" title="View sync log">
						<i class="fa-regular fa-rectangle-list"></i>
					</a>
					<?php include plugin_dir_path(__FILE__) . 'parts/module-user-settings-form.php'; ?>
				</div>
			</div>
		</div>
	</header>

	<article id="journey-<?php echo $journeyId; ?>" data-journey="<?php echo $journeyId; ?>" class="single-journey-article full-width">
		<div class="entry-content" itemprop="mainContentOfPage">
			<?php if (!empty($journey['description'])): ?>
				<div class="wizcampaign-section inset journey-description-section">
					<h3>Journey Description</h3>
					<p><?php echo esc_html(is_array($journey['description']) ? implode(', ', $journey['description']) : $journey['description']); ?></p>
					
					<div class="journey-details">
						<?php if (isset($journey['createdAt'])): ?>
							<span class="journey-created">Created: <?php echo date('M j, Y', (int)($journey['createdAt'] / 1000)); ?></span>
						<?php endif; ?>
						<?php if (isset($journey['lifetimeLimit']) && $journey['lifetimeLimit'] > 0): ?>
							• <span class="journey-limit">Limit: <?php echo number_format($journey['lifetimeLimit']); ?> per user</span>
						<?php endif; ?>
						<?php if (isset($journey['triggerEventNames'])): 
							$triggers = is_string($journey['triggerEventNames']) ? unserialize($journey['triggerEventNames']) : $journey['triggerEventNames'];
							if (is_array($triggers) && !empty($triggers)): ?>
							• <span class="journey-triggers">Triggers: <?php echo implode(', ', $triggers); ?></span>
						<?php endif; endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if (isset($_GET['view']) && $_GET['view'] == 'Campaigns' || !isset($_GET['view'])) { ?>
				<?php include plugin_dir_path(__FILE__) . 'parts/dashboard-date-pickers.php'; ?>

				<div id="journey-rollup-wrapper" data-campaign-ids='<?php echo json_encode($journeyCampaignIds); ?>' data-start-date="<?php echo $startDate; ?>" data-end-date="<?php echo $endDate; ?>">
					<div class="rollup_summary_wrapper" id="journey-timeline-rollup-summary">
						<div class="rollup_summary_loader"><i class="fa-solid fa-spinner fa-spin"></i>&nbsp;&nbsp;Loading
							rollup summary...</div>
					</div>
				</div>

				<div class="journey-campaigns-controls">
					<label class="journey-campaigns-toggle show-inactive-toggle">
						<input type="checkbox" id="show-inactive-campaigns" />
						<span>Show inactive campaigns</span>
					</label>
					<label class="journey-campaigns-toggle">
						<input type="checkbox" id="exclude-no-sends-campaigns" />
						<span>Exclude campaigns with no sends</span>
					</label>
				</div>

				<div class="journey-campaigns-wrapper">
					<?php
					if (!empty($campaigns)) {
						echo display_workflow_campaigns_table($journeyId, $campaigns, $startDate, $endDate, true);
					} else {
						echo '<div class="wizcampaign-section inset"><p>No campaigns found for this journey.</p></div>';
					}
					?>
				</div>
			<?php } ?>
			
			<?php if ($activeTab == 'Timeline') { ?>
				<?php
				// Everything below is a shell. The months, metrics and campaign rows
				// are aggregated in a handful of grouped queries by
				// get_idwiz_journey_timeline() and fetched over AJAX, so the page
				// paints immediately and switching metric or fiscal year is a
				// client-side re-render rather than a full reload.
				//
				// Whether the aggregation is already cached is settled here rather
				// than in the browser: the request that would tell us is the one
				// that takes a minute, and the point is to say so before it starts.
				$timelineCacheState = idwiz_journey_timeline_cache_state($journeyId);
				$timelineRetained = idwiz_timeline_journey_is_retained($journeyId, get_current_user_id());
				?>
				<div class="journey-timeline" id="journey-timeline" data-journey-id="<?php echo (int) $journeyId; ?>"
					data-cache-state="<?php echo esc_attr($timelineCacheState); ?>">
					<div class="jt-controls">
						<div class="jt-control">
							<label class="jt-control-label" for="jt-metric">Metric</label>
							<select id="jt-metric" class="jt-select"></select>
						</div>
						<div class="jt-control jt-control-years">
							<span class="jt-control-label">Fiscal years</span>
							<div class="jt-chips" id="jt-year-chips"></div>
						</div>
						<div class="jt-control jt-control-actions">
							<label class="jt-retain" for="jt-retain"
								title="Re-read this journey's most recent month on a schedule, so the tab is already warm when you open it">
								<input type="checkbox" id="jt-retain" <?php checked($timelineRetained); ?> />
								<span>Retain this data for faster page loads</span>
							</label>
							<button type="button" class="wiz-button gray" id="jt-export" title="Download the table below as CSV">
								<i class="fa-solid fa-file-csv"></i>&nbsp;&nbsp;CSV
							</button>
							<button type="button" class="wiz-button gray" id="jt-refresh" title="Re-run the aggregation instead of using the cached copy">
								<i class="fa-solid fa-arrows-rotate"></i>&nbsp;&nbsp;Refresh
							</button>
						</div>
					</div>

					<div class="jt-status" id="jt-status">
						<i class="fa-solid fa-spinner fa-spin"></i>&nbsp;&nbsp;Loading journey performance&hellip;
					</div>

					<div class="jt-body" id="jt-body" hidden>
						<div class="jt-tiles" id="jt-tiles"></div>

						<section class="jt-panel">
							<header class="jt-panel-head">
								<h2 id="jt-chart-title">Performance over time</h2>
								<p class="jt-panel-sub" id="jt-chart-sub"></p>
							</header>
							<div class="jt-chart-wrap">
								<canvas id="jt-chart"></canvas>
							</div>
							<div class="jt-legend" id="jt-legend"></div>
						</section>

						<section class="jt-panel">
							<header class="jt-panel-head">
								<h2>Month by month</h2>
								<p class="jt-panel-sub" id="jt-table-sub"></p>
							</header>
							<div class="jt-table-scroll">
								<table class="jt-table" id="jt-table"></table>
							</div>
						</section>

						<details class="jt-panel jt-campaigns" id="jt-campaigns">
							<summary class="jt-panel-head">
								<h2>By campaign</h2>
								<p class="jt-panel-sub" id="jt-campaign-sub"></p>
							</summary>
							<div class="jt-table-scroll">
								<table class="jt-table" id="jt-campaign-table"></table>
							</div>
						</details>

						<p class="jt-footnote" id="jt-footnote"></p>
					</div>
				</div>
			<?php } ?>
		</div>
	</article>

<?php
} else { // if valid journey id
	echo '<div class="wizcampaign-section inset"><p>Invalid journey ID or journey not found! <a href="' . get_bloginfo('url') . '/metrics/journeys">View all journeys</a></p></div>';
}

// Enqueue journeys styles and scripts, versioned by mtime so an edit to either
// file busts its own cache. A hand-written version here is easy to forget, and
// journeys.js depends on the export helpers in id-general.js.
$journeysStyle = plugin_dir_path(__FILE__) . '../styles/journeys-archive.css';
$journeysScript = plugin_dir_path(__FILE__) . '../js/journeys.js';
$timelineStyle = plugin_dir_path(__FILE__) . '../styles/journey-timeline.css';

wp_enqueue_style('journeys-archive', plugin_dir_url(__FILE__) . '../styles/journeys-archive.css', [], file_exists($journeysStyle) ? filemtime($journeysStyle) : '1.0.0');
wp_enqueue_style('journey-timeline', plugin_dir_url(__FILE__) . '../styles/journey-timeline.css', ['journeys-archive'], file_exists($timelineStyle) ? filemtime($timelineStyle) : '1.0.0');
wp_enqueue_script('journeys', plugin_dir_url(__FILE__) . '../js/journeys.js', ['jquery', 'id-general'], file_exists($journeysScript) ? filemtime($journeysScript) : '1.0.0', true);

get_footer(); ?>