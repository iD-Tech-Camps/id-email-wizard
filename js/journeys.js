jQuery(document).ready(function ($) {

    var journeyCampaignsTable = null;
    var showInactiveCampaigns = false;
    var excludeNoSendsCampaigns = false;

    // On page load
    if ($(".single-journey-article").length) {
        updateDynamicJourneyRollup();
        initJourneyCampaignsTable();
    }

    function updateDynamicJourneyRollup() {
        var $rollupWrapper = $("#journey-rollup-wrapper");
        if ($rollupWrapper.length === 0) {
            return;   
        }
        var campaignIds = $rollupWrapper.attr('data-campaign-ids');
        var campaignIdsArray = JSON.parse(campaignIds); // Parse JSON string to array
        var startDate = $rollupWrapper.attr('data-start-date');
        var endDate = $rollupWrapper.attr('data-end-date');

        // Fetch rollup summary
        console.log('Fetching rollup data for campaigns:', campaignIdsArray);
        fetchRollUpSummaryData(campaignIdsArray, startDate, endDate, "#journey-timeline-rollup-summary");
    }

    // Initialize DataTables for the journey campaigns table
    function initJourneyCampaignsTable() {
        var $table = $('.journey-campaigns-sortable');
        if ($table.length === 0) {
            return;
        }

        // Find the Sent column by its heading rather than assuming a position,
        // since the table grows a GA Rev column when no date range is applied.
        var sentColumnIndex = -1;
        $table.find('thead th').each(function (index) {
            if ($(this).text().trim().toLowerCase() === 'sent') {
                sentColumnIndex = index;
            }
        });

        // The Sent cell carries the unformatted count in data-order; its text is
        // comma-grouped. Anything unreadable counts as a send so that a parsing
        // problem cannot silently hide rows.
        function getRowSendCount(row) {
            if (sentColumnIndex < 0 || !row.cells[sentColumnIndex]) {
                return 1;
            }

            var cell = row.cells[sentColumnIndex];
            var order = cell.getAttribute('data-order');
            var value = parseFloat(order !== null ? order : cell.textContent.replace(/[^0-9.-]/g, ''));

            return isNaN(value) ? 1 : value;
        }

        // Add the toggle filters BEFORE initializing DataTable. Running them as
        // search filters (rather than hiding rows in the DOM) is what lets the
        // exports honor the toggles - they export with search "applied".
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            // Only apply to our journey campaigns table
            if (settings.nTable.id.indexOf('journey-campaigns-table') === -1) {
                return true;
            }

            var row = settings.aoData[dataIndex].nTr;

            if (!showInactiveCampaigns && $(row).hasClass('inactive-campaign')) {
                return false;
            }

            if (excludeNoSendsCampaigns && getRowSendCount(row) <= 0) {
                return false;
            }

            return true;
        });

        var tableOptions = {
            paging: false,
            searching: true, // Enable searching for the filter to work
            info: false,
            order: [[2, 'desc']], // Sort by Last Sent column descending by default
            columnDefs: [
                { targets: 0, orderable: true }, // Campaign name
                { targets: 1, orderable: true }, // Status
                { targets: 2, orderable: true }, // Last Sent
                { targets: '_all', orderable: true }
            ],
            autoWidth: false,
            responsive: false,
            dom: 'lrtip' // Hide the search box but keep search functionality
        };

        // "Export current view": the same copy/CSV/Excel collection the campaigns
        // table uses (js/id-general.js). Because it exports with the search
        // modifier applied, the file matches what is on screen - the inactive
        // campaign filter and the date range included.
        if (typeof window.idwizExportCollection === 'function') {
            tableOptions.dom = 'B' + tableOptions.dom;
            tableOptions.buttons = [window.idwizExportCollection()];
        }

        journeyCampaignsTable = $table.DataTable(tableOptions);
    }

    // Handle the campaign filter checkboxes. Redrawing re-runs the search
    // filters above, which the export buttons then read through.
    $('#show-inactive-campaigns').on('change', function() {
        showInactiveCampaigns = $(this).is(':checked');
        if (journeyCampaignsTable) {
            journeyCampaignsTable.draw();
        }
    });

    $('#exclude-no-sends-campaigns').on('change', function() {
        excludeNoSendsCampaigns = $(this).is(':checked');
        if (journeyCampaignsTable) {
            journeyCampaignsTable.draw();
        }
    });

    // Handle sync journey button
    $('.sync-journey').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const originalText = $button.html();
        const journeyIds = $button.data('journeyids');
        
        $button.prop('disabled', true).text('Syncing...');

        // Honor the date range currently being viewed so we don't sync the journey's
        // entire purchase history (which can exhaust server memory). Fall back to the
        // rollup wrapper's defaults if the date pickers aren't present on this view.
        const $rollupWrapper = $('#journey-rollup-wrapper');
        const startDate = $('#wizStartDate').val() || $rollupWrapper.attr('data-start-date') || '';
        const endDate = $('#wizEndDate').val() || $rollupWrapper.attr('data-end-date') || '';

        const campaignIds = journeyIds ? JSON.stringify(journeyIds) : JSON.stringify([]);

        // Campaigns, templates, metrics, purchases, experiments, journeys. This
        // does not touch the triggered tables the Sent/Delivered/Opens columns
        // are read from.
        const metricsSync = $.post(idAjax.ajaxurl, {
            action: 'idemailwiz_ajax_sync',
            security: idAjax.wizAjaxNonce,
            campaignIds: campaignIds,
            startDate: startDate,
            endDate: endDate
        });

        // Queues the Iterable engagement exports that do fill those columns.
        const engagementSync = $.post(idAjax.ajaxurl, {
            action: 'handle_journey_triggered_sync',
            security: idAjax.wizAjaxNonce,
            campaignIds: campaignIds,
            startDate: startDate,
            endDate: endDate
        });

        $.when(metricsSync, engagementSync)
            .done(function(metricsResult, engagementResult) {
                const metricsResponse = metricsResult[0];
                const engagementResponse = engagementResult[0];

                if (!metricsResponse.success) {
                    alert('Sync failed: ' + (metricsResponse.data || 'Unknown error'));
                    $button.prop('disabled', false).html(originalText);
                    return;
                }

                // The engagement queue only drains while Engagement Data Sync is
                // on, so pass that back rather than reporting a finished sync.
                if (engagementResponse.success && engagementResponse.data && !engagementResponse.data.engagementSyncEnabled) {
                    alert(engagementResponse.data.message);
                } else if (!engagementResponse.success) {
                    alert('Engagement sync failed: ' + (engagementResponse.data || 'Unknown error'));
                }

                $button.text('Synced!').removeClass('green').addClass('blue');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            })
            .fail(function(xhr, status, error) {
                alert('Sync failed: Network error');
                $button.prop('disabled', false).html(originalText);
            });
    });

    // The Timeline tab's controls live in js/journey-timeline.js. They used to
    // reload the whole page on every metric, year or month click, which meant
    // re-running the aggregation server-side each time; the tab now holds the
    // journey's full history client-side and re-renders in place.

    // Handle collapsible sections
    $('.journey-description-section h3').on('click', function() {
        $(this).parent().find('.journey-details').slideToggle();
    });

});