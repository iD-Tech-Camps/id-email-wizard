jQuery(document).ready(function ($) {
	// Call toggleOverlay(false) once everything (including images, iframes, scripts, etc.) has finished loading
	$(window).on("load", function () {
		toggleOverlay(false);
	});

	// Reload an element in-place by re-fetching the current URL and swapping
	// the matching subtree into the DOM. Feature-specific re-initialization
	// (e.g. the folder sidebar and the template quick-search) hooks in via
	// the custom `idwiz:uiRefreshed` event that this dispatches on document.
	(function ($) {
		$.refreshUIelement = function (selector) {
			var container = $("<div>").load(location.href + " " + selector + " > *", function () {
				$(selector).html(container.contents());
				$(document).trigger("idwiz:uiRefreshed", [selector]);
			});
		};
	})(jQuery);

	//apply highlight to all <code> elements
	hljs.highlightAll();

	// Stop click events within the Select2 dropdown from propagating
	$(document).on("click", ".select2-dropdown", function (e) {
		e.stopPropagation();
	});
});

//Global scope functions

// A generalized Ajax call.
// Params: action_name, nonce_value, array of passed data, success callback, error callback
// The callback functions can either be directly built in the function or can also take names of
// callback functions which will get the data and error objects passed into them for use
// This function can handle  calls that rely on await, or not
function idemailwiz_do_ajax(actionFunctionName, nonceValue, additionalData, successCallback, errorCallback, dataType = "json", contentType = "application/x-www-form-urlencoded; charset=UTF-8") {
	return new Promise((resolve, reject) => {
		let defaultData = {
			action: actionFunctionName,
			security: nonceValue,
		};

		let mergedData = Object.assign({}, defaultData, additionalData);

		jQuery
			.ajax({
				url: idAjax.ajaxurl,
				context: this,
				type: "POST",
				data: mergedData,
				dataType: dataType,
				contentType: contentType,
				
			})
			.done((response) => {
				if (successCallback) {
					successCallback(response);
				}
				resolve(response);
			})
			.fail((xhr, status, error) => {
				if (errorCallback) {
					errorCallback(xhr, status, error);
				}
				reject({ xhr, status, error });
			})
			.always(function () {
				// Always executed regardless of response
			});
	});
}

// Even more global function to reload and element, when passed one
function wizReloadThing(selector) {
	var element = jQuery(selector);

	if (element.length) {
		// Fetch the current page's content
		jQuery.ajax({
			url: window.location.href,
			type: "GET",
			success: function (data) {
				// Replace the element's content with the content from the fetched page
				var updatedContent = jQuery(selector, data).html();
				element.html(updatedContent);
			},
			error: function () {
				console.error("Failed to reload element:", selector);
			},
		});
	} else {
		console.warn("Element not found:", selector);
	}
}

// Generates and displays a notification
// Example: do_wiz_notif({ message: 'You clicked a thing.', duration: 3000 });
function do_wiz_notif(notifData) {
	// Create the notification elements
	var notif = jQuery("<div>", { class: "wizNotif" });
	var icon = jQuery("<div>", { class: "wizNotifIcon" }).append(jQuery("<i>", { class: "fa fa-bell" }));
	var content = jQuery("<div>", { class: "wizNotifContent" }).html(notifData.message);
	var close = jQuery("<i>", { class: "fa fa-times wizNotifClose" });

	// Assemble the notification
	notif.append(icon, content, close);

	// Append to the container
	jQuery(".wizNotifs").prepend(notif);

	// Set a timeout to automatically remove the notification after a certain period
	setTimeout(function () {
		notif.fadeOut(function () {
			jQuery(this).remove();
		});
	}, notifData.duration || 5000); // Default duration to 5 seconds if not specified

	// Handle the click event on the close button
	close.on("click", function () {
		notif.fadeOut(function () {
			jQuery(this).remove();
		});
	});
}

function checkImageUrl(url, callback) {
	jQuery.get(url)
		.done(function() {
			callback(true); // Image is valid
		})
		.fail(function() {
			callback(false); // Image is not valid
		});
}
