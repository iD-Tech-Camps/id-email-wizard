/**
 * Template CRUD: delete, restore, duplicate, move, create.
 *
 * Previously lived at builder-v2/js/template-actions.js, but its handlers are
 * used everywhere the template row UI is shown (archive, search results,
 * builder), so it now lives alongside the rest of the folder feature.
 *
 * Phase 1 fix: the restore-template handler was bound to `.templateTable`,
 * which $.refreshUIelement() replaces wholesale. Delegated to document so
 * it survives refreshes.
 *
 * The select2-powered quick-jump search init that used to live at the top
 * of this file has moved into folder-ui.js so there is a single owner.
 */

jQuery(document).ready(function ($) {
	$(document).on("click", ".delete-template", async function (e) {
		const post_id = $(this).attr("data-postid");
		id_delete_templates([post_id]);
	});

	$(document).on("click", ".restore-template", async function (e) {
		const post_id = $(this).attr("data-postid");
		id_restore_templates([post_id]);
	});

	$(document).on("click", ".duplicate-template", function () {
		var post_id = $(this).attr("data-postid");
		$("#iDoverlay, #iDspinner").show();
		Swal.fire({
			title: "Duplicate Template?",
			text: "This will copy all template settings and fields, but it will not sync the new template to Iterable.",
			icon: "info",
			confirmButtonText: "Duplicate",
			showCancelButton: true,
			cancelButtonText: "Nevermind",
		}).then((confirmDuplicate) => {
			if (confirmDuplicate.isConfirmed) {
				const templateDuplicateData = {
					template_action: "duplicate",
					post_id: post_id,
				};
				const templateDuplicateSuccess = function (response) {
					Swal.fire({
						title: "Template duplicated!",
						html: `Your new template is ready: <br><br><a href="${response.data.newURL}" style="color: #007cba; text-decoration: underline;">${response.data.newURL}</a>`,
						icon: "success",
						confirmButtonText: "Continue",
					}).then(() => {
						$("#iDoverlay, #iDspinner").hide();
					});
				};

				const templateDuplicateError = function (response) {
					Swal.fire("Uh oh, something went wrong! Refresh and try again maybe?", {
						icon: "error",
					});
				};
				idemailwiz_do_ajax(
					"id_ajax_template_actions",
					idAjax_template_actions.nonce,
					templateDuplicateData,
					templateDuplicateSuccess,
					templateDuplicateError
				);
			} else {
				$("#iDoverlay, #iDspinner").hide();
			}
		});
	});

	$(document).on("click", ".moveTemplate", function () {
		const thisTemplate = $(this).attr("data-postid");
		id_move_template([thisTemplate]);
	});

	$(document).on("click", ".show-new-template-ui", function () {
		showCreateTemplateModal();
	});
});

// Global-scope functions — also dispatched to by bulk-actions.js.

function id_move_template(templateIDs) {
	if (templateIDs.length > 1) {
		var msgText = "these templates";
		var confirmText = "Templates";
	} else {
		var msgText = "this template";
		var confirmText = "Template";
	}

	Swal.fire({
		title: "Move " + confirmText,
		html:
			'<p class="swal2-field-intro">Move ' + msgText + " to a new folder.</p>" +
			'<label class="swal2-field-label" for="moveToFolder">Destination folder</label>' +
			'<select id="moveToFolder" class="swal2-select"><option value="">Select new location</option></select>',
		showCancelButton: true,
		confirmButtonText: "Move " + confirmText,
		focusConfirm: false,
		preConfirm: function () {
			return new Promise(function (resolve, reject) {
				var moveInto = jQuery("#moveToFolder").val();
				if (!moveInto) {
					reject("Please select a destination folder");
					return;
				}
				resolve({
					this_template: templateIDs,
					move_into: moveInto,
				});
			}).catch(function (error) {
				Swal.showValidationMessage(error);
			});
		},
		didOpen: function () {
			const generateFoldersData = {
				action: "id_generate_folders_select_ajax",
			};

			const generateFoldersSuccess = function (response) {
				jQuery("#moveToFolder").append(response.data.options);
				jQuery("#moveToFolder").select2({
					dropdownParent: jQuery(".swal2-container"),
					width: "100%",
				});
			};

			const generateFoldersError = function (response) {
				console.error("Error generating folders", response);
			};

			idemailwiz_do_ajax(
				"id_generate_folders_select_ajax",
				idAjax_template_actions.nonce,
				generateFoldersData,
				generateFoldersSuccess,
				generateFoldersError
			);
		},
	}).then(function (result) {
		if (result.isConfirmed) {
			const moveTemplateData = {
				this_template: result.value.this_template,
				move_into: result.value.move_into,
			};

			const moveTemplateSuccess = function (response) {
				Swal.fire({
					title: "Template Moved!",
					icon: "success",
				}).then(function () {
					window.location.reload();
				});
			};

			const moveTemplateFailure = function (response) {
				Swal.fire({
					title: "Error moving template",
					icon: "error",
				}).then(function () {
					window.location.reload();
				});
			};

			idemailwiz_do_ajax(
				"id_move_template",
				idAjax_template_actions.nonce,
				moveTemplateData,
				moveTemplateSuccess,
				moveTemplateFailure
			);
		}
	});
}

async function id_delete_templates(post_ids) {
	if (post_ids.length > 1) {
		var msgText = "these templates";
		var confirmText = "Templates";
	} else {
		var msgText = "this template";
		var confirmText = "Template";
	}
	const { isConfirmed } = await Swal.fire({
		title: "Delete " + msgText + "?",
		text:
			"Trashed templates can be restored later, but deleting will un-link the template from Iterable and remove the template for all user's favorites.",
		icon: "warning",
		showCancelButton: true,
		iconColor: "#dc3545",
	});

	if (isConfirmed) {
		for (let i = 0; i < post_ids.length; i++) {
			const response = await idemailwiz_do_ajax(
				"id_ajax_template_actions",
				idAjax_template_actions.nonce,
				{
					template_action: "delete",
					post_id: post_ids[i],
				}
			).catch((error) => {
				console.error(`Error deleting post ID ${post_ids[i]}: ${error}`);
			});

			if (response && response.error) {
				console.error(`Error deleting post ID ${post_ids[i]}: ${response.error}`);
			}
		}

		Swal.fire({
			icon: "success",
			html:
				'All done! Templates can be restored from the <a href="' +
				idAjax.site_url +
				'/templates/trash/">trash</a> for 30 days.',
		}).then(() => {
			const isTemplatesArchive = window.location.href.indexOf("/templates/") > -1;
			if (isTemplatesArchive) {
				post_ids.forEach((post_id) => {
					jQuery("#template-" + post_id).css("background-color", "red").fadeOut(1500);
				});
				jQuery.refreshUIelement(".folderList");
				jQuery.refreshUIelement("#bulkActionsSelect");
			} else {
				const redirectUrl = `${window.location.origin}/templates/all`;
				window.location.href = redirectUrl;
			}
		});

		return "Delete actions completed";
	} else {
		return "Action cancelled by user";
	}
}

async function id_restore_templates(templateIDs) {
	if (templateIDs.length > 1) {
		var msgText = "these templates";
		var confirmText = "templates";
	} else {
		var msgText = "this template";
		var confirmText = "template";
	}

	const { isConfirmed } = await Swal.fire({
		title: "Restore " + msgText + "?",
		text: "If previously synced to Iterable, it will NOT re-sync upon restoration.",
		icon: "question",
		confirmButtonText: "Restore " + confirmText,
		showCancelButton: true,
		iconColor: "#dc3545",
	});
	if (isConfirmed) {
		for (let i = 0; i < templateIDs.length; i++) {
			const restoreTemplateData = {
				template_action: "restore",
				post_id: templateIDs[i],
			};

			const restoreTemplateResponse = await idemailwiz_do_ajax(
				"id_ajax_template_actions",
				idAjax_template_actions.nonce,
				restoreTemplateData,
				null,
				null
			);

			if (restoreTemplateResponse.error) {
				console.error(`Error restoring post ID ${templateIDs[i]}: ${restoreTemplateResponse.error}`);
			}
		}

		Swal.fire({
			icon: "success",
			text: "Template restored!",
		}).then(() => {
			jQuery.refreshUIelement(".templateTable");
			jQuery.refreshUIelement("#bulkActionsSelect");
		});
	}
}

function showCreateTemplateModal() {
	Swal.fire({
		title: "Create New Template",
		input: "text",
		inputLabel: "Enter a template title",
		inputPlaceholder: "e.g., 0555 | Formers | Camp is awesome!",
		html: `<div class="new-template-type">
				<label><input type="radio" name="new_template_type" value="email" checked> <i class="fa-regular fa-envelope"></i> Email</label>
				<label><input type="radio" name="new_template_type" value="sms"> <i class="fa-solid fa-comment-sms"></i> SMS</label>
			</div>`,
		icon: "info",
		confirmButtonText: "Create Template",
		showCancelButton: true,
		cancelButtonText: "Cancel",
		preConfirm: (templateTitle) => {
			if (!templateTitle.trim()) {
				Swal.showValidationMessage("Please enter a title for the new template.");
				return false;
			}
			const templateType = document.querySelector('input[name="new_template_type"]:checked').value;
			return { title: templateTitle.trim(), type: templateType };
		},
	}).then((result) => {
		if (result.isConfirmed && result.value) {
			createTemplate(result.value.title, result.value.type);
		}
	});
}

function createTemplate(templateTitle, templateType = "email") {
	idemailwiz_do_ajax(
		"create_new_wiz_template",
		idAjax_template_actions.nonce,
		{ template_title: templateTitle, template_type: templateType },
		function (response) {
			if (response.success && response.data.newURL) {
				window.location.href = response.data.newURL;
			} else {
				Swal.fire("Error", "Could not create template", "error");
			}
		},
		function (error) {
			Swal.fire("Error", "An error occurred while creating the template", "error");
		}
	);
}
