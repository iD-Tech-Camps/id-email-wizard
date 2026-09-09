<?php

/**
 * Month-by-month journey performance, with year-over-year comparison.
 *
 * The Timeline tab used to build its table inline in the page template, calling
 * get_triggered_campaign_metrics() once per (year, month, campaign) cell. Each of
 * those calls reads seven engagement tables plus purchases and GA revenue, so a
 * journey with twenty campaigns over twelve months issued a couple of thousand
 * queries before the first byte of the page - which is why the tab took minutes
 * to load and could not be filtered without a full page reload.
 *
 * Everything here aggregates the journey's whole history in a fixed number of
 * grouped queries (one for sends, one per send-linked event table, one for
 * purchases, one for GA revenue) and hands the browser a single payload keyed by
 * month. The count of queries does not grow with the number of months, years, or
 * campaigns on screen, and the front end re-slices the payload by metric and
 * fiscal year without going back to the server.
 */

if (!defined('ABSPATH')) {
	exit;
}

// The fiscal year runs November through October, so FY2025 is Nov 2024 through
// Oct 2025. That matches the old timeline table and wizPulse_get_current_fiscal_year().
if (!defined('IDWIZ_TIMELINE_FISCAL_START_MONTH')) {
	define('IDWIZ_TIMELINE_FISCAL_START_MONTH', 11);
}

// Engagement data only exists from the first sync forward; nothing before this
// is worth charting.
if (!defined('IDWIZ_TIMELINE_EARLIEST_MONTH')) {
	define('IDWIZ_TIMELINE_EARLIEST_MONTH', '2021-11');
}

// A journey with a long history still only needs a bounded window. Six fiscal
// years is more comparison than anyone reads at once.
if (!defined('IDWIZ_TIMELINE_MAX_MONTHS')) {
	define('IDWIZ_TIMELINE_MAX_MONTHS', 72);
}

if (!defined('IDWIZ_TIMELINE_CACHE_TTL')) {
	define('IDWIZ_TIMELINE_CACHE_TTL', 15 * MINUTE_IN_SECONDS);
}

/**
 * The fiscal year a calendar month belongs to.
 *
 * @param int $year  Calendar year.
 * @param int $month Calendar month, 1-12.
 * @return int
 */
function idwiz_timeline_fiscal_year($year, $month)
{
	return $month >= IDWIZ_TIMELINE_FISCAL_START_MONTH ? (int) $year + 1 : (int) $year;
}

/**
 * A calendar month's position within its fiscal year: November is 0, October is 11.
 *
 * @param int $month Calendar month, 1-12.
 * @return int
 */
function idwiz_timeline_fiscal_index($month)
{
	return ((int) $month + 1) % 12;
}

/**
 * The month labels of a fiscal year, in fiscal order.
 *
 * @return array Twelve short month names starting at November.
 */
function idwiz_timeline_fiscal_month_labels()
{
	$labels = [];
	for ($index = 0; $index < 12; $index++) {
		$month = (($index + 10) % 12) + 1; // 0 => 11 (Nov), 2 => 1 (Jan), 11 => 10 (Oct)
		$labels[] = date('M', mktime(0, 0, 0, $month, 1, 2000));
	}

	return $labels;
}

/**
 * Every month between two Y-m keys, inclusive, with its epoch boundaries.
 *
 * Boundaries are computed in PHP rather than derived in SQL because the events
 * are stored as epoch milliseconds and MySQL's session timezone is not the
 * timezone the rest of this plugin does date math in. Bucketing on these
 * boundaries keeps "September" meaning the same thing here as it does in the
 * campaigns table.
 *
 * @param string $startMonth Y-m, inclusive.
 * @param string $endMonth   Y-m, inclusive.
 * @return array List of month buckets.
 */
function idwiz_timeline_month_range($startMonth, $endMonth)
{
	$months = [];

	$cursor = DateTime::createFromFormat('Y-m-d H:i:s', $startMonth . '-01 00:00:00');
	$last = DateTime::createFromFormat('Y-m-d H:i:s', $endMonth . '-01 00:00:00');

	if (!$cursor || !$last || $cursor > $last) {
		return $months;
	}

	while ($cursor <= $last) {
		$next = (clone $cursor)->modify('first day of next month');

		$year = (int) $cursor->format('Y');
		$month = (int) $cursor->format('n');

		$months[] = [
			'key' => $cursor->format('Y-m'),
			'label' => $cursor->format('M Y'),
			'monthLabel' => $cursor->format('M'),
			'year' => $year,
			'month' => $month,
			'fiscalYear' => idwiz_timeline_fiscal_year($year, $month),
			'fiscalIndex' => idwiz_timeline_fiscal_index($month),
			'startDate' => $cursor->format('Y-m-d'),
			'endDate' => $cursor->format('Y-m-t'),
			'startMs' => $cursor->getTimestamp() * 1000,
			'endMs' => $next->getTimestamp() * 1000,
		];

		$cursor = $next;
	}

	return $months;
}

/**
 * A CASE expression that maps an epoch-milliseconds column to its month key.
 *
 * The rows it runs over are already filtered to the range, and the boundaries
 * ascend, so the first "< end" that matches is the row's month.
 *
 * @param string $column  Column (or alias.column) holding epoch milliseconds.
 * @param array  $months  Month buckets from idwiz_timeline_month_range().
 * @param array  $params  Collects the placeholder values, in SQL order.
 * @return string SQL fragment carrying %d/%s placeholders.
 */
function idwiz_timeline_month_case($column, $months, &$params)
{
	$sql = 'CASE';

	foreach ($months as $month) {
		$sql .= " WHEN {$column} < %d THEN %s";
		$params[] = $month['endMs'];
		$params[] = $month['key'];
	}

	return $sql . ' END';
}

/**
 * Engagement counts per campaign per month.
 *
 * Sends are counted by when they were sent. Opens, clicks and the rest are
 * counted against the send that caused them - the same messageId join
 * get_triggered_events_for_sends_in_window() uses - so a month's open rate is
 * "of what we sent that month, this much was opened", however long after the
 * send the open arrived. Counting events by their own timestamps instead lets a
 * month report opens against sends it never made.
 *
 * @param array $campaignIds Campaign ids, connected campaigns included.
 * @param array $months      Month buckets.
 * @return array campaignId => monthKey => [metric => count]
 */
function idwiz_journey_monthly_engagement($campaignIds, $months)
{
	global $wpdb;

	$counts = [];

	if (empty($campaignIds) || empty($months)) {
		return $counts;
	}

	$sendsTable = $wpdb->prefix . 'idemailwiz_triggered_sends';
	$idPlaceholders = implode(', ', array_fill(0, count($campaignIds), '%d'));
	$rangeStart = $months[0]['startMs'];
	$rangeEnd = $months[count($months) - 1]['endMs'];

	$caseParams = [];
	$bucket = idwiz_timeline_month_case('startAt', $months, $caseParams);

	$sendRows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT campaignId, {$bucket} AS ym, COUNT(DISTINCT messageId) AS total
			 FROM {$sendsTable}
			 WHERE campaignId IN ({$idPlaceholders}) AND startAt >= %d AND startAt < %d
			 GROUP BY campaignId, ym",
			array_merge($caseParams, $campaignIds, [$rangeStart, $rangeEnd])
		),
		ARRAY_A
	);

	foreach ($sendRows ?: [] as $row) {
		if (empty($row['ym'])) {
			continue;
		}
		$counts[(int) $row['campaignId']][$row['ym']]['sends'] = (int) $row['total'];
	}

	$sendLinkedDatabases = [
		'opens' => 'idemailwiz_triggered_opens',
		'clicks' => 'idemailwiz_triggered_clicks',
		'unsubscribes' => 'idemailwiz_triggered_unsubscribes',
		'complaints' => 'idemailwiz_triggered_complaints',
		'bounces' => 'idemailwiz_triggered_bounces',
	];

	foreach ($sendLinkedDatabases as $metricKey => $database) {
		$eventsTable = $wpdb->prefix . $database;

		$caseParams = [];
		$bucket = idwiz_timeline_month_case('sent.startAt', $months, $caseParams);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT sent.campaignId AS campaignId, {$bucket} AS ym, COUNT(DISTINCT events.messageId) AS total
				 FROM (
					SELECT messageId, campaignId, MIN(startAt) AS startAt
					FROM {$sendsTable}
					WHERE campaignId IN ({$idPlaceholders}) AND startAt >= %d AND startAt < %d
					GROUP BY messageId, campaignId
				 ) AS sent
				 INNER JOIN {$eventsTable} AS events ON events.messageId = sent.messageId
				 WHERE events.campaignId IN ({$idPlaceholders})
				 GROUP BY sent.campaignId, ym",
				array_merge($caseParams, $campaignIds, [$rangeStart, $rangeEnd], $campaignIds)
			),
			ARRAY_A
		);

		foreach ($rows ?: [] as $row) {
			if (empty($row['ym'])) {
				continue;
			}
			$counts[(int) $row['campaignId']][$row['ym']][$metricKey] = (int) $row['total'];
		}
	}

	if ($wpdb->last_error) {
		error_log('idwiz_journey_monthly_engagement SQL error: ' . $wpdb->last_error);
	}

	return $counts;
}

/**
 * Purchases and attributed revenue per campaign per month.
 *
 * Read through get_idwiz_purchases() rather than a hand-rolled query so the
 * user's attribution mode and attribution window still apply.
 *
 * @param array $campaignIds Campaign ids, connected campaigns included.
 * @param array $months      Month buckets.
 * @return array campaignId => monthKey => ['purchases' => int, 'revenue' => float]
 */
function idwiz_journey_monthly_purchases($campaignIds, $months)
{
	$totals = [];

	if (empty($campaignIds) || empty($months)) {
		return $totals;
	}

	$monthKeys = array_flip(array_column($months, 'key'));

	$purchases = get_idwiz_purchases([
		'campaignIds' => $campaignIds,
		'startAt_start' => $months[0]['startDate'],
		'startAt_end' => $months[count($months) - 1]['endDate'],
		'fields' => ['campaignId', 'purchaseDate', 'total'],
	]);

	if (!is_array($purchases)) {
		return $totals;
	}

	foreach ($purchases as $purchase) {
		$monthKey = substr((string) ($purchase['purchaseDate'] ?? ''), 0, 7);
		if (!isset($monthKeys[$monthKey])) {
			continue;
		}

		$campaignId = (int) $purchase['campaignId'];

		if (!isset($totals[$campaignId][$monthKey])) {
			$totals[$campaignId][$monthKey] = ['purchases' => 0, 'revenue' => 0.0];
		}

		$totals[$campaignId][$monthKey]['purchases']++;
		$totals[$campaignId][$monthKey]['revenue'] += (float) $purchase['total'];
	}

	return $totals;
}

/**
 * GA-attributed revenue per campaign per month.
 *
 * @param array $campaignIds Campaign ids, connected campaigns included.
 * @param array $months      Month buckets.
 * @return array campaignId => monthKey => float
 */
function idwiz_journey_monthly_ga_revenue($campaignIds, $months)
{
	$totals = [];

	if (empty($campaignIds) || empty($months)) {
		return $totals;
	}

	$monthKeys = array_flip(array_column($months, 'key'));

	$gaRows = get_idwiz_ga_data([
		'campaignIds' => $campaignIds,
		'startDate' => $months[0]['startDate'],
		'endDate' => $months[count($months) - 1]['endDate'],
	]);

	if (!is_array($gaRows)) {
		return $totals;
	}

	foreach ($gaRows as $row) {
		$monthKey = substr((string) ($row['date'] ?? ''), 0, 7);
		if (!isset($monthKeys[$monthKey])) {
			continue;
		}

		$campaignId = (int) $row['campaignId'];
		if (!isset($totals[$campaignId][$monthKey])) {
			$totals[$campaignId][$monthKey] = 0.0;
		}
		$totals[$campaignId][$monthKey] += (float) $row['revenue'];
	}

	return $totals;
}

/**
 * The month of a journey's first send, or null if it has never sent.
 *
 * @param array $campaignIds Campaign ids, connected campaigns included.
 * @return string|null Y-m
 */
function idwiz_journey_first_send_month($campaignIds)
{
	global $wpdb;

	if (empty($campaignIds)) {
		return null;
	}

	$sendsTable = $wpdb->prefix . 'idemailwiz_triggered_sends';
	$placeholders = implode(', ', array_fill(0, count($campaignIds), '%d'));

	$firstSend = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT MIN(startAt) FROM {$sendsTable} WHERE campaignId IN ({$placeholders})",
			$campaignIds
		)
	);

	if (!$firstSend) {
		return null;
	}

	return date('Y-m', (int) ($firstSend / 1000));
}

/**
 * The journey's campaigns, plus the connected-campaign ids that roll up into them.
 *
 * A campaign's connected campaigns carry the same message under a different id,
 * so their engagement belongs to the parent row rather than a row of its own.
 *
 * @param array $campaigns Campaign rows from get_idwiz_campaigns().
 * @return array ['allIds' => int[], 'parentOf' => [childId => parentId]]
 */
function idwiz_journey_timeline_campaign_ids($campaigns)
{
	$parentOf = [];
	$allIds = [];

	foreach ($campaigns as $campaign) {
		$campaignId = (int) $campaign['id'];
		$allIds[] = $campaignId;
		$parentOf[$campaignId] = $campaignId;
	}

	foreach ($campaigns as $campaign) {
		$campaignId = (int) $campaign['id'];
		$connected = maybe_unserialize($campaign['connectedCampaigns'] ?? '');

		if (!is_array($connected)) {
			continue;
		}

		foreach ($connected as $connectedId) {
			$connectedId = (int) $connectedId;
			if (!$connectedId || isset($parentOf[$connectedId])) {
				continue;
			}

			$allIds[] = $connectedId;
			$parentOf[$connectedId] = $campaignId;
		}
	}

	return [
		'allIds' => array_values(array_unique($allIds)),
		'parentOf' => $parentOf,
	];
}

/**
 * An empty bucket of counts.
 *
 * Months with no sends are left out of the payload entirely rather than
 * zero-filled: a month the journey did not send in has no open rate, and
 * charting it as 0% would invent a dip that never happened.
 *
 * @return array
 */
function idwiz_timeline_empty_counts()
{
	return [
		'sends' => 0,
		'opens' => 0,
		'clicks' => 0,
		'unsubscribes' => 0,
		'complaints' => 0,
		'bounces' => 0,
		'purchases' => 0,
		'revenue' => 0.0,
		'gaRevenue' => 0.0,
	];
}

/**
 * The whole timeline payload for a journey: every month it has sent in, per
 * campaign and rolled up, ready for the browser to slice by metric and year.
 *
 * @param int  $journeyId
 * @param bool $forceRefresh Skip the cached copy and re-aggregate.
 * @return array|false
 */
function get_idwiz_journey_timeline($journeyId, $forceRefresh = false)
{
	$journeyId = (int) $journeyId;

	if (!$journeyId) {
		return false;
	}

	// The rest of the plugin does its date math in this timezone (see
	// build_idwiz_query), and month boundaries have to agree with it.
	date_default_timezone_set('America/Los_Angeles');

	$journey = get_idwiz_journey($journeyId);
	if (!$journey) {
		return false;
	}

	$campaigns = get_idwiz_campaigns([
		'workflowId' => $journeyId,
		'fields' => ['id', 'name', 'campaignState', 'type', 'connectedCampaigns', 'startAt'],
	]);

	if (!is_array($campaigns) || empty($campaigns)) {
		return [
			'journeyId' => $journeyId,
			'journeyName' => is_array($journey['name']) ? implode(', ', $journey['name']) : $journey['name'],
			'months' => [],
			'fiscalYears' => [],
			'fiscalMonthLabels' => idwiz_timeline_fiscal_month_labels(),
			'totals' => [],
			'campaigns' => [],
			'generatedAt' => current_time('mysql'),
		];
	}

	$ids = idwiz_journey_timeline_campaign_ids($campaigns);

	// Purchase attribution is a per-user setting, so two users can legitimately
	// see different revenue for the same journey. Keying the cache on the
	// settings keeps one user's view from being served to the other.
	$currentUserId = get_current_user_id();
	$cacheKey = 'idwiz_jt_' . md5(wp_json_encode([
		'v' => 2,
		'journey' => $journeyId,
		'campaigns' => $ids['allIds'],
		'attributionMode' => get_user_meta($currentUserId, 'purchase_attribution_mode', true),
		'attributionLength' => get_user_meta($currentUserId, 'purchase_attribution_length', true),
		'month' => date('Y-m'),
	]));

	if (!$forceRefresh) {
		$cached = get_transient($cacheKey);
		if (is_array($cached)) {
			$cached['cached'] = true;
			return $cached;
		}
	}

	$firstSendMonth = idwiz_journey_first_send_month($ids['allIds']);
	$startMonth = $firstSendMonth ?: date('Y-m');
	if ($startMonth < IDWIZ_TIMELINE_EARLIEST_MONTH) {
		$startMonth = IDWIZ_TIMELINE_EARLIEST_MONTH;
	}

	$months = idwiz_timeline_month_range($startMonth, date('Y-m'));

	// Keep the window bounded on very old journeys by dropping the oldest months.
	if (count($months) > IDWIZ_TIMELINE_MAX_MONTHS) {
		$months = array_slice($months, -IDWIZ_TIMELINE_MAX_MONTHS);
	}

	$engagement = idwiz_journey_monthly_engagement($ids['allIds'], $months);
	$purchases = idwiz_journey_monthly_purchases($ids['allIds'], $months);
	$gaRevenue = idwiz_journey_monthly_ga_revenue($ids['allIds'], $months);

	// Fold every source, including connected campaigns, onto the parent campaign.
	$byCampaign = [];

	$collect = function ($campaignId, $monthKey, $values) use (&$byCampaign, $ids) {
		$parentId = $ids['parentOf'][(int) $campaignId] ?? null;
		if (!$parentId) {
			return;
		}

		if (!isset($byCampaign[$parentId][$monthKey])) {
			$byCampaign[$parentId][$monthKey] = idwiz_timeline_empty_counts();
		}

		foreach ($values as $metricKey => $value) {
			$byCampaign[$parentId][$monthKey][$metricKey] += $value;
		}
	};

	foreach ($engagement as $campaignId => $byMonth) {
		foreach ($byMonth as $monthKey => $values) {
			$collect($campaignId, $monthKey, $values);
		}
	}

	foreach ($purchases as $campaignId => $byMonth) {
		foreach ($byMonth as $monthKey => $values) {
			$collect($campaignId, $monthKey, $values);
		}
	}

	foreach ($gaRevenue as $campaignId => $byMonth) {
		foreach ($byMonth as $monthKey => $value) {
			$collect($campaignId, $monthKey, ['gaRevenue' => $value]);
		}
	}

	// Delivered is sends minus bounces. A send skip means no message went out at
	// all, so skips are not part of the send population and are not subtracted -
	// see the same note in get_triggered_campaign_metrics_chunk().
	$totals = [];
	$activeMonthKeys = [];

	foreach ($byCampaign as $campaignId => $byMonth) {
		foreach ($byMonth as $monthKey => $counts) {
			$counts['delivered'] = max(0, $counts['sends'] - $counts['bounces']);
			$byCampaign[$campaignId][$monthKey] = $counts;

			if (!isset($totals[$monthKey])) {
				$totals[$monthKey] = idwiz_timeline_empty_counts();
				$totals[$monthKey]['delivered'] = 0;
			}

			foreach ($counts as $metricKey => $value) {
				$totals[$monthKey][$metricKey] += $value;
			}

			$activeMonthKeys[$monthKey] = true;
		}
	}

	// Only ship the months the journey actually has data in. Everything else is a
	// gap in the chart, not a zero.
	$months = array_values(array_filter($months, function ($month) use ($activeMonthKeys) {
		return isset($activeMonthKeys[$month['key']]);
	}));

	$fiscalYears = array_values(array_unique(array_column($months, 'fiscalYear')));
	sort($fiscalYears);

	$campaignPayload = [];
	foreach ($campaigns as $campaign) {
		$campaignId = (int) $campaign['id'];
		$monthly = $byCampaign[$campaignId] ?? [];

		if (empty($monthly)) {
			continue;
		}

		$campaignType = $campaign['type'] ?? '';
		$campaignState = $campaign['campaignState'] ?? 'Unknown';

		// A triggered campaign that reads "Finished" has been deactivated; a blast
		// that reads "Finished" simply completed. Same wording, opposite meaning.
		$isActive = ($campaignType === 'Triggered' || $campaignType === 'FromWorkflow')
			? $campaignState === 'Running'
			: in_array($campaignState, ['Running', 'Finished'], true);

		$campaignPayload[] = [
			'id' => $campaignId,
			'name' => is_array($campaign['name']) ? implode(', ', $campaign['name']) : $campaign['name'],
			'state' => $campaignState,
			'isActive' => $isActive,
			'monthly' => $monthly,
		];
	}

	// Busiest first: on a journey with dozens of campaigns, the ones that carry
	// the volume are the ones worth reading.
	usort($campaignPayload, function ($a, $b) {
		$aSends = array_sum(array_column($a['monthly'], 'sends'));
		$bSends = array_sum(array_column($b['monthly'], 'sends'));

		if ($aSends === $bSends) {
			return strcasecmp($a['name'], $b['name']);
		}

		return $bSends <=> $aSends;
	});

	$payload = [
		'journeyId' => $journeyId,
		'journeyName' => is_array($journey['name']) ? implode(', ', $journey['name']) : $journey['name'],
		'months' => $months,
		'fiscalYears' => $fiscalYears,
		'currentFiscalYear' => idwiz_timeline_fiscal_year((int) date('Y'), (int) date('n')),
		'fiscalMonthLabels' => idwiz_timeline_fiscal_month_labels(),
		'totals' => $totals,
		'campaigns' => $campaignPayload,
		'generatedAt' => current_time('mysql'),
		'cached' => false,
	];

	set_transient($cacheKey, $payload, IDWIZ_TIMELINE_CACHE_TTL);

	return $payload;
}

/**
 * Serve the timeline payload to the Timeline tab.
 */
add_action('wp_ajax_idwiz_journey_timeline', function () {
	check_ajax_referer('journey-timeline', 'security');

	if (!is_user_logged_in()) {
		wp_send_json_error('Not authorized');
	}

	$journeyId = isset($_POST['journeyId']) ? (int) $_POST['journeyId'] : 0;
	if (!$journeyId) {
		wp_send_json_error('Missing journey ID');
	}

	$timeline = get_idwiz_journey_timeline($journeyId, !empty($_POST['refresh']));

	if (!$timeline) {
		wp_send_json_error('Journey not found');
	}

	wp_send_json_success($timeline);
});
