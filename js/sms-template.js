jQuery(document).ready(function ($) {
	if (!$("#sms-template-editor").length) {
		return;
	}

	const postId = $("#sms-template-editor").data("postid");
	let unsavedChanges = false;

	init_template_title_tinymce();

	// GSM-7 characters fit 160 per SMS (153 per part when split). Anything outside
	// it switches the whole message to UCS-2: 70 per SMS, 67 per part.
	const GSM_BASIC = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
	const GSM_EXTENDED = "^{}\\[~]|€\f";

	function countSegments(text) {
		let gsmLength = 0;
		let isGsm = true;
		for (const char of text) {
			if (GSM_BASIC.includes(char)) {
				gsmLength += 1;
			} else if (GSM_EXTENDED.includes(char)) {
				gsmLength += 2;
			} else {
				isGsm = false;
				break;
			}
		}

		const length = isGsm ? gsmLength : [...text].length;
		const single = isGsm ? 160 : 70;
		const perPart = isGsm ? 153 : 67;
		const segments = length === 0 ? 0 : length <= single ? 1 : Math.ceil(length / perPart);
		return { length, segments, encoding: isGsm ? "GSM-7" : "Unicode" };
	}

	function updatePreview() {
		const message = $("#sms-message").val();
		const imageUrl = $("#sms-image-url").val().trim();

		$("#sms-preview-text").text(message);
		if (imageUrl) {
			$("#sms-preview-image").attr("src", imageUrl).prop("hidden", false);
		} else {
			$("#sms-preview-image").removeAttr("src").prop("hidden", true);
		}

		const stats = countSegments(message);
		let statsText = `${stats.length} characters · ${stats.segments} ${stats.segments === 1 ? "segment" : "segments"} · ${stats.encoding}`;
		if (/\{\{.*?\}\}/.test(message)) {
			statsText += " · merge tags will change the final length";
		}
		$("#sms-message-stats").text(statsText);
	}

	function saveSmsTemplate() {
		return idemailwiz_do_ajax("save_wiz_sms_template", idAjax_sms_template.nonce, {
			post_id: postId,
			message: $("#sms-message").val(),
			image_url: $("#sms-image-url").val().trim(),
			message_type_id: $('input[name="sms-message-type"]:checked').val() || "",
		}).then((response) => {
			if (!response.success) {
				throw new Error(response.data.message);
			}
			unsavedChanges = false;
			return response;
		});
	}

	$("#sms-message, #sms-image-url").on("input", function () {
		unsavedChanges = true;
		updatePreview();
	});

	$('input[name="sms-message-type"]').on("change", function () {
		unsavedChanges = true;
	});

	$("#save-sms-template").on("click", function () {
		saveSmsTemplate()
			.then((response) => do_wiz_notif({ message: response.data.message, duration: 3000 }))
			.catch((error) => Swal.fire("Error", error.message || "Could not save the template", "error"));
	});

	function syncTargetHtml(templateId, isPrimary) {
		return `
			<label class="sync-target-item">
				<input type="checkbox" name="sync_target" value="${templateId}" checked>
				<span class="sync-target-id">${templateId}${isPrimary ? ' <span class="primary-badge">Primary</span>' : ""}</span>
				<a href="https://app.iterable.com/templates/editor?templateId=${templateId}"
					target="_blank" class="sync-target-link" title="View in Iterable">
					<i class="fa-solid fa-arrow-up-right-from-square"></i>
				</a>
			</label>`;
	}

	// Every ID in the sync history starts checked, like the email sync
	function buildSyncTargets() {
		const primaryId = String($("#iterable_template_id").val() || "");
		const historyIds = $("#sync-history-list .sync-history-item")
			.map((i, el) => String($(el).data("template-id")))
			.get();
		if (primaryId && !historyIds.includes(primaryId)) {
			historyIds.unshift(primaryId);
		}

		return `
			<div class="sync-targets-section">
				<label class="sync-targets-label">Sync to:</label>
				<div class="sync-target-manual">
					<div class="sync-target-manual-input-wrap">
						<input type="text" id="manual_template_id" placeholder="Template ID from an SMS campaign">
						<button type="button" id="add_manual_template" class="wiz-button small green">Add</button>
					</div>
				</div>
				<div class="sync-targets-list">
					${historyIds.map((id) => syncTargetHtml(id, id === primaryId)).join("")}
				</div>
			</div>`;
	}

	function addManualTarget() {
		const ids = $("#manual_template_id").val().split(/[\s,]+/).map((id) => id.replace(/\D/g, "")).filter(Boolean);
		ids.forEach((id) => {
			const $existing = $(`.sync-targets-list input[value="${id}"]`);
			if ($existing.length) {
				$existing.prop("checked", true);
			} else {
				$(".sync-targets-list").append(syncTargetHtml(id, false));
			}
		});
		$("#manual_template_id").val("").trigger("focus");
	}

	function showSyncResults(results) {
		const failed = results.filter((r) => r.error);
		const succeeded = results.filter((r) => !r.error);
		const list = results
			.map((r) => `<li>${r.error ? "❌" : "✅"} <strong>${r.templateId}</strong>${r.error ? ": " + r.error : ""}</li>`)
			.join("");

		Swal.fire({
			icon: failed.length === 0 ? "success" : succeeded.length === 0 ? "error" : "warning",
			title: failed.length === 0 ? "Synced!" : `${succeeded.length} of ${results.length} synced`,
			html: `<ul class="sms-sync-results">${list}</ul>`,
		});
	}

	$("#sync-sms-template").on("click", function () {
		if (!$('input[name="sms-message-type"]:checked').length) {
			Swal.fire("Choose a message type", "Pick Marketing or Transactional SMS before syncing.", "warning");
			return;
		}

		Swal.fire({
			title: "Sync to Iterable?",
			html: `<p>This saves, then overwrites the message in each checked template.</p>${buildSyncTargets()}`,
			icon: "info",
			showCancelButton: true,
			confirmButtonText: "Save & Sync",
			showLoaderOnConfirm: true,
			allowOutsideClick: () => !Swal.isLoading(),
			didOpen: () => {
				$("#add_manual_template").on("click", addManualTarget);
				$("#manual_template_id").on("keydown", function (e) {
					if (e.key === "Enter") {
						e.preventDefault();
						addManualTarget();
					}
				});
			},
			preConfirm: () => {
				// An ID typed but not added yet still counts
				if ($("#manual_template_id").val().trim()) {
					addManualTarget();
				}
				const templateIds = $('input[name="sync_target"]:checked').map((i, el) => el.value).get();
				if (!templateIds.length) {
					Swal.showValidationMessage("Add or check at least one template ID.");
					return false;
				}

				return saveSmsTemplate()
					.then(() =>
						idemailwiz_do_ajax("sync_wiz_sms_template", idAjax_sms_template.nonce, {
							post_id: postId,
							template_ids: templateIds,
						})
					)
					.then((response) => {
						if (!response.success) {
							throw new Error(response.data.message);
						}
						return response.data;
					})
					.catch((error) => {
						Swal.showValidationMessage(error.message || "Sync failed");
					});
			},
		}).then((result) => {
			if (result.isConfirmed && result.value) {
				window.updateSyncHistoryUI(result.value.syncHistory, result.value.primaryTemplateId);
				showSyncResults(result.value.results);
			}
		});
	});

	$(window).on("beforeunload", function () {
		if (unsavedChanges) {
			return "You have unsaved changes.";
		}
	});

	updatePreview();
});
