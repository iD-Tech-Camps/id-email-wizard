/**
 * Journey Timeline: how a journey performs month by month, and how this fiscal
 * year compares to the last.
 *
 * The server hands over the journey's whole history in one payload (see
 * includes/journey-timeline.php) as raw counts per month. Everything on screen -
 * rates, totals, deltas, the chart, both tables - is derived from those counts
 * here, so changing the metric or the fiscal-year selection is an instant
 * re-render rather than another round trip.
 */
jQuery(document).ready(function ($) {

	var root = document.getElementById('journey-timeline');
	if (!root) {
		return;
	}

	// The fiscal year runs Nov -> Oct, so index 0 is November.
	var FISCAL_MONTH_COUNT = 12;

	// Categorical palette, assigned to a fiscal year by its position in the
	// journey's full history so that toggling years never repaints the ones that
	// stay on screen. Validated for colorblind separation on a light surface;
	// three of these sit below 3:1 contrast, which is why every series is also
	// named in the legend, labeled at the end of its line, and repeated in the
	// table below the chart.
	var SERIES_COLORS = [
		'#2a78d6', '#eb6834', '#1baf7a', '#eda100',
		'#e87ba4', '#008300', '#4a3aa7', '#e34948'
	];

	var INK = {
		primary: '#1d2327',
		secondary: '#50575e',
		muted: '#8c8f94',
		grid: '#e6e8eb',
		good: '#1e7b46',
		bad: '#b32d2e'
	};

	/**
	 * Every metric the tab can show, derived from the raw monthly counts.
	 *
	 * `value` returns null when the denominator is missing rather than 0, so a
	 * month the journey did not send in leaves a gap in the line instead of
	 * drawing a plunge to zero.
	 */
	var METRICS = [
		{
			key: 'openRate', label: 'Open Rate', group: 'Rates', unit: 'rate', higherIsBetter: true,
			value: function (c) { return c.sends > 0 ? (c.opens / c.sends) * 100 : null; }
		},
		{
			key: 'ctr', label: 'CTR', group: 'Rates', unit: 'rate', higherIsBetter: true,
			value: function (c) { return c.sends > 0 ? (c.clicks / c.sends) * 100 : null; }
		},
		{
			key: 'cto', label: 'CTO', group: 'Rates', unit: 'rate', higherIsBetter: true,
			value: function (c) { return c.opens > 0 ? (c.clicks / c.opens) * 100 : null; }
		},
		{
			key: 'cvr', label: 'CVR', group: 'Rates', unit: 'rate', higherIsBetter: true,
			value: function (c) { return c.delivered > 0 ? (c.purchases / c.delivered) * 100 : null; }
		},
		{
			key: 'deliveryRate', label: 'Delivery Rate', group: 'Rates', unit: 'rate', higherIsBetter: true,
			value: function (c) { return c.sends > 0 ? (c.delivered / c.sends) * 100 : null; }
		},
		{
			key: 'unsubRate', label: 'Unsub Rate', group: 'Rates', unit: 'rate', higherIsBetter: false,
			value: function (c) { return c.sends > 0 ? (c.unsubscribes / c.sends) * 100 : null; }
		},
		{
			key: 'revenue', label: 'Revenue', group: 'Money', unit: 'currency', higherIsBetter: true,
			value: function (c) { return c.sends > 0 || c.revenue > 0 ? c.revenue : null; }
		},
		{
			key: 'aov', label: 'Avg Order Value', group: 'Money', unit: 'currency', higherIsBetter: true,
			value: function (c) { return c.purchases > 0 ? c.revenue / c.purchases : null; }
		},
		{
			key: 'revPerSend', label: 'Revenue / 1k Sends', group: 'Money', unit: 'currency', higherIsBetter: true,
			value: function (c) { return c.sends > 0 ? (c.revenue / c.sends) * 1000 : null; }
		},
		{
			key: 'sends', label: 'Sends', group: 'Volume', unit: 'count', higherIsBetter: true,
			value: function (c) { return c.sends > 0 ? c.sends : null; }
		},
		{
			key: 'delivered', label: 'Delivered', group: 'Volume', unit: 'count', higherIsBetter: true,
			value: function (c) { return c.sends > 0 ? c.delivered : null; }
		},
		{
			key: 'opens', label: 'Opens', group: 'Volume', unit: 'count', higherIsBetter: true,
			value: function (c) { return c.sends > 0 ? c.opens : null; }
		},
		{
			key: 'clicks', label: 'Clicks', group: 'Volume', unit: 'count', higherIsBetter: true,
			value: function (c) { return c.sends > 0 ? c.clicks : null; }
		},
		{
			key: 'purchases', label: 'Purchases', group: 'Volume', unit: 'count', higherIsBetter: true,
			value: function (c) { return c.sends > 0 ? c.purchases : null; }
		},
		{
			key: 'unsubscribes', label: 'Unsubscribes', group: 'Volume', unit: 'count', higherIsBetter: false,
			value: function (c) { return c.sends > 0 ? c.unsubscribes : null; }
		}
	];

	// The tiles across the top always answer the same five questions, whatever
	// metric the chart is showing.
	var TILE_METRICS = ['sends', 'openRate', 'ctr', 'cvr', 'revenue'];

	var COUNT_KEYS = [
		'sends', 'delivered', 'opens', 'clicks',
		'unsubscribes', 'complaints', 'bounces', 'purchases', 'revenue', 'gaRevenue'
	];

	var state = {
		data: null,
		metricKey: 'openRate',
		selectedYears: [],
		chart: null,
		tableModel: null
	};

	var els = {
		status: document.getElementById('jt-status'),
		body: document.getElementById('jt-body'),
		metric: document.getElementById('jt-metric'),
		yearChips: document.getElementById('jt-year-chips'),
		tiles: document.getElementById('jt-tiles'),
		chartTitle: document.getElementById('jt-chart-title'),
		chartSub: document.getElementById('jt-chart-sub'),
		chartCanvas: document.getElementById('jt-chart'),
		legend: document.getElementById('jt-legend'),
		table: document.getElementById('jt-table'),
		tableSub: document.getElementById('jt-table-sub'),
		campaigns: document.getElementById('jt-campaigns'),
		campaignSub: document.getElementById('jt-campaign-sub'),
		campaignTable: document.getElementById('jt-campaign-table'),
		footnote: document.getElementById('jt-footnote'),
		refresh: document.getElementById('jt-refresh'),
		exportBtn: document.getElementById('jt-export')
	};

	/* -------------------------------------------------------------- helpers */

	function el(tag, className, text) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (text !== undefined && text !== null) {
			node.textContent = text;
		}
		return node;
	}

	function emptyCounts() {
		var counts = {};
		COUNT_KEYS.forEach(function (key) {
			counts[key] = 0;
		});
		return counts;
	}

	function addCounts(target, source) {
		if (!source) {
			return target;
		}
		COUNT_KEYS.forEach(function (key) {
			target[key] += (source[key] || 0);
		});
		return target;
	}

	function sumCounts(list) {
		return (list || []).reduce(function (total, counts) {
			return addCounts(total, counts);
		}, emptyCounts());
	}

	function metricByKey(key) {
		for (var i = 0; i < METRICS.length; i++) {
			if (METRICS[i].key === key) {
				return METRICS[i];
			}
		}
		return METRICS[0];
	}

	function currentMetric() {
		return metricByKey(state.metricKey);
	}

	function formatNumber(value, decimals) {
		return Number(value).toLocaleString('en-US', {
			minimumFractionDigits: decimals || 0,
			maximumFractionDigits: decimals === undefined ? 0 : decimals
		});
	}

	function formatValue(value, metric) {
		if (value === null || value === undefined || isNaN(value)) {
			return '—';
		}

		if (metric.unit === 'rate') {
			// Small rates (CVR, unsubs) lose all their signal at one decimal.
			return formatNumber(value, Math.abs(value) < 10 ? 2 : 1) + '%';
		}

		if (metric.unit === 'currency') {
			return '$' + formatNumber(value, Math.abs(value) < 100 && value !== 0 ? 2 : 0);
		}

		return formatNumber(value, 0);
	}

	/**
	 * The change from one period to the next.
	 *
	 * Rates move in percentage points - an open rate going 40% -> 42% is +2pp, not
	 * +5% - while volume and money move in percent.
	 */
	function delta(current, previous, metric) {
		if (current === null || previous === null || current === undefined || previous === undefined) {
			return null;
		}

		if (metric.unit === 'rate') {
			var points = current - previous;
			return {
				text: (points >= 0 ? '+' : '') + formatNumber(points, Math.abs(points) < 10 ? 2 : 1) + 'pp',
				direction: Math.abs(points) < 0.005 ? 'flat' : (points > 0 ? 'up' : 'down')
			};
		}

		if (!previous) {
			return null;
		}

		var pct = ((current - previous) / Math.abs(previous)) * 100;
		return {
			text: (pct >= 0 ? '+' : '') + formatNumber(pct, Math.abs(pct) < 10 ? 1 : 0) + '%',
			direction: Math.abs(pct) < 0.05 ? 'flat' : (pct > 0 ? 'up' : 'down')
		};
	}

	/**
	 * Whether a move is a good one. Every metric here rises when things go well
	 * except the unsubscribe ones, where the arrow has to mean the opposite.
	 */
	function deltaTone(deltaValue, metric) {
		if (!deltaValue || deltaValue.direction === 'flat') {
			return 'flat';
		}
		return (deltaValue.direction === 'up') === (metric.higherIsBetter !== false) ? 'good' : 'bad';
	}

	function deltaClass(deltaValue, metric) {
		return 'jt-delta ' + deltaTone(deltaValue, metric);
	}

	// Table cells carry the tone as color only; .jt-delta is an inline-flex badge
	// and would collapse the table column it sits in.
	function deltaCellClass(deltaValue, metric) {
		return 'jt-tone-' + deltaTone(deltaValue, metric);
	}

	/**
	 * Axis ticks want fewer decimals than a cell does - the gridline is already
	 * telling you the magnitude.
	 */
	function formatAxis(value, metric) {
		if (metric.unit === 'rate') {
			return formatNumber(value, Math.abs(value) < 1 ? 1 : 0) + '%';
		}

		if (metric.unit === 'currency') {
			if (Math.abs(value) >= 1000000) {
				return '$' + formatNumber(value / 1000000, 1) + 'M';
			}
			if (Math.abs(value) >= 1000) {
				return '$' + formatNumber(value / 1000, 0) + 'k';
			}
			return '$' + formatNumber(value, 0);
		}

		if (Math.abs(value) >= 1000000) {
			return formatNumber(value / 1000000, 1) + 'M';
		}
		if (Math.abs(value) >= 10000) {
			return formatNumber(value / 1000, 0) + 'k';
		}

		return formatNumber(value, 0);
	}

	function colorForYear(fiscalYear) {
		var index = state.data.fiscalYears.indexOf(fiscalYear);
		return SERIES_COLORS[(index < 0 ? 0 : index) % SERIES_COLORS.length];
	}

	function fyLabel(fiscalYear) {
		return 'FY' + fiscalYear;
	}

	/* ------------------------------------------------------------ data model */

	/**
	 * Counts for one fiscal year, indexed by fiscal month (0 = November).
	 * Months the journey did not send in are left undefined.
	 */
	function yearSeries(fiscalYear, monthlyByKey) {
		var series = new Array(FISCAL_MONTH_COUNT);

		state.data.months.forEach(function (month) {
			if (month.fiscalYear !== fiscalYear) {
				return;
			}
			var counts = monthlyByKey[month.key];
			if (counts) {
				series[month.fiscalIndex] = counts;
			}
		});

		return series;
	}

	function journeySeries(fiscalYear) {
		return yearSeries(fiscalYear, state.data.totals);
	}

	/**
	 * The fiscal months where both years have data, so a year-to-date comparison
	 * is like-for-like instead of a full year against a partial one.
	 */
	function sharedMonths(seriesA, seriesB) {
		var shared = [];
		for (var i = 0; i < FISCAL_MONTH_COUNT; i++) {
			if (seriesA[i] && seriesB[i]) {
				shared.push(i);
			}
		}
		return shared;
	}

	function monthSpanLabel(indexes) {
		if (!indexes.length) {
			return '';
		}
		var labels = state.data.fiscalMonthLabels;
		var first = labels[indexes[0]];
		var last = labels[indexes[indexes.length - 1]];
		return first === last ? first : first + '–' + last;
	}

	/**
	 * The two fiscal years to open on: the most recent two the journey actually
	 * sent in.
	 *
	 * A purchase can be attributed to a campaign long after its last send, and
	 * that lands a revenue-only month - and with it a whole fiscal year - on the
	 * timeline with no sends behind it. Opening on such a year leaves every rate
	 * and volume metric null, so the tiles, chart and table all read as empty on
	 * a journey with years of data. Years without sends stay selectable; they
	 * are just never the default.
	 */
	function defaultYears(payload) {
		var seen = {};
		var years = [];

		payload.months.forEach(function (month) {
			var counts = payload.totals[month.key];
			if (!counts || !counts.sends || seen[month.fiscalYear]) {
				return;
			}
			seen[month.fiscalYear] = true;
			years.push(month.fiscalYear);
		});

		if (!years.length) {
			years = payload.fiscalYears.slice();
		}

		years.sort(function (a, b) { return a - b; });

		return years.slice(-2);
	}

	function primaryYear() {
		if (!state.selectedYears.length) {
			return null;
		}
		return state.selectedYears[state.selectedYears.length - 1];
	}

	/* -------------------------------------------------------------- controls */

	function buildMetricSelect() {
		var groups = {};
		var order = [];

		METRICS.forEach(function (metric) {
			if (!groups[metric.group]) {
				groups[metric.group] = [];
				order.push(metric.group);
			}
			groups[metric.group].push(metric);
		});

		els.metric.innerHTML = '';
		order.forEach(function (groupName) {
			var optgroup = document.createElement('optgroup');
			optgroup.label = groupName;
			groups[groupName].forEach(function (metric) {
				var option = el('option', null, metric.label);
				option.value = metric.key;
				optgroup.appendChild(option);
			});
			els.metric.appendChild(optgroup);
		});

		els.metric.value = state.metricKey;
	}

	function renderYearChips() {
		els.yearChips.innerHTML = '';

		state.data.fiscalYears.slice().reverse().forEach(function (fiscalYear) {
			var isOn = state.selectedYears.indexOf(fiscalYear) !== -1;
			var chip = el('button', 'jt-chip' + (isOn ? ' on' : ''));
			chip.type = 'button';
			chip.setAttribute('aria-pressed', isOn ? 'true' : 'false');

			var swatch = el('span', 'jt-chip-swatch');
			swatch.style.backgroundColor = colorForYear(fiscalYear);
			chip.appendChild(swatch);
			chip.appendChild(el('span', null, fyLabel(fiscalYear)));

			chip.addEventListener('click', function () {
				toggleYear(fiscalYear);
			});

			els.yearChips.appendChild(chip);
		});
	}

	function toggleYear(fiscalYear) {
		var index = state.selectedYears.indexOf(fiscalYear);

		if (index === -1) {
			state.selectedYears.push(fiscalYear);
		} else if (state.selectedYears.length > 1) {
			state.selectedYears.splice(index, 1);
		} else {
			// Never leave the view with nothing to draw.
			return;
		}

		state.selectedYears.sort(function (a, b) { return a - b; });
		render();
	}

	/* --------------------------------------------------------------- render */

	function render() {
		renderYearChips();
		renderTiles();
		renderChart();
		renderTable();
		renderCampaigns();
	}

	function renderTiles() {
		els.tiles.innerHTML = '';

		var year = primaryYear();
		if (year === null) {
			return;
		}

		var current = journeySeries(year);
		var previous = state.data.fiscalYears.indexOf(year - 1) !== -1 ? journeySeries(year - 1) : null;

		var basis = [];
		for (var i = 0; i < FISCAL_MONTH_COUNT; i++) {
			if (current[i]) {
				basis.push(i);
			}
		}

		var comparable = previous ? sharedMonths(current, previous) : [];

		var heading = el('div', 'jt-tiles-head');
		heading.appendChild(el('span', 'jt-tiles-title', fyLabel(year)));
		heading.appendChild(el('span', 'jt-tiles-sub', monthSpanLabel(basis)));
		if (comparable.length) {
			heading.appendChild(el('span', 'jt-tiles-sub', 'compared with ' + fyLabel(year - 1) + ' over ' + monthSpanLabel(comparable)));
		}
		els.tiles.appendChild(heading);

		var grid = el('div', 'jt-tile-grid');

		TILE_METRICS.forEach(function (metricKey) {
			var metric = metricByKey(metricKey);

			var currentTotal = sumCounts(basis.map(function (i) { return current[i]; }));
			var tile = el('div', 'jt-tile');
			tile.appendChild(el('span', 'jt-tile-label', metric.label));
			tile.appendChild(el('span', 'jt-tile-value', formatValue(metric.value(currentTotal), metric)));

			if (comparable.length) {
				var currentComparable = sumCounts(comparable.map(function (i) { return current[i]; }));
				var previousComparable = sumCounts(comparable.map(function (i) { return previous[i]; }));
				var change = delta(metric.value(currentComparable), metric.value(previousComparable), metric);

				if (change) {
					var deltaNode = el('span', deltaClass(change, metric));
					deltaNode.appendChild(el('span', 'jt-delta-arrow', change.direction === 'up' ? '▲' : (change.direction === 'down' ? '▼' : '■')));
					deltaNode.appendChild(el('span', null, change.text + ' vs ' + fyLabel(year - 1)));
					tile.appendChild(deltaNode);
				} else {
					tile.appendChild(el('span', 'jt-delta flat', 'no prior-year basis'));
				}
			}

			grid.appendChild(tile);
		});

		els.tiles.appendChild(grid);
	}

	function renderChart() {
		var metric = currentMetric();
		var labels = state.data.fiscalMonthLabels;

		els.chartTitle.textContent = metric.label + ' by month';
		els.chartSub.textContent = state.selectedYears.length > 1
			? 'Fiscal months, November through October. Each line is one fiscal year, so months sit directly above their counterpart.'
			: 'Fiscal months, November through October.';

		var datasets = state.selectedYears.map(function (fiscalYear, index) {
			var series = journeySeries(fiscalYear);
			var isPrimary = index === state.selectedYears.length - 1;
			var color = colorForYear(fiscalYear);

			return {
				label: fyLabel(fiscalYear),
				fiscalYear: fiscalYear,
				counts: series,
				data: series.map(function (counts) {
					return counts ? metric.value(counts) : null;
				}),
				borderColor: color,
				backgroundColor: color,
				borderWidth: isPrimary ? 3 : 2,
				pointRadius: 4,
				pointHoverRadius: 7,
				pointBackgroundColor: color,
				pointBorderColor: '#ffffff',
				pointBorderWidth: 2,
				borderDash: isPrimary ? [] : [6, 4],
				// Straight segments: a month is a measurement, and a spline draws
				// peaks and troughs between months that were never measured.
				tension: 0,
				spanGaps: false
			};
		});

		if (state.chart) {
			state.chart.destroy();
		}

		state.chart = new Chart(els.chartCanvas.getContext('2d'), {
			type: 'line',
			data: { labels: labels, datasets: datasets },
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				// Room on the right for the end-of-line year labels.
				layout: { padding: { right: 64, top: 8 } },
				plugins: {
					legend: { display: false },
					title: { display: false },
					datalabels: {
						// One label at the end of each line: identity without
						// relying on color alone, and no number soup on every point.
						display: function (context) {
							var data = context.dataset.data;
							for (var i = data.length - 1; i >= 0; i--) {
								if (data[i] !== null && data[i] !== undefined) {
									return i === context.dataIndex;
								}
							}
							return false;
						},
						align: 'right',
						offset: 6,
						color: function (context) { return context.dataset.borderColor; },
						font: { weight: '600', size: 11 },
						formatter: function (value, context) { return context.dataset.label; }
					},
					tooltip: {
						backgroundColor: '#ffffff',
						titleColor: INK.primary,
						bodyColor: INK.secondary,
						// Defaults to white, which is invisible on this tooltip.
						footerColor: INK.primary,
						borderColor: INK.grid,
						borderWidth: 1,
						padding: 12,
						displayColors: true,
						callbacks: {
							title: function (items) {
								return items.length ? labels[items[0].dataIndex] : '';
							},
							label: function (context) {
								return ' ' + context.dataset.label + ': ' + formatValue(context.parsed.y, metric);
							},
							afterLabel: function (context) {
								var counts = context.dataset.counts[context.dataIndex];
								if (!counts) {
									return '';
								}
								return '   ' + formatNumber(counts.sends) + ' sends · '
									+ formatNumber(counts.opens) + ' opens · '
									+ formatNumber(counts.clicks) + ' clicks';
							},
							footer: function (items) {
								if (items.length < 2) {
									return '';
								}
								var newest = items[items.length - 1];
								var previous = items[items.length - 2];
								var change = delta(newest.parsed.y, previous.parsed.y, metric);
								if (!change) {
									return '';
								}
								return newest.dataset.label + ' vs ' + previous.dataset.label + ': ' + change.text;
							}
						}
					}
				},
				scales: {
					x: {
						grid: { display: false },
						ticks: { color: INK.secondary }
					},
					y: {
						// Counts and money get a zero baseline. Rates do not: open
						// rates that live between 40% and 45% flatten into one line
						// against a zero axis, which hides the very difference this
						// tab exists to show.
						beginAtZero: metric.unit !== 'rate',
						grace: metric.unit === 'rate' ? '15%' : 0,
						grid: { color: INK.grid, drawBorder: false },
						ticks: {
							color: INK.secondary,
							callback: function (value) {
								return formatAxis(value, metric);
							}
						}
					}
				}
			}
		});

		renderLegend();
	}

	function renderLegend() {
		var metric = currentMetric();
		els.legend.innerHTML = '';

		state.selectedYears.slice().reverse().forEach(function (fiscalYear) {
			var series = journeySeries(fiscalYear);
			var months = [];
			for (var i = 0; i < FISCAL_MONTH_COUNT; i++) {
				if (series[i]) {
					months.push(i);
				}
			}

			var item = el('div', 'jt-legend-item');
			var swatch = el('span', 'jt-legend-swatch');
			swatch.style.backgroundColor = colorForYear(fiscalYear);
			item.appendChild(swatch);
			item.appendChild(el('span', 'jt-legend-label', fyLabel(fiscalYear)));
			item.appendChild(el('span', 'jt-legend-value', formatValue(metric.value(sumCounts(months.map(function (i) { return series[i]; }))), metric)));
			item.appendChild(el('span', 'jt-legend-months', monthSpanLabel(months)));

			els.legend.appendChild(item);
		});
	}

	/**
	 * Months down the side, one column per selected fiscal year, and a change
	 * column between each consecutive pair.
	 */
	function renderTable() {
		var metric = currentMetric();
		var years = state.selectedYears;

		var header = ['Month'];
		years.forEach(function (fiscalYear, index) {
			header.push(fyLabel(fiscalYear));
			if (index > 0) {
				header.push('vs ' + fyLabel(years[index - 1]));
			}
		});

		var seriesByYear = {};
		years.forEach(function (fiscalYear) {
			seriesByYear[fiscalYear] = journeySeries(fiscalYear);
		});

		var rows = [];
		for (var monthIndex = 0; monthIndex < FISCAL_MONTH_COUNT; monthIndex++) {
			var hasData = years.some(function (fiscalYear) {
				return !!seriesByYear[fiscalYear][monthIndex];
			});

			if (!hasData) {
				continue;
			}

			rows.push(buildRow(state.data.fiscalMonthLabels[monthIndex], years, function (fiscalYear) {
				return seriesByYear[fiscalYear][monthIndex];
			}, metric));
		}

		// Totals compare each year only over the months it shares with the year
		// before it, so a part-finished year is never scored against a full one.
		var totalRow = buildTotalRow(years, seriesByYear, metric);

		els.tableSub.textContent = metric.label + ' by fiscal month. Blank cells are months the journey did not send in.';
		paintTable(els.table, header, rows, totalRow);

		state.tableModel = { header: header, rows: rows, total: totalRow };
	}

	function buildRow(label, years, countsFor, metric) {
		var cells = [];

		years.forEach(function (fiscalYear, index) {
			var counts = countsFor(fiscalYear);
			var value = counts ? metric.value(counts) : null;

			cells.push({
				text: formatValue(value, metric),
				title: counts
					? formatNumber(counts.sends) + ' sends, ' + formatNumber(counts.opens) + ' opens, '
						+ formatNumber(counts.clicks) + ' clicks, ' + formatNumber(counts.purchases) + ' purchases'
					: 'No sends',
				className: counts ? 'jt-num' : 'jt-num jt-empty'
			});

			if (index > 0) {
				var previousCounts = countsFor(years[index - 1]);
				var change = delta(value, previousCounts ? metric.value(previousCounts) : null, metric);
				cells.push({
					text: change ? change.text : '—',
					className: 'jt-num ' + (change ? deltaCellClass(change, metric) : 'jt-empty')
				});
			}
		});

		return { label: label, cells: cells };
	}

	function buildTotalRow(years, seriesByYear, metric) {
		var cells = [];

		years.forEach(function (fiscalYear, index) {
			var series = seriesByYear[fiscalYear];
			var own = [];
			for (var i = 0; i < FISCAL_MONTH_COUNT; i++) {
				if (series[i]) {
					own.push(series[i]);
				}
			}

			cells.push({
				text: own.length ? formatValue(metric.value(sumCounts(own)), metric) : '—',
				className: 'jt-num'
			});

			if (index > 0) {
				var previousSeries = seriesByYear[years[index - 1]];
				var shared = sharedMonths(series, previousSeries);
				var change = shared.length
					? delta(
						metric.value(sumCounts(shared.map(function (i) { return series[i]; }))),
						metric.value(sumCounts(shared.map(function (i) { return previousSeries[i]; }))),
						metric
					)
					: null;

				cells.push({
					text: change ? change.text : '—',
					title: shared.length ? 'Compared over ' + monthSpanLabel(shared) : 'No shared months',
					className: 'jt-num ' + (change ? deltaCellClass(change, metric) : 'jt-empty')
				});
			}
		});

		return {
			label: 'Year total',
			title: 'Each year totals only the months it has data for. The change column compares the months both years share.',
			cells: cells
		};
	}

	function paintTable(table, header, rows, totalRow) {
		table.innerHTML = '';

		var thead = el('thead');
		var headerRow = el('tr');
		header.forEach(function (text, index) {
			var th = el('th', index === 0 ? 'jt-sticky' : 'jt-num', text);
			headerRow.appendChild(th);
		});
		thead.appendChild(headerRow);
		table.appendChild(thead);

		var tbody = el('tbody');
		rows.forEach(function (row) {
			tbody.appendChild(paintRow(row));
		});
		table.appendChild(tbody);

		if (totalRow) {
			var tfoot = el('tfoot');
			tfoot.appendChild(paintRow(totalRow));
			table.appendChild(tfoot);
		}
	}

	function paintRow(row) {
		var tr = el('tr');
		var th = el('th', 'jt-sticky', row.label);
		if (row.title) {
			th.title = row.title;
		}
		tr.appendChild(th);

		row.cells.forEach(function (cell) {
			var td = el('td', cell.className, cell.text);
			if (cell.title) {
				td.title = cell.title;
			}
			tr.appendChild(td);
		});

		return tr;
	}

	/**
	 * The same metric per campaign for the most recent selected year, so a
	 * journey-level dip can be traced to the message that caused it.
	 */
	function renderCampaigns() {
		var metric = currentMetric();
		var year = primaryYear();

		if (year === null || !state.data.campaigns.length) {
			els.campaigns.hidden = true;
			return;
		}

		els.campaigns.hidden = false;
		els.campaignSub.textContent = metric.label + ' per campaign, ' + fyLabel(year);

		var header = ['Campaign'].concat(state.data.fiscalMonthLabels).concat(['Year']);
		var rows = [];

		state.data.campaigns.forEach(function (campaign) {
			var series = yearSeries(year, campaign.monthly);
			var monthsWithData = [];
			var cells = [];

			for (var i = 0; i < FISCAL_MONTH_COUNT; i++) {
				var counts = series[i];
				if (counts) {
					monthsWithData.push(counts);
				}

				cells.push({
					text: counts ? formatValue(metric.value(counts), metric) : '',
					title: counts ? formatNumber(counts.sends) + ' sends' : '',
					className: counts ? 'jt-num' : 'jt-num jt-empty'
				});
			}

			if (!monthsWithData.length) {
				return;
			}

			cells.push({
				text: formatValue(metric.value(sumCounts(monthsWithData)), metric),
				className: 'jt-num jt-year-total'
			});

			rows.push({
				label: campaign.name + (campaign.isActive ? '' : ' (inactive)'),
				title: campaign.state,
				cells: cells
			});
		});

		paintTable(els.campaignTable, header, rows, null);
	}

	/* --------------------------------------------------------------- export */

	function exportCsv() {
		if (!state.tableModel) {
			return;
		}

		var metric = currentMetric();
		var lines = [];

		lines.push(csvLine(state.tableModel.header));
		state.tableModel.rows.forEach(function (row) {
			lines.push(csvLine([row.label].concat(row.cells.map(function (cell) { return cell.text; }))));
		});
		lines.push(csvLine([state.tableModel.total.label].concat(state.tableModel.total.cells.map(function (cell) { return cell.text; }))));

		var name = (state.data.journeyName || 'journey').replace(/[^a-z0-9]+/gi, '-').toLowerCase();
		var blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
		var link = document.createElement('a');

		link.href = URL.createObjectURL(blob);
		link.download = name + '-' + metric.key + '-timeline.csv';
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
		URL.revokeObjectURL(link.href);
	}

	function csvLine(values) {
		return values.map(function (value) {
			return '"' + String(value === null || value === undefined ? '' : value).replace(/"/g, '""') + '"';
		}).join(',');
	}

	/* ----------------------------------------------------------------- load */

	function showStatus(html) {
		els.status.innerHTML = html;
		els.status.hidden = false;
		els.body.hidden = true;
	}

	function load(forceRefresh) {
		showStatus('<i class="fa-solid fa-spinner fa-spin"></i>&nbsp;&nbsp;'
			+ (forceRefresh ? 'Re-aggregating journey performance&hellip;' : 'Loading journey performance&hellip;'));

		$.ajax({
			url: idAjax_journey_timeline.ajaxurl,
			type: 'POST',
			timeout: 180000,
			data: {
				action: 'idwiz_journey_timeline',
				security: idAjax_journey_timeline.nonce,
				journeyId: root.getAttribute('data-journey-id'),
				refresh: forceRefresh ? 1 : 0
			}
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				showStatus('<span class="jt-error">Could not load the timeline: '
					+ ((response && response.data) || 'unexpected response') + '</span>');
				return;
			}

			applyPayload(response.data);
		}).fail(function (xhr, status) {
			showStatus('<span class="jt-error">Could not load the timeline ('
				+ (status === 'timeout' ? 'the request timed out' : 'server error')
				+ '). <button type="button" class="wiz-button gray jt-retry">Try again</button></span>');
		}).always(function () {
			els.refresh.disabled = false;
		});
	}

	function applyPayload(payload) {
		state.data = payload;

		if (!payload.months.length) {
			showStatus('<p class="jt-empty-state">This journey has no sends on record yet, so there is nothing to chart. '
				+ 'Sync the journey to pull its engagement data.</p>');
			return;
		}

		// Open on the current fiscal year plus the one before it - the comparison
		// people come to this tab for - falling back to the two most recent years
		// with sends on a journey that stopped sending a while ago.
		if (!state.selectedYears.length) {
			state.selectedYears = defaultYears(payload);
		}

		els.status.hidden = true;
		els.body.hidden = false;

		els.footnote.textContent = 'Opens, clicks, unsubscribes and bounces are counted against the send that caused them, '
			+ 'so a month reports engagement on the mail it actually sent that month. '
			+ 'Revenue follows your purchase attribution settings. Aggregated ' + payload.generatedAt
			+ (payload.cached ? ' (cached)' : '') + '.';

		render();
	}

	/* ---------------------------------------------------------------- events */

	els.metric.addEventListener('change', function () {
		state.metricKey = els.metric.value;
		render();
	});

	els.refresh.addEventListener('click', function () {
		els.refresh.disabled = true;
		load(true);
	});

	els.exportBtn.addEventListener('click', exportCsv);

	$(document).on('click', '.jt-retry', function () {
		load(false);
	});

	buildMetricSelect();
	load(false);
});
