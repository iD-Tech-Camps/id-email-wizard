jQuery(document).ready(function ($) {
	// Constants
	const config = {
		API_KEY: idAjax_iterable_actions.iterable_api_key,
		TRANSACTIONAL_FROM_EMAIL: "info@idtechnotifications.com",
		PROMOTIONAL_FROM_EMAIL: "info@idtechonline.com",
	};
	//var existingTemplateId = $('#templateUI').data('iterableid');
	var existingTemplateId = $('#iterable_template_id').val();

	// ============================================================
	// Sync error viewer helpers
	// ============================================================

	// Track CodeMirror instances created inside the failure modal so we can
	// dispose them cleanly when the modal closes (preventing memory leaks).
	var __wizSyncCmInstances = [];

	// Parses Iterable's "line: N col: M found: 'X'" error message.
	// Returns { line, col, found } when parseable; null otherwise.
	function extractMergeError(rawMsg) {
		if (!rawMsg || typeof rawMsg !== 'string') return null;
		var lc = rawMsg.match(/line:\s*(\d+)\s*col:\s*(\d+)/i);
		if (!lc) return null;
		var foundMatch = rawMsg.match(/found:\s*'([^']*)'/i);
		return {
			line: parseInt(lc[1], 10),
			col:  parseInt(lc[2], 10),
			found: foundMatch ? foundMatch[1] : null,
		};
	}

	// Injects the CSS used by the sync error viewer once per page.
	function ensureSyncErrorViewerStyles() {
		if (document.getElementById('wiz-sync-error-viewer-styles')) return;
		var css = ''
			+ '.wiz-sync-error-popup { max-width: 95vw !important; }'
			+ '.wiz-sync-error-popup .swal2-html-container { text-align: left; max-height: 75vh; overflow: hidden; }'
			+ '.wiz-sync-error-summary { font-family: monospace; background: #2b2b2b; color: #f8f8f2; padding: 10px 12px; border-radius: 4px; white-space: pre-wrap; word-break: break-word; margin-bottom: 10px; font-size: 12px; }'
			+ '.wiz-sync-error-summary .wiz-sync-error-loc { color: #ff7b72; font-weight: bold; }'
			+ '.wiz-sync-error-toolbar { display: flex; gap: 8px; margin-bottom: 8px; flex-wrap: wrap; }'
			+ '.wiz-sync-error-toolbar button { font-size: 12px; padding: 4px 10px; cursor: pointer; }'
			+ '.wiz-sync-error-cm-host { border: 1px solid #444; border-radius: 4px; }'
			+ '.wiz-sync-error-cm-host .CodeMirror { height: 55vh; font-size: 12px; }'
			+ '.wiz-cm-error-bg { background: rgba(255, 80, 80, 0.22) !important; }'
			+ '.wiz-cm-error-line-mark { background: rgba(255, 0, 0, 0.35); border-bottom: 2px solid #ff5252; }'
			+ '.wiz-cm-error-gutter { color: #ff5252; padding-left: 4px; font-weight: bold; }'
			+ '.wiz-sync-error-failure { border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px; }'
			+ '.wiz-sync-error-failure-header { padding: 8px 10px; background: #f7f7f7; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }'
			+ '.wiz-sync-error-failure-header:hover { background: #efefef; }'
			+ '.wiz-sync-error-failure-body { padding: 10px; display: none; }'
			+ '.wiz-sync-error-failure.expanded .wiz-sync-error-failure-body { display: block; }'
			+ '.wiz-sync-error-failure-msg { font-family: monospace; font-size: 12px; color: #b00; flex: 1; padding-right: 10px; word-break: break-word; }';
		var style = document.createElement('style');
		style.id = 'wiz-sync-error-viewer-styles';
		style.appendChild(document.createTextNode(css));
		document.head.appendChild(style);
	}

	// Mounts a read-only CodeMirror htmlmixed viewer into `container`, scrolls
	// to the offending line, and highlights the line + column.
	// Returns the CodeMirror instance, or null if CodeMirror is unavailable.
	function renderSyncErrorViewer(container, opts) {
		if (!container) return null;
		var html = (opts && opts.html) || '';
		var line = opts && opts.line ? parseInt(opts.line, 10) : null;
		var col  = opts && opts.col  ? parseInt(opts.col,  10) : null;

		if (typeof CodeMirror === 'undefined') {
			// Fallback: plain pre with line numbers we generate ourselves.
			var pre = document.createElement('pre');
			pre.style.maxHeight = '55vh';
			pre.style.overflow = 'auto';
			pre.style.fontSize = '12px';
			pre.style.background = '#2b2b2b';
			pre.style.color = '#f8f8f2';
			pre.style.padding = '10px';
			pre.textContent = html;
			container.appendChild(pre);
			return null;
		}

		var cm = CodeMirror(container, {
			value: html,
			mode: 'htmlmixed',
			lineNumbers: true,
			readOnly: true,
			lineWrapping: true,
			gutters: ['CodeMirror-linenumbers', 'wiz-sync-error-gutter'],
			theme: 'default',
		});

		__wizSyncCmInstances.push(cm);

		// Defer so the editor lays out before we measure / scroll.
		setTimeout(function() {
			try {
				cm.refresh();
				if (line && line > 0) {
					var lineIdx = line - 1;
					var lastLine = cm.lastLine();
					if (lineIdx > lastLine) lineIdx = lastLine;

					cm.addLineClass(lineIdx, 'background', 'wiz-cm-error-bg');

					var lineText = cm.getLine(lineIdx) || '';
					var startCh = 0;
					var endCh = lineText.length;
					if (col && col > 0) {
						startCh = Math.max(0, Math.min(col - 1, lineText.length));
						endCh = Math.min(lineText.length, startCh + 1);
						if (endCh <= startCh) endCh = startCh + 1;
					}
					cm.markText(
						{ line: lineIdx, ch: startCh },
						{ line: lineIdx, ch: endCh },
						{ className: 'wiz-cm-error-line-mark' }
					);

					var marker = document.createElement('div');
					marker.className = 'wiz-cm-error-gutter';
					marker.textContent = '!';
					cm.setGutterMarker(lineIdx, 'wiz-sync-error-gutter', marker);

					var targetCh = col && col > 0 ? Math.min(col - 1, lineText.length) : 0;
					cm.scrollIntoView({ line: lineIdx, ch: targetCh }, 200);
					cm.setCursor({ line: lineIdx, ch: targetCh });
				}
			} catch (e) {
				if (window.console) console.warn('Sync error viewer:', e);
			}
		}, 50);

		return cm;
	}

	// Disposes any CodeMirror instances created for the sync error modal.
	function disposeSyncErrorViewers() {
		__wizSyncCmInstances = [];
	}

	// Copies text to clipboard with a small toast confirmation.
	function copyToClipboard(text) {
		var done = function(ok) {
			if (typeof Swal === 'undefined' || !Swal.fire) return;
			Swal.fire({
				toast: true,
				position: 'top-end',
				icon: ok ? 'success' : 'error',
				title: ok ? 'HTML copied to clipboard' : 'Copy failed',
				showConfirmButton: false,
				timer: 1800,
			});
		};
		try {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function() { done(true); }, function() { done(false); });
				return;
			}
		} catch (e) {}
		// Fallback for older browsers.
		try {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.left = '-9999px';
			document.body.appendChild(ta);
			ta.select();
			var ok = document.execCommand('copy');
			document.body.removeChild(ta);
			done(ok);
		} catch (e) {
			done(false);
		}
	}

	// Escapes a string for safe HTML insertion.
	function escapeHtmlText(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	// Builds the HTML for the summary banner shown above the code viewer.
	function buildSyncErrorSummaryHtml(err) {
		var raw = escapeHtmlText(err && err.rawMsg ? err.rawMsg : (err && err.message ? err.message : 'Unknown error'));
		if (err && err.line) {
			var locText = 'line: ' + err.line + (err.col ? ' col: ' + err.col : '');
			// Highlight the location pattern within the raw text.
			raw = raw.replace(
				new RegExp('line:\\s*' + err.line + '\\s*col:\\s*' + (err.col || '\\d+'), 'i'),
				'<span class="wiz-sync-error-loc">' + escapeHtmlText(locText) + '</span>'
			);
		}
		return '<div class="wiz-sync-error-summary">' + raw + '</div>';
	}

	// Handle channel type change
	$(document).on('change', 'input[name=\"email_type\"]', function() {
		// Ignore changes during initialization
		if (window.wizBuilderInitializing) return;
		
		const channelType = $(this).val();
		$('.message-types').removeClass('active');
		$('.message-type-select').prop('disabled', true);
		

		if (channelType === 'promotional') {
			$('.message-types.promotional-types').addClass('active');
			$('.message-types.promotional-types .message-type-select').prop('disabled', false);
			// Set default message type if none selected
			if (!$('.message-types.promotional-types .message-type-select').val()) {
				$('.message-types.promotional-types .message-type-select').val('52634').trigger('change');
			}
		} else {
			$('.message-types.transactional-types').addClass('active');
			$('.message-types.transactional-types .message-type-select').prop('disabled', false);
			// Set default message type if none selected
			if (!$('.message-types.transactional-types .message-type-select').val()) {
				$('.message-types.transactional-types .message-type-select').val('52620').trigger('change');
			}
		}
	});

	// Handle message type selection
	$(document).on('change', '.message-type-select', function() {
		// Ignore changes during initialization
		if (window.wizBuilderInitializing) return;
		
		if (!$(this).prop('disabled')) {
			const messageTypeId = $(this).val();
			// Store the selected message type ID in a hidden input
			if (!$('#template_settings_message_type_id').length) {
				$('<input>').attr({
					type: 'hidden',
					id: 'template_settings_message_type_id',
					name: 'message_type_id',
					value: messageTypeId
				}).appendTo('#template-settings-form');
			} else {
				$('#template_settings_message_type_id').val(messageTypeId);
			}
			sessionStorage.setItem('unsavedChanges', 'true');
		}
	});

	// Main click event handler
	$("#sendToIterable").on("click", function () {
		const post_id = $(this).data("postid");
		
		// Check for unsaved changes and offer to save first
		if (sessionStorage.getItem('unsavedChanges') === 'true') {
			return Swal.fire({
				title: 'Unsaved Changes',
				html: 'You have unsaved changes. Would you like to save before syncing?',
				icon: "warning",
				showCancelButton: true,
				confirmButtonText: 'Save & Sync',
				cancelButtonText: 'Cancel',
				confirmButtonColor: '#94d401'
			}).then((result) => {
				if (result.isConfirmed) {
					// Save first, then trigger sync
					toggleOverlay(true, 'Saving...');
					save_template_data().then(() => {
						proceedWithSync(post_id);
					}).catch((error) => {
						toggleOverlay(false);
						Swal.fire({
							title: 'Save Failed',
							html: 'Could not save template: ' + error,
							icon: 'error'
						});
					});
				}
			});
		}
		
		proceedWithSync(post_id);
	});
	
	// Proceed with the sync process
	function proceedWithSync(post_id) {
		toggleOverlay(true);

		// Fetch template data from the server
		$.post(idAjax.ajaxurl, {
			action: "idemailwiz_get_template_data_for_iterable",
			security: idAjax_iterable_actions.nonce,
			post_id: post_id,
			template_id: existingTemplateId
		})
		.done(data => {
			if (data.status === "error") {
				handleAjaxError(true, data.message).then(() => {
					toggleOverlay(false);
				});
			} else {
				handleTemplateDataSuccess(data);
			}
		})
		.fail(() => handleAjaxError(true).then(() => {
			toggleOverlay(false);
		}));
	}

	// Error handling function
	function handleAjaxError(hideOverlay = true, message = "Whoops, something went wrong!") {
		return Swal.fire({
			html: message,
			icon: "error"
		}).then(() => {
			if (hideOverlay) {
				toggleOverlay(false);
			}
		});
	}

	// Success handling function for getting template data
	function handleTemplateDataSuccess(data) {
		var primaryTemplateId = data.primaryTemplateId || '';
		var syncHistory = data.syncHistory || [];
    
		// Function to format the display of a field
		function formatField(label, val, isOptional = false) {
			val = val || (isOptional ? "<em>Not set</em>" : "<em>No value set (REQUIRED)</em>");
			return `<li><strong>${label}:</strong> ${val}</li>`;
		}

		// Function to format UTM parameters
		function formatUTMs(utmParams) {
			if (!utmParams || !Array.isArray(utmParams) || utmParams.length === 0) {
				return "<li><strong>UTM Parameters:</strong> <em>None set</em></li>";
			}
			const utmList = utmParams.map(param => 
				`<li style="margin-left: 20px;">${param.key}: ${param.value || "<em>No value</em>"}</li>`
			).join('');
			return `<li><strong>UTM Parameters:</strong><ul>${utmList}</ul></li>`;
		}

		// Function to build sync target checkboxes
		function buildSyncTargets() {
			let html = '<div class="sync-targets-section">';
			html += '<label class="sync-targets-label">Sync to:</label>';

			// Manual template ID input (above the scrollable list)
			html += `
				<div class="sync-target-manual">
					<div class="sync-target-manual-input-wrap">
						<input type="text" id="manual_template_id" placeholder="Enter an existing template ID">
						<button type="button" id="add_manual_template" class="wiz-button small green">Add</button>
						<span class="sync-target-manual-hint">Leave blank to create a new template</span>
					</div>
				</div>
			`;

			html += '<div class="sync-targets-list">';
			
			// Check if primary ID exists but isn't in history (backwards compatibility)
			const historyIds = syncHistory.map(entry => String(entry.template_id));
			const hasPrimaryInHistory = primaryTemplateId && historyIds.includes(String(primaryTemplateId));
			
			// Add primary template ID first if it exists but isn't in history
			if (primaryTemplateId && !hasPrimaryInHistory) {
				html += `
					<label class="sync-target-item">
						<input type="checkbox" name="sync_target" value="${primaryTemplateId}" checked>
						<span class="sync-target-id">${primaryTemplateId} <span class="primary-badge">Primary</span></span>
						<a href="https://app.iterable.com/templates/editor?templateId=${primaryTemplateId}" 
							target="_blank" class="sync-target-link" title="View in Iterable">
							<i class="fa-solid fa-arrow-up-right-from-square"></i>
						</a>
					</label>
				`;
			}
			
			// Add existing history items as checkboxes
			if (syncHistory.length > 0) {
				syncHistory.forEach(entry => {
					const isPrimary = String(entry.template_id) === String(primaryTemplateId);
					const primaryLabel = isPrimary ? ' <span class="primary-badge">Primary</span>' : '';
					html += `
						<label class="sync-target-item">
							<input type="checkbox" name="sync_target" value="${entry.template_id}" checked>
							<span class="sync-target-id">${entry.template_id}${primaryLabel}</span>
							<a href="https://app.iterable.com/templates/editor?templateId=${entry.template_id}" 
								target="_blank" class="sync-target-link" title="View in Iterable">
								<i class="fa-solid fa-arrow-up-right-from-square"></i>
							</a>
						</label>
					`;
				});
			}
			
			html += '</div></div>';
			return html;
		}

		const fieldList = `
			<ul style="text-align: left;">
				${formatField("Subject Line", data.fields.emailSubject)}
				${formatField("Preheader", data.fields.preheader, true)}
				${formatField("Type", data.fields.messageType.charAt(0).toUpperCase() + data.fields.messageType.slice(1))}
				${formatField("From", `${data.fields.fromName} <${data.fields.fromEmail}>`)}
				${formatField("Reply To", data.fields.replyToEmail)}
				${formatField("GA Campaign", data.fields.googleAnalyticsCampaignName, true)}
				${formatUTMs(data.fields.linkParams)}
			</ul>
			${buildSyncTargets()}
		`;

		Swal.fire({
			title: "Confirm Sync Details",
			html: `${fieldList}`,
			icon: false,
			showCancelButton: true,
			confirmButtonText: "Confirm & Sync!",
			customClass: {
				htmlContainer: 'sync-to-iterable-container'
			},
			didOpen: () => {
				// Handle adding manual template ID
				$(document).on('click', '#add_manual_template', function() {
					const manualId = $('#manual_template_id').val().trim();
					if (manualId) {
						const exists = $(`.sync-targets-list input[value="${manualId}"]`).length > 0;
						if (!exists) {
							const newItem = `
								<label class="sync-target-item">
									<input type="checkbox" name="sync_target" value="${manualId}" checked>
									<span class="sync-target-id">${manualId}</span>
									<a href="https://app.iterable.com/templates/editor?templateId=${manualId}" 
										target="_blank" class="sync-target-link" title="View in Iterable">
										<i class="fa-solid fa-arrow-up-right-from-square"></i>
									</a>
								</label>
							`;
							$('.sync-targets-list').append(newItem);
						} else {
							$(`.sync-targets-list input[value="${manualId}"]`).prop('checked', true);
						}
						$('#manual_template_id').val('');
					}
				});
			},
			preConfirm: () => {
				const selectedIds = [];
				$('.sync-targets-list input[name="sync_target"]:checked').each(function() {
					selectedIds.push($(this).val());
				});

				const manualId = $('#manual_template_id').val().trim();
				if (manualId && !selectedIds.includes(manualId)) {
					selectedIds.push(manualId);
				}

				if (selectedIds.length === 0) {
					selectedIds.push('new');
				}
				
				return selectedIds;
			}
		}).then((result) => {
			if (result.isConfirmed && result.value) {
				// Process syncs sequentially with the collected IDs
				processSyncs(data.fields, result.value, data.alreadySent);
			} else {
				toggleOverlay(false);
			}
		});
	}
	
	// Process multiple syncs sequentially
	async function processSyncs(templateData, templateIds, alreadySent) {
		const successfulSyncs = [];
		const failedSyncs = [];
		const totalSyncs = templateIds.length;
		let currentSync = 0;
		
		// Show syncing overlay with initial message
		toggleOverlay(true, 'Syncing...');
		
		for (const templateId of templateIds) {
			currentSync++;
			const displayId = templateId === 'new' ? 'new template' : templateId;
			
			try {
				// Check for duplicates first (skip for 'new')
				if (templateId !== 'new') {
					updateOverlayMessage(`Syncing (${currentSync}/${totalSyncs}): Checking template ${displayId}...`);
					const dupCheck = await $.post(idAjax.ajaxurl, {
						action: "check_duplicate_itTemplateId",
						template_id: templateId,
						post_id: templateData.postId
					});
					
					if (dupCheck.status === "error") {
						failedSyncs.push({ id: templateId, error: dupCheck.message });
						continue;
					}
				}
				
				// Update message for syncing
				updateOverlayMessage(`Syncing (${currentSync}/${totalSyncs}): Pushing to ${displayId}...`);
				
				// For 'new', always use unique clientTemplateId to force Iterable to create a new template
				// (Iterable's upsert uses clientTemplateId to match existing templates)
				const isNewTemplate = templateId === 'new';
				const passedId = isNewTemplate ? null : templateId;
				
				const syncedId = await create_or_update_iterable_template(
					templateData, 
					passedId, 
					alreadySent,
					isNewTemplate // Always use unique clientTemplateId when creating new
				);
				successfulSyncs.push(syncedId);
			} catch (error) {
				failedSyncs.push({ id: templateId, error: error });
			}
		}
		
		// Update sync history with all successful syncs
		if (successfulSyncs.length > 0) {
			updateOverlayMessage('Syncing: Updating sync history...');
			const syncHistoryResponse = await updateSyncHistory(templateData.postId, successfulSyncs);
			handleMultiSyncSuccess(successfulSyncs, failedSyncs, syncHistoryResponse);
		} else {
			handleTemplateUpdateFailure(failedSyncs);
		}
	}
	
	// Update sync history via AJAX
	function updateSyncHistory(postId, syncedTemplateIds) {
		return $.post(idAjax.ajaxurl, {
			action: "update_iterable_sync_history",
			security: idAjax_iterable_actions.nonce,
			post_id: postId,
			synced_template_ids: syncedTemplateIds
		});
	}
	
	// Handle success for multiple syncs
	function handleMultiSyncSuccess(successfulSyncs, failedSyncs, syncHistoryData) {
		let message = '<div class="sync-results">';

		if (successfulSyncs.length > 0) {
			message += '<div class="sync-results-section"><strong>Successfully synced to:</strong><ul class="sync-results-list">';
			successfulSyncs.forEach(id => {
				message += `<li><a href="https://app.iterable.com/templates/editor?templateId=${id}" target="_blank">${id} <i class="fa-solid fa-arrow-up-right-from-square"></i></a></li>`;
			});
			message += '</ul></div>';
		}

		var hasRichFailures = failedSyncs.some(function(f) {
			return f && f.error && typeof f.error === 'object' && typeof f.error.html === 'string' && f.error.html.length > 0;
		});

		if (failedSyncs.length > 0) {
			if (hasRichFailures) {
				ensureSyncErrorViewerStyles();
				message += '<div class="sync-results-section sync-results-failed"><strong>Failed to sync:</strong>';
				failedSyncs.forEach(function(f, idx) {
					var err = f && f.error;
					var rawMsg = (err && typeof err === 'object' ? (err.rawMsg || err.message) : err) || 'Unknown error';
					var hasViewer = err && typeof err === 'object' && typeof err.html === 'string' && err.html.length > 0;
					message += ''
						+ '<div class="wiz-sync-error-failure" data-failure-idx="' + idx + '">'
						+   '<div class="wiz-sync-error-failure-header">'
						+     '<div><strong>' + escapeHtmlText(f.id) + '</strong>: <span class="wiz-sync-error-failure-msg">' + escapeHtmlText(rawMsg) + '</span></div>'
						+     (hasViewer ? '<div><i class="fa-solid fa-chevron-down"></i></div>' : '')
						+   '</div>'
						+   (hasViewer
							? '<div class="wiz-sync-error-failure-body">'
								+ '<div class="wiz-sync-error-toolbar">'
								+   '<button type="button" class="button wiz-sync-error-copy">Copy HTML</button>'
								+   '<button type="button" class="button wiz-sync-error-download">Download HTML</button>'
								+   (err.line ? '<button type="button" class="button wiz-sync-error-jump">Jump to error</button>' : '')
								+ '</div>'
								+ '<div class="wiz-sync-error-cm-host"></div>'
							+ '</div>'
							: '')
						+ '</div>';
				});
				message += '</div>';
			} else {
				message += '<div class="sync-results-section sync-results-failed"><strong>Failed to sync:</strong><ul class="sync-results-list">';
				failedSyncs.forEach(f => {
					var errText = (f && f.error && typeof f.error === 'object' ? (f.error.message || f.error.rawMsg) : f.error) || 'Unknown error';
					message += `<li>${escapeHtmlText(f.id)}: ${escapeHtmlText(errText)}</li>`;
				});
				message += '</ul></div>';
			}
		}

		message += '</div>';

		// Update the sync history UI without page reload
		if (syncHistoryData) {
			updateSyncHistoryUI(syncHistoryData.syncHistory, syncHistoryData.primaryTemplateId);
		}

		Swal.fire({
			title: "Sync Complete",
			html: message,
			icon: successfulSyncs.length > 0 ? 'success' : 'error',
			showConfirmButton: true,
			width: hasRichFailures ? '95vw' : undefined,
			customClass: {
				htmlContainer: 'sync-results-container',
				popup: hasRichFailures ? 'wiz-sync-error-popup' : undefined,
			},
			didOpen: function(popup) {
				if (!hasRichFailures) return;

				function wireToolbar(container, err, cm) {
					var $c = jQuery(container);
					$c.find('.wiz-sync-error-copy').off('click').on('click', function() {
						copyToClipboard(err.html);
					});
					$c.find('.wiz-sync-error-download').off('click').on('click', function() {
						try {
							var blob = new Blob([err.html], { type: 'text/html;charset=utf-8' });
							var url = URL.createObjectURL(blob);
							var a = document.createElement('a');
							a.href = url;
							a.download = 'iterable-sync-error-' + (err.templateId || 'template') + '.html';
							document.body.appendChild(a);
							a.click();
							document.body.removeChild(a);
							setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
						} catch (e) {
							if (window.console) console.warn('Download failed:', e);
						}
					});
					$c.find('.wiz-sync-error-jump').off('click').on('click', function() {
						if (!cm || !err.line) return;
						var lineIdx = Math.max(0, err.line - 1);
						var ch = err.col ? Math.max(0, err.col - 1) : 0;
						cm.scrollIntoView({ line: lineIdx, ch: ch }, 200);
						cm.setCursor({ line: lineIdx, ch: ch });
						cm.focus();
					});
				}

				jQuery(popup).find('.wiz-sync-error-failure').each(function() {
					var $row = jQuery(this);
					var idx = parseInt($row.attr('data-failure-idx'), 10);
					var f = failedSyncs[idx];
					if (!f || !f.error || typeof f.error !== 'object' || !f.error.html) return;
					var $body = $row.find('.wiz-sync-error-failure-body');
					var host = $body.find('.wiz-sync-error-cm-host')[0];
					var cmInstance = null;

					$row.find('.wiz-sync-error-failure-header').on('click', function() {
						var wasExpanded = $row.hasClass('expanded');
						$row.toggleClass('expanded');
						if (!wasExpanded && !cmInstance && host) {
							cmInstance = renderSyncErrorViewer(host, {
								html: f.error.html,
								line: f.error.line,
								col:  f.error.col,
							});
							wireToolbar($body[0], f.error, cmInstance);
						}
					});
				});
			},
			willClose: function() {
				if (hasRichFailures) disposeSyncErrorViewers();
			}
		}).then(() => {
			toggleOverlay(false);
		});
	}
	
	// Update the sync history section in the UI without page reload
	function updateSyncHistoryUI(syncHistory, primaryTemplateId) {
		const $historyList = $('#sync-history-list');
		
		if (!$historyList.length) return;
		
		// Update primary template ID field
		$('#iterable_template_id').val(primaryTemplateId || '');
		
		// Update the Iterable link and Clear button next to primary ID
		const $inputParent = $('#iterable_template_id').parent();
		const $iterableLink = $inputParent.find('.iterable-link');
		const $clearBtn = $inputParent.find('.clear-primary-template');
		if (primaryTemplateId) {
			if ($iterableLink.length) {
				$iterableLink.attr('href', `https://app.iterable.com/templates/editor?templateId=${primaryTemplateId}`).show();
			} else {
				$inputParent.append(`
					<a href="https://app.iterable.com/templates/editor?templateId=${primaryTemplateId}" 
						target="_blank" class="iterable-link" title="View in Iterable">
						<i class="fa-solid fa-arrow-up-right-from-square"></i>
					</a>
				`);
			}
			if (!$clearBtn.length) {
				$inputParent.append(`
					<button type="button" class="clear-primary-template" title="Clear primary template ID">
						<i class="fa-solid fa-xmark"></i> Clear
					</button>
				`);
			} else {
				$clearBtn.show();
			}
		} else {
			$iterableLink.hide();
			$clearBtn.hide();
		}
		
		// Clear existing history
		$historyList.empty();
		
		if (!syncHistory || syncHistory.length === 0) {
			$historyList.html('<div class="sync-history-empty">No sync history yet</div>');
			return;
		}
		
		// Sort by synced_at descending (most recent first)
		syncHistory.sort((a, b) => new Date(b.synced_at) - new Date(a.synced_at));
		
		// Build new history items
		syncHistory.forEach(entry => {
			const templateId = entry.template_id;
			const isPrimary = (String(templateId) === String(primaryTemplateId));
			const syncedAt = entry.synced_at ? formatSyncDate(entry.synced_at) : 'Unknown';
			
			const itemHtml = `
				<div class="sync-history-item${isPrimary ? ' is-primary' : ''}" data-template-id="${templateId}">
					<span class="sync-history-id">
						<a href="https://app.iterable.com/templates/editor?templateId=${templateId}" 
							target="_blank" title="View in Iterable">
							${templateId}
							<i class="fa-solid fa-arrow-up-right-from-square"></i>
						</a>
						${isPrimary ? '<span class="primary-badge">Primary</span>' : ''}
					</span>
					<span class="sync-history-date">${syncedAt}</span>
					${!isPrimary ? `<button type="button" class="make-primary-template" data-template-id="${templateId}" title="Make primary">
						<i class="fa-solid fa-star"></i>
					</button>` : ''}
					<button type="button" class="remove-from-history" data-template-id="${templateId}" title="Remove from history">
						<i class="fa-solid fa-times"></i>
					</button>
				</div>
			`;
			$historyList.append(itemHtml);
		});
	}
	
	// Format sync date for display
	function formatSyncDate(isoDate) {
		try {
			const date = new Date(isoDate);
			return date.toLocaleString('en-US', {
				month: 'short',
				day: 'numeric',
				year: 'numeric',
				hour: 'numeric',
				minute: '2-digit',
				hour12: true
			});
		} catch (e) {
			return 'Unknown';
		}
	}

	// The SMS editor (sms-template.js) shares the sync history list
	window.updateSyncHistoryUI = updateSyncHistoryUI;


	// Function to handle success of template sync
	function handleTemplateUpdateSuccess(templateId) {
		$('#iterable_template_id').val(templateId).change();
		//$('#iterable_template_id').attr('data-auto-publish', 'true');
		Swal.fire({
			title: "Sync complete",
			html: `Sync was successful!<br/><a style="text-decoration:underline;" href="https://app.iterable.com/templates/editor?templateId=${templateId}" target="_blank">Click here to go to Iterable template</a>.`,
			showConfirmButton: true,
		}).then(() => {
			
			toggleOverlay(false);

			save_template_data();

			// Reload the page to reflect changes (if necessary)
			//var currentUrl = window.location.href;
			//window.location.href = currentUrl;
		});
	}

	// Function to handle failure of template update.
	// Accepts:
	//   - a string (legacy / non-HTML error)
	//   - a single rich error object { message, rawMsg, html, line, col, templateId }
	//   - an array of failure entries [{ id, error }] from processSyncs
	function handleTemplateUpdateFailure(error) {
		// Normalize input into an array of failure entries: [{ id, err }]
		var entries = [];
		if (Array.isArray(error)) {
			entries = error.map(function(f) {
				return { id: (f && f.id) || (f && f.error && f.error.templateId) || 'template', err: f && f.error };
			});
		} else if (error && typeof error === 'object') {
			entries = [{ id: error.templateId || 'template', err: error }];
		} else {
			entries = [{ id: 'template', err: error }];
		}

		// Find rich entries that include the generated HTML so we can render the viewer.
		var richEntries = entries.filter(function(e) {
			return e.err && typeof e.err === 'object' && typeof e.err.html === 'string' && e.err.html.length > 0;
		});

		// If no rich entries, fall back to the simple message dialog (legacy behavior).
		if (richEntries.length === 0) {
			var msg = entries.map(function(e) {
				if (e.err && typeof e.err === 'object') return e.err.message || e.err.rawMsg || 'Unknown error';
				return e.err;
			}).join('<br>');
			Swal.fire({
				title: "Sync failed!",
				html: msg,
				icon: "error",
			}).then(() => {
				var currentUrl = window.location.href;
				window.location.href = currentUrl;
			});
			return;
		}

		ensureSyncErrorViewerStyles();

		// Build the modal body. Single rich entry = inline viewer; multiple = expandable list.
		var bodyHtml;
		if (entries.length === 1) {
			var only = entries[0];
			bodyHtml = ''
				+ buildSyncErrorSummaryHtml(only.err)
				+ '<div class="wiz-sync-error-toolbar">'
				+   '<button type="button" class="button wiz-sync-error-copy">Copy HTML</button>'
				+   '<button type="button" class="button wiz-sync-error-download">Download HTML</button>'
				+   (only.err.line ? '<button type="button" class="button wiz-sync-error-jump">Jump to error</button>' : '')
				+ '</div>'
				+ '<div class="wiz-sync-error-cm-host" data-failure-idx="0"></div>';
		} else {
			bodyHtml = '<div class="wiz-sync-error-summary">' + escapeHtmlText(richEntries.length + ' of ' + entries.length + ' sync attempt(s) failed. Click a row to view the generated HTML.') + '</div>';
			entries.forEach(function(entry, idx) {
				var err = entry.err || {};
				var rawMsg = (typeof err === 'object' ? (err.rawMsg || err.message) : err) || 'Unknown error';
				var hasViewer = typeof err === 'object' && typeof err.html === 'string' && err.html.length > 0;
				bodyHtml += ''
					+ '<div class="wiz-sync-error-failure" data-failure-idx="' + idx + '">'
					+   '<div class="wiz-sync-error-failure-header">'
					+     '<div><strong>' + escapeHtmlText(entry.id) + '</strong>: <span class="wiz-sync-error-failure-msg">' + escapeHtmlText(rawMsg) + '</span></div>'
					+     (hasViewer ? '<div><i class="fa-solid fa-chevron-down"></i></div>' : '')
					+   '</div>'
					+   (hasViewer
						? '<div class="wiz-sync-error-failure-body">'
							+ '<div class="wiz-sync-error-toolbar">'
							+   '<button type="button" class="button wiz-sync-error-copy">Copy HTML</button>'
							+   '<button type="button" class="button wiz-sync-error-download">Download HTML</button>'
							+   (err.line ? '<button type="button" class="button wiz-sync-error-jump">Jump to error</button>' : '')
							+ '</div>'
							+ '<div class="wiz-sync-error-cm-host"></div>'
						+ '</div>'
						: '')
					+ '</div>';
			});
		}

		Swal.fire({
			title: 'Sync failed!',
			html: bodyHtml,
			icon: 'error',
			width: '95vw',
			customClass: { popup: 'wiz-sync-error-popup' },
			showConfirmButton: true,
			confirmButtonText: 'Close',
			didOpen: function(popup) {
				var $popup = jQuery(popup);

				// Helper that wires the toolbar buttons inside a given container.
				function wireToolbar(container, err, cm) {
					var $c = jQuery(container);
					$c.find('.wiz-sync-error-copy').off('click').on('click', function() {
						copyToClipboard(err.html);
					});
					$c.find('.wiz-sync-error-download').off('click').on('click', function() {
						try {
							var blob = new Blob([err.html], { type: 'text/html;charset=utf-8' });
							var url = URL.createObjectURL(blob);
							var a = document.createElement('a');
							a.href = url;
							a.download = 'iterable-sync-error-' + (err.templateId || 'template') + '.html';
							document.body.appendChild(a);
							a.click();
							document.body.removeChild(a);
							setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
						} catch (e) {
							if (window.console) console.warn('Download failed:', e);
						}
					});
					$c.find('.wiz-sync-error-jump').off('click').on('click', function() {
						if (!cm || !err.line) return;
						var lineIdx = Math.max(0, err.line - 1);
						var ch = err.col ? Math.max(0, err.col - 1) : 0;
						cm.scrollIntoView({ line: lineIdx, ch: ch }, 200);
						cm.setCursor({ line: lineIdx, ch: ch });
						cm.focus();
					});
				}

				if (entries.length === 1) {
					var only = entries[0];
					var host = popup.querySelector('.wiz-sync-error-cm-host');
					var cm = renderSyncErrorViewer(host, { html: only.err.html, line: only.err.line, col: only.err.col });
					wireToolbar(popup, only.err, cm);
				} else {
					// Wire expandable failure rows. Render CodeMirror lazily on first expand.
					$popup.find('.wiz-sync-error-failure').each(function() {
						var $row = jQuery(this);
						var idx = parseInt($row.attr('data-failure-idx'), 10);
						var entry = entries[idx];
						if (!entry || !entry.err || typeof entry.err !== 'object' || !entry.err.html) return;
						var $body = $row.find('.wiz-sync-error-failure-body');
						var host = $body.find('.wiz-sync-error-cm-host')[0];
						var cmInstance = null;

						$row.find('.wiz-sync-error-failure-header').on('click', function() {
							var wasExpanded = $row.hasClass('expanded');
							$row.toggleClass('expanded');
							if (!wasExpanded && !cmInstance && host) {
								cmInstance = renderSyncErrorViewer(host, {
									html: entry.err.html,
									line: entry.err.line,
									col:  entry.err.col,
								});
								wireToolbar($body[0], entry.err, cmInstance);
							}
						});
					});
				}
			},
			willClose: function() {
				disposeSyncErrorViewers();
			},
		}).then(() => {
			var currentUrl = window.location.href;
			window.location.href = currentUrl;
		});
	}

	// Function to create or update Iterable template
	// useUniqueClientId: when true, appends timestamp to clientTemplateId to force Iterable to create a new template
	function create_or_update_iterable_template(templateData, existingTemplateId = null, alreadySent = false, useUniqueClientId = false) {
		
		return new Promise((resolve, reject) => {
			// Check early if we're trying to update an already sent template and bail if so
			if (existingTemplateId && alreadySent === true) {
				reject("You cannot update an Iterable template attached to an already sent campaign!");
				return;
			}

		const {
			postId,
			createdBy,
			templateName,
			messageType,
			emailSubject,
			preheader,
			fromName,
			replyToEmail,
			plainText,
			googleAnalyticsCampaignName,
			linkParams,
			messageTypeId
		} = templateData;

			// Ensure we have a valid message type ID
			if (!messageTypeId) {
				reject("No message type ID selected. Please select a message type before syncing.");
				return;
			}

			const fromSender = messageType == "transactional" ? config.TRANSACTIONAL_FROM_EMAIL : config.PROMOTIONAL_FROM_EMAIL;

			// Set up parameters for get_template_part_do_callback
			var params = {
				partType: 'fullTemplate',
				isEditor: false,
				templateId: idAjax_builder_functions.currentPostId,
				security: idAjax_template_editor.nonce,
			};

			// Call get_template_part_do_callback to get the HTML
			get_template_part_do_callback(params, function(error, data) {
				if (error) {
					reject("Failed to fetch template HTML: " + error.message);
					return;
				}

				let templateHtml = data.html;

				// Replace curly quotes with straight quotes
				templateHtml = templateHtml.replace(/[\u201C\u201D]/g, '"');
            
				// Decode HTML entities
				templateHtml = decodeHtml(templateHtml);
            
			// Use unique clientTemplateId when creating additional templates
			// This prevents Iterable's upsert from finding/updating the existing template
			const clientId = useUniqueClientId ? `${postId}_${Date.now()}` : postId;
			
			const apiData = {
				name: templateName,
				fromName: fromName,
				fromEmail: fromSender,
				replyToEmail: replyToEmail,
				subject: emailSubject,
				preheaderText: preheader,
				clientTemplateId: clientId,
				creatorUserId: createdBy,
				messageTypeId,
				html: templateHtml,
				plainText: plainText,
				googleAnalyticsCampaignName: googleAnalyticsCampaignName,
				linkParams: linkParams,
			};

				if (existingTemplateId) {
					apiData.templateId = parseInt(existingTemplateId);
				}

				const apiUrl = existingTemplateId ? 
					"https://api.iterable.com/api/templates/email/update" : 
					"https://api.iterable.com/api/templates/email/upsert";

				$.ajax({
					type: "POST",
					url: apiUrl,
					data: JSON.stringify(apiData),
					contentType: "text/plain",
					beforeSend: function (xhr) {
						xhr.setRequestHeader("Api-Key", config.API_KEY);
					},
				})
				.done(function(response) {
					if (!existingTemplateId) {
						existingTemplateId = response.templateId || extractID(response.msg);
					}

					updateTemplateAfterSync(existingTemplateId, postId);

					resolve(existingTemplateId);
				})
				.fail(function(jqXHR) {
					let errorResponse = {};
					try { errorResponse = JSON.parse(jqXHR.responseText); } catch (e) {}
					const rawMsg = (errorResponse && errorResponse.msg) || "An unknown error occurred.";
					const parsed = extractMergeError(rawMsg);

					reject({
						message: "Failed to update or create Iterable template. Error: " + rawMsg,
						rawMsg: rawMsg,
						html: templateHtml,
						line: parsed ? parsed.line : null,
						col:  parsed ? parsed.col  : null,
						found: parsed ? parsed.found : null,
						templateId: existingTemplateId || 'new',
					});
				});
			});
		});
	}

	// Function to decode HTML entities
	function decodeHtml(html) {
	  var txt = document.createElement("textarea");
	  txt.innerHTML = html;
	  return txt.value;
	}


	// Function to update template after sync
	function updateTemplateAfterSync(templateId, postId = null, templateName = null) {
		return $.post(idAjax.ajaxurl, {
			action: "update_template_after_sync",
			post_id: postId,
			template_id: templateId,
			template_name: templateName,
			security: idAjax_iterable_actions.nonce
		});
	}

	// Function to extract ID from string
	function extractID(s) {
		const match = s.match(/\b(\d+)$/);
		return match ? parseInt(match[1], 10) : null;
	}

	// Function to update the template name
	function updateIterableTemplateName(templateId, newTemplateName) {
	  return new Promise((resolve, reject) => {
		const apiData = {
		  templateId: parseInt(templateId),
		  name: newTemplateName
		};

		$.ajax({
		  type: "POST",
		  url: "https://api.iterable.com/api/templates/email/update",
		  data: JSON.stringify(apiData),
		  contentType: "text/plain",
		  beforeSend: function (xhr) {
			xhr.setRequestHeader("Api-Key", config.API_KEY);
		  },
		})
		.done(function(response) {
		
		  resolve(response);
		})
		.fail(function(jqXHR) {
		  var errorResponse = JSON.parse(jqXHR.responseText);
		  var errorMessage = "Failed to update template name. ";
      
		  if (errorResponse && errorResponse.msg) {
			errorMessage += "Error: " + errorResponse.msg;
		  } else {
			errorMessage += "An unknown error occurred.";
		  }

		  reject(errorMessage);
		});
	  });
	}

	// Click event handler for removing template from sync history
	$(document).on('click', '.remove-from-history', function(e) {
		e.preventDefault();
		const $item = $(this).closest('.sync-history-item');
		const templateId = $(this).data('template-id');
		const postId = $('#sync-history-list').data('post-id');
		const hadUnsavedChanges = sessionStorage.getItem('unsavedChanges') === 'true';
		
		Swal.fire({
			title: 'Remove from history?',
			text: `Remove template ${templateId} from sync history?`,
			icon: 'warning',
			showCancelButton: true,
			confirmButtonText: 'Remove',
			confirmButtonColor: '#d33'
		}).then((result) => {
			if (result.isConfirmed) {
				$.post(idAjax.ajaxurl, {
					action: 'remove_from_iterable_sync_history',
					security: idAjax_iterable_actions.nonce,
					post_id: postId,
					template_id: templateId
				}).done(function(response) {
					if (response.status === 'success') {
						$item.fadeOut(300, function() {
							$(this).remove();
							$('#iterable_template_id').val(response.primaryTemplateId || '');
							if ($('#sync-history-list .sync-history-item').length === 0) {
								$('#sync-history-list').html('<div class="sync-history-empty">No sync history yet</div>');
							}
							if (!hadUnsavedChanges) {
								sessionStorage.setItem('unsavedChanges', 'false');
							}
						});
					} else {
						Swal.fire('Error', response.message, 'error');
					}
				}).fail(function() {
					Swal.fire('Error', 'Failed to remove template from history', 'error');
				});
			}
		});
	});

	// Click event handler for "Make Primary" button in sync history
	$(document).on('click', '.make-primary-template', function(e) {
		e.preventDefault();
		const templateId = $(this).data('template-id');
		const postId = $('#sync-history-list').data('post-id');
		const hadUnsavedChanges = sessionStorage.getItem('unsavedChanges') === 'true';

		Swal.fire({
			title: 'Make primary?',
			text: `Set template ${templateId} as the primary template ID?`,
			icon: 'question',
			showCancelButton: true,
			confirmButtonText: 'Make Primary',
			confirmButtonColor: '#28a745'
		}).then((result) => {
			if (result.isConfirmed) {
				$.post(idAjax.ajaxurl, {
					action: 'set_primary_iterable_template',
					security: idAjax_iterable_actions.nonce,
					post_id: postId,
					template_id: templateId
				}).done(function(response) {
					if (response.status === 'success') {
						updateSyncHistoryUI(response.syncHistory, response.primaryTemplateId);
						if (!hadUnsavedChanges) {
							sessionStorage.setItem('unsavedChanges', 'false');
						}
					} else {
						Swal.fire('Error', response.message, 'error');
					}
				}).fail(function() {
					Swal.fire('Error', 'Failed to update primary template', 'error');
				});
			}
		});
	});

	// Click event handler for "Clear Primary" button next to primary template ID
	$(document).on('click', '.clear-primary-template', function(e) {
		e.preventDefault();
		const postId = $('#sync-history-list').data('post-id');
		const hadUnsavedChanges = sessionStorage.getItem('unsavedChanges') === 'true';

		Swal.fire({
			title: 'Clear primary?',
			text: 'Clear the primary template ID? It will be re-assigned on the next sync.',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonText: 'Clear',
			confirmButtonColor: '#d33'
		}).then((result) => {
			if (result.isConfirmed) {
				$.post(idAjax.ajaxurl, {
					action: 'clear_primary_iterable_template',
					security: idAjax_iterable_actions.nonce,
					post_id: postId
				}).done(function(response) {
					if (response.status === 'success') {
						updateSyncHistoryUI(response.syncHistory, response.primaryTemplateId);
						if (!hadUnsavedChanges) {
							sessionStorage.setItem('unsavedChanges', 'false');
						}
					} else {
						Swal.fire('Error', response.message, 'error');
					}
				}).fail(function() {
					Swal.fire('Error', 'Failed to clear primary template', 'error');
				});
			}
		});
	});

	// Click event handler for the "edit name" link
	$(document).on('click', '.editTemplateName', function(e) {
	  e.preventDefault();
	  const templateId = $(this).data('templateid');
	  const currentName = $(this).data('currentname');

	  Swal.fire({
		title: 'Update Template Name',
		icon: 'info',
		input: 'text',
		inputValue: currentName,
		inputPlaceholder: 'Enter new template name',
		text: 'This will rename the template in Iterable and The Wizard',
		showCancelButton: true,
		confirmButtonText: 'Update',
		showLoaderOnConfirm: true,
		preConfirm: (newTemplateName) => {
		  return updateIterableTemplateName(templateId, newTemplateName)
			.then(response => {
			  updateTemplateAfterSync(templateId, null, newTemplateName);
			  Swal.fire({
				title: 'Template Name Updated',
				text: 'The template name has been successfully updated.',
				icon: 'success'
			  }).then(() => {
				location.reload();
			  });
			})
			.catch(error => {
			  Swal.showValidationMessage(error);
			});
		},
		allowOutsideClick: () => !Swal.isLoading()
	  });
	});

});
