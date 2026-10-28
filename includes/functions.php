<?php
//Insert overlay and spinner into header
function insert_overlay_loader()
{
	//$options = get_option( 'idemailwiz_settings' );
	if ((is_single() && get_post_type() == 'idemailwiz_template')) {
?>
		<div id="iDoverlay"></div>
		<div id="iDspinner" class="loader"></div>
	<?php
	}
	?>
	<script type="text/javascript">
		// Function to show and hide overlays and spinners
		// Can optionally pass a message to display on the overlay
		const toggleOverlay = (show = true, message = null) => {
			if (show) {
				jQuery("#iDoverlay").fadeIn(100);
				jQuery("#iDspinner").fadeIn(250);
				if (message) {
					jQuery("#iDoverlay").attr('data-message', message);
				} else {
					jQuery("#iDoverlay").removeAttr('data-message');
				}
			} else {
				jQuery("#iDoverlay").fadeOut(100);
				jQuery("#iDspinner").hide();
				jQuery("#iDoverlay").removeAttr('data-message');
			}
		};
		
		// Update overlay message without toggling visibility
		const updateOverlayMessage = (message) => {
			jQuery("#iDoverlay").attr('data-message', message);
		};

		// Call toggleOverlay() as soon as the script is executed
		toggleOverlay();
	</script>
<?php
}
add_action('wp_head', 'idemailwiz_head');
function idemailwiz_head()
{
	// Preload overlay stuff so it happens fast
	insert_overlay_loader();

	//Add meta to prevent scaling on mobile (for DataTables)
	echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">';
}


// Determine if a template or folder is in the current user's favorites
function is_user_favorite($object_id, $object_type)
{
	// Determine the meta key based on the object_type
	$meta_key = 'idwiz_favorite_' . strtolower($object_type) . 's'; // either 'favorite_templates' or 'favorite_folders'

	$favorites = get_user_meta(get_current_user_id(), $meta_key, true);

	if (!is_array($favorites)) {
		$favorites = array();
	}

	// Cast IDs in favorites to integers for consistent comparison
	$favorites = array_map('intval', $favorites);

	$object_id = intval($object_id); // Ensure object_id is an integer

	// Check if $object_id is in favorites
	if (in_array($object_id, $favorites)) {
		return true;
	}

	return false;
}

//Add custom meta metabox back to edit screens
add_filter('acf/settings/remove_wp_meta_box', '__return_false');

// Add or remove a favorite template or folder from a user's profile
function add_remove_user_favorite()
{
	//check nonce
	if (
		check_ajax_referer('template-actions', 'security', false)
		|| check_ajax_referer('user-favorites', 'security', false)
		|| check_ajax_referer('initiatives', 'security', false)
	) {
	} else {
		wp_die('Invalid nonce');
	};

	// Ensure object_id and object_type are set
	$object_id = isset($_POST['object_id']) ? intval($_POST['object_id']) : 0;
	$object_type = isset($_POST['object_type']) ? sanitize_text_field($_POST['object_type']) : '';

	if ($object_id <= 0 || empty($object_type)) {
		wp_send_json(
			array(
				'success' => false,
				'message' => 'Invalid object id or object type was sent!',
				'action' => null,
				'objectid' => $object_id,
			)
		);
	}

	// Determine the meta key based on the object_type
	$meta_key = 'idwiz_favorite_' . strtolower($object_type) . 's'; // either 'idwiz_favorite_templates' or 'idwiz_favorite_folders'

	$favorites = get_user_meta(get_current_user_id(), $meta_key, true);

	if (!is_array($favorites)) {
		$favorites = array();
	}

	$success = false;
	$message = '';
	$action = '';

	$key = array_search($object_id, $favorites);
	if (false !== $key) {
		unset($favorites[$key]);
		$message = 'Favorite ' . $object_type . ' removed.';
		$action = 'removed';
	} else {
		$favorites[] = intval($object_id); // Ensure object_id is an integer
		$message = 'Favorite ' . $object_type . ' added.';
		$action = 'added';
	}
	$success = true;

	if ($success) {
		$update_status = update_user_meta(get_current_user_id(), $meta_key, $favorites);
		if ($update_status === false) {
			$success = false;
			$message = 'Failed to update user meta.';
		} else {
			$updated_favorites = get_user_meta(get_current_user_id(), $meta_key, true);
			if (!is_array($updated_favorites)) {
				$success = false;
				$message = 'User meta was updated but the structure is incorrect.';
			} else {
				// Check if the object_id was correctly added or removed
				if ($action === 'added' && !in_array($object_id, $updated_favorites)) {
					$success = false;
					$message = 'Object id was not added correctly to ' . $object_type . '.';
				} elseif ($action === 'removed' && in_array($object_id, $updated_favorites)) {
					$success = false;
					$message = 'Object id was not removed correctly from ' . $object_type . '.';
				}
			}
		}
	}

	wp_send_json(
		array(
			'success' => $success,
			'message' => $message,
			'action' => $action,
			'objectid' => $object_id,
		)
	);
}

add_action('wp_ajax_add_remove_user_favorite', 'add_remove_user_favorite');

function wiz_notifications()
{
	// Insert notifications wrapper into the footer
	echo '<div class="wizNotifs" aria-live="assertive" aria-atomic="true"></div>';
}
add_action('wp_footer', 'wiz_notifications');
