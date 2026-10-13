<?php
/**
 * Ajax handlers for template CRUD: trash, restore, duplicate, move, create.
 *
 * Also hosts the `id_restore_template()` utility and the helpers that write
 * rows into the custom wp_wiz_templates table on create/duplicate.
 *
 * As with folder-ajax.php, the wp_ajax_nopriv_* variants that existed in the
 * old mixed folder-template-actions.php file have been dropped.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Untrash + republish a template by post ID.
 *
 * @param int $post_id
 * @return bool|string True on success, error message string on failure.
 */
function id_restore_template( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post ) {
		return 'Template not found!';
	}

	if ( 'trash' !== $post->post_status ) {
		return 'This template is not trashed!';
	}

	$untrash = wp_untrash_post( $post_id );
	if ( is_wp_error( $untrash ) ) {
		return $untrash->get_error_message();
	}

	$republish = wp_publish_post( $post_id );
	if ( is_wp_error( $republish ) ) {
		return $republish->get_error_message();
	}

	return true;
}

/**
 * Dispatch handler for per-template row actions (delete/restore/duplicate).
 * The request sends a `template_action` and a `post_id`.
 */
function id_ajax_template_actions() {
	// The template row lives inside the folder archive template, but some
	// callers (e.g. the builder) use the template-actions nonce instead.
	if ( ! check_ajax_referer( 'template-actions', 'security', false ) ) {
		check_ajax_referer( 'id-general', 'security' );
	}

	$options   = get_option( 'idemailwiz_settings' );
	$trashTerm = $options['folder_trash'];
	if ( ! isset( $options['folder_trash'] ) ) {
		wp_send_json(
			array(
				'success'        => false,
				'actionResponse' => 'Trash folder term was not found!',
			)
		);
		die();
	}

	$action  = $_POST['template_action'];
	$post_id = $_POST['post_id'];
	if ( ! $post_id || ! $action ) {
		wp_send_json( false );
	}

	$doAction       = false;
	$actionResponse = '';
	switch ( $action ) {
		case 'delete':
			$currentFolder = wp_get_post_terms( $post_id, 'idemailwiz_folder', array( 'fields' => 'ids' ) );
			if ( is_wp_error( $currentFolder ) ) {
				wp_send_json(
					array(
						'success'        => false,
						'actionResponse' => $currentFolder->get_error_message(),
					)
				);
				die();
			}

			if ( ! is_wp_error( $trashTerm ) ) {
				$setDeleteTerms = wp_set_post_terms(
					$post_id,
					array_merge( array( (int) $trashTerm ), $currentFolder ),
					'idemailwiz_folder',
					false
				);
				if ( is_wp_error( $setDeleteTerms ) ) {
					wp_send_json(
						array(
							'success'        => false,
							'actionResponse' => $setDeleteTerms->get_error_message(),
						)
					);
					die();
				}
			} else {
				wp_send_json(
					array(
						'success'        => false,
						'actionResponse' => $trashTerm->get_error_message(),
					)
				);
				die();
			}

			$trashPost = wp_trash_post( $post_id );
			if ( is_wp_error( $trashPost ) ) {
				wp_send_json(
					array(
						'success'        => false,
						'actionResponse' => $trashPost->get_error_message(),
					)
				);
				die();
			}

			// Prune this template from everyone's favorites.
			$users = get_users();
			foreach ( $users as $user ) {
				$favorites = get_user_meta( $user->ID, 'idwiz_favorite_templates', true );
				if ( is_array( $favorites ) && in_array( $post_id, $favorites ) ) {
					$key = array_search( $post_id, $favorites );
					unset( $favorites[ $key ] );
					update_user_meta( $user->ID, 'idwiz_favorite_templates', $favorites );
				}
			}

			$doAction       = true;
			$actionResponse = $post_id;
			break;

		case 'restore':
			if ( ! is_wp_error( $trashTerm ) ) {
				$restoreTrashedTemplate = id_restore_template( $post_id );
				if ( $restoreTrashedTemplate === true ) {
					wp_send_json(
						array(
							'success'        => false,
							'actionResponse' => $restoreTrashedTemplate,
						)
					);
					die();
				}
			} else {
				wp_send_json(
					array(
						'success'        => false,
						'actionResponse' => $trashTerm->get_error_message(),
					)
				);
				die();
			}
			wp_remove_object_terms( $post_id, $trashTerm, 'idemailwiz_folder' );
			$doAction       = true;
			$actionResponse = $post_id;
			break;

		case 'duplicate':
			$duplicateTemplate = duplicate_wiz_template( $post_id );
			if ( isset( $duplicateTemplate['success'] ) && $duplicateTemplate['success'] == true ) {
				$doAction = true;
			}
			break;
	}

	wp_send_json(
		array(
			'success'        => $doAction,
			'actionResponse' => $actionResponse,
		)
	);
}
add_action( 'wp_ajax_id_ajax_template_actions', 'id_ajax_template_actions' );

/**
 * Move one or more templates into a destination folder.
 */
function id_move_template() {
	check_ajax_referer( 'template-actions', 'security' );

	$thisTemplate = $_POST['this_template'];

	foreach ( $thisTemplate as $template ) {
		$moveInto   = (int) $_POST['move_into'];
		$setfolders = wp_set_post_terms( $template, array( $moveInto ), 'idemailwiz_folder' );
	}

	$newfolderLink = get_term_link( $moveInto, 'idemailwiz_folder' );

	wp_send_json_success(
		array(
			'moveTemplate'  => $setfolders,
			'newFolderLink' => $newfolderLink,
		)
	);
	wp_die();
}
add_action( 'wp_ajax_id_move_template', 'id_move_template' );

/**
 * Create a brand-new empty template from a title.
 */
add_action( 'wp_ajax_create_new_wiz_template', 'create_new_wiz_template' );
function create_new_wiz_template() {
	global $wpdb;

	if ( ! wp_verify_nonce( $_POST['security'], 'template-actions' ) ) {
		wp_send_json_error( 'Nonce verification failed' );
		die();
	}

	if ( empty( $_POST['template_title'] ) ) {
		wp_send_json_error( 'Template title is required' );
		die();
	}
	$template_title = sanitize_text_field( $_POST['template_title'] );
	$template_type  = ( $_POST['template_type'] ?? '' ) === 'sms' ? 'sms' : 'email';

	$post_data = array(
		'post_title'  => $template_title,
		'post_status' => 'publish',
		'post_type'   => 'idemailwiz_template',
		'post_author' => get_current_user_id(),
	);

	$post_id = wp_insert_post( $post_data );

	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( $post_id->get_error_message() );
		die();
	}

	$options         = get_option( 'idemailwiz_settings' );
	$folderRootTerm  = $options['folder_base'] ?? '';
	if ( ! empty( $folderRootTerm ) ) {
		wp_set_post_terms( $post_id, array( $folderRootTerm ), 'idemailwiz_folder' );
	}

	$createDBrow = create_wiz_template_db_row( $post_id, $template_type );

	if ( $post_id && $createDBrow ) {
		$new_url = get_permalink( $post_id );
		wp_send_json_success( array( 'newURL' => $new_url ) );
	} else {
		wp_send_json_error( array( 'message' => 'Failed to create new template' ) );
	}
	die();
}

/**
 * Create the matching row in wp_wiz_templates for a freshly-created template.
 */
function create_wiz_template_db_row( $post_id, $template_type = 'email' ) {
	global $wpdb;

	$template_data = array( 'template_options' => array() );
	if ( $template_type === 'sms' ) {
		$template_data['template_type'] = 'sms';
		$template_data['sms']           = array( 'message' => '', 'image_url' => '' );
	}

	$success = $wpdb->insert(
		$wpdb->prefix . 'wiz_templates',
		array(
			'last_updated'  => current_time( 'mysql' ),
			'post_id'       => $post_id,
			'user_id'       => get_current_user_id(),
			'template_data' => json_encode( $template_data ),
		),
		array( '%s', '%d', '%d', '%s' )
	);

	return (bool) $success;
}

/**
 * Duplicate an existing template (post + terms + wp_wiz_templates row).
 *
 * This intentionally ends with wp_send_json_success() rather than returning,
 * because it is only ever called from the 'duplicate' case of
 * id_ajax_template_actions() and the client expects a response.
 */
function duplicate_wiz_template( $post_id, $returnPHP = false ) {
	global $wpdb;

	$post              = get_post( $post_id );
	$wizTemplateObject = get_wiztemplate_object( $post_id );

	$duplicate = array(
		'post_title'   => '(copy) ' . $post->post_title,
		'post_content' => $post->post_content,
		'post_status'  => 'publish',
		'post_type'    => 'idemailwiz_template',
		'post_author'  => $post->post_author,
	);
	$dupedID = wp_insert_post( $duplicate );

	if ( is_wp_error( $dupedID ) ) {
		return $dupedID->get_error_message();
	}

	$folders = wp_get_object_terms( $post_id, 'idemailwiz_folder' );
	if ( is_wp_error( $folders ) ) {
		return $folders->get_error_message();
	}

	$folderIDs = empty( $folders ) ? array() : wp_list_pluck( $folders, 'term_id' );
	if ( empty( $folderIDs ) ) {
		$options   = get_option( 'idemailwiz_settings' );
		$folderIDs = array( (int) $options['folder_base'] );
	}
	$setTemplateTerms = wp_set_object_terms( $dupedID, $folderIDs, 'idemailwiz_folder' );
	if ( is_wp_error( $setTemplateTerms ) ) {
		return $setTemplateTerms->get_error_message();
	}

	$wizTemplateRow = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wiz_templates WHERE post_id = %d",
			$post_id
		),
		ARRAY_A
	);

	if ( $wizTemplateRow ) {
		$templateData = json_decode( $wizTemplateRow['template_data'], true );

		// Strip per-template sync state so the copy doesn't try to re-sync
		// itself against the source's Iterable template id.
		unset( $templateData['template_options']['template_settings']['iterable-sync']['iterable_template_id'] );
		unset( $templateData['template_options']['template_settings']['iterable-sync']['synced_templates_history'] );
		$wizTemplateRow['template_data'] = json_encode( $templateData );
		$wizTemplateRow['post_id']       = $dupedID;
		unset( $wizTemplateRow['id'] );
		$wizTemplateRow['last_updated'] = current_time( 'mysql' );

		$inserted = $wpdb->insert( "{$wpdb->prefix}wiz_templates", $wizTemplateRow );

		if ( ! $inserted ) {
			return 'Failed to duplicate the template data in the custom database.';
		}
	} else {
		return 'Original template data not found in the custom database.';
	}

	wp_send_json_success(
		array(
			'success'     => true,
			'newTemplate' => $dupedID,
			'newURL'      => get_the_permalink( $dupedID ),
		)
	);
}
