<?php
/**
 * Sidebar folder-tree renderers used by the taxonomy archive template.
 *
 * get_folder_list() renders the full sidebar: favorite folders, favorite
 * templates, the hierarchical folder tree, and the Base/Trash pinned links.
 *
 * get_folder_tree() recursively renders one branch of the tree.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function get_folder_list( $current_folder_id = null ) {
	$idwiz_favorite_folders   = get_user_meta( get_current_user_id(), 'idwiz_favorite_folders', true );
	$favorite_folder_cat_ids  = ! empty( $idwiz_favorite_folders ) ? $idwiz_favorite_folders : array();

	$idwiz_favorite_templates = get_user_meta( get_current_user_id(), 'idwiz_favorite_templates', true );
	$favorite_template_cat_ids = ! empty( $idwiz_favorite_templates ) ? $idwiz_favorite_templates : array();

	$faves_list = '';

	if ( ! empty( $idwiz_favorite_folders ) ) {
		$faves_list .= '<h5>Favorite Folders</h5>';
		$faves_list .= '<ul>';
		foreach ( $favorite_folder_cat_ids as $fav_folder_cat_id ) {
			$term = get_term( $fav_folder_cat_id, 'idemailwiz_folder' );
			if ( $term && ! is_wp_error( $term ) ) {
				$faves_list .= '<li class="favItem"><i class="row-icon fa-solid fa-folder"></i><a href="' . get_term_link( $term ) . '" title="' . esc_attr( $term->name ) . '">' . esc_html( $term->name ) . '</a><i title="Remove Favorite" class="fa-solid fa-circle-minus addRemoveFavorite" data-objecttype="Folder" data-objectid="' . $fav_folder_cat_id . '"></i></li>';
			}
		}
		$faves_list .= '</ul>';
	}
	if ( ! empty( $idwiz_favorite_templates ) ) {
		$faves_list .= '<h5>Favorite Templates</h5>';
		$faves_list .= '<ul>';
		foreach ( $favorite_template_cat_ids as $fav_template_id ) {
			$template = get_post( $fav_template_id );
			if ( $template && ! is_wp_error( $template ) ) {
				$fav_template_title = get_the_title( $fav_template_id );
				$fav_template_icon  = is_wiz_sms_template( $fav_template_id ) ? 'fa-comment-sms' : 'fa-file';
				$faves_list        .= '<li class="favItem"><i class="row-icon fa-solid ' . $fav_template_icon . '"></i><a href="' . get_the_permalink( $fav_template_id ) . '" title="' . esc_attr( $fav_template_title ) . '">' . esc_html( $fav_template_title ) . '</a><i title="Remove Favorite" class="fa-solid fa-circle-minus addRemoveFavorite" data-objecttype="Template" data-objectid="' . $fav_template_id . '"></i></li>';
			}
		}
		$faves_list .= '</ul>';
	}

	$args = array(
		'orderby'      => 'name',
		'hide_empty'   => false,
		'hierarchical' => false,
		'depth'        => 0,
		'child_of'     => 0,
		'taxonomy'     => 'idemailwiz_folder',
		'exclude'      => array(),
	);

	$options       = get_option( 'idemailwiz_settings' );
	$exclude_terms = array( 'folder_trash' );
	foreach ( $exclude_terms as $term ) {
		$term_value = (int) $options[ $term ];
		if ( ! is_wp_error( $term_value ) ) {
			$args['exclude'][] = $term_value;
		}
	}

	$folders    = get_terms( $args );
	$terms_list = '';
	if ( empty( $folders ) ) {
		return;
	}

	$terms_list .= '<ul>';
	foreach ( $folders as $folder ) {
		if ( $folder->parent == 0 ) {
			$terms_list .= get_folder_tree( $folder, $current_folder_id );
		}
	}
	$terms_list .= '</ul>';

	$folder_list  = $terms_list . $faves_list;
	$folder_list .= '<ul>';

	$options         = get_option( 'idemailwiz_settings' );
	$trashedFolderId = (int) $options['folder_trash'];
	$trashLink       = get_term_link( $trashedFolderId, 'idemailwiz_folder' );
	$templateRoot    = (int) $options['folder_base'];

	if ( ! is_wp_error( $trashLink ) ) {
		$folder_list .= '<li><i class="row-icon fa fa-trash"></i><a href="' . $trashLink . '">Trash</a></li>';
	} else {
		$folder_list .= '<li><em>Error! Trash term not detected.</em></li>';
	}

	if ( is_wp_error( $templateRoot ) ) {
		$folder_list .= '<li><em>No template root detected!</em></li>';
	}

	$folder_list .= '</ul>';

	echo $folder_list;
}

function get_folder_tree( $folder, $current_folder_id = null ) {
	$link                     = get_term_link( $folder, 'idemailwiz_folder' );
	$name                     = $folder->name;
	$current_class            = ( $current_folder_id == $folder->term_id ) ? ' current-cat' : '';
	$has_sub_cats_class       = '';
	$sub_cats_collapse_toggle = '';

	$sub_categories = get_terms(
		array(
			'taxonomy'   => 'idemailwiz_folder',
			'parent'     => $folder->term_id,
			'hide_empty' => false,
		)
	);
	if ( $sub_categories ) {
		$has_sub_cats_class       = ' has-sub-cats';
		$sub_cats_collapse_toggle = '<i class="fa-solid fa-angle-up showHideSubs"></i>';
	}

	$is_open_icon = ( $current_folder_id == $folder->term_id || is_parent( $folder, get_term( $current_folder_id, 'idemailwiz_folder' ) ) || $folder->term_id == 1 );
	$icon_html    = $is_open_icon
		? '<i class="row-icon fa-regular fa-folder-open"></i>'
		: '<i class="row-icon fa-solid fa-folder"></i>';

	$html  = '<li class="cat-item cat-item-' . $folder->term_id . $current_class . $has_sub_cats_class . '">';
	$html .= $icon_html;
	$html .= '<a href="' . $link . '" title="' . esc_attr( $folder->name ) . '">' . esc_html( $name ) . '</a>';
	$html .= $sub_cats_collapse_toggle;

	if ( $sub_categories ) {
		$html .= '<ul class="sub-categories">';
		foreach ( $sub_categories as $sub_category ) {
			$html .= get_folder_tree( $sub_category, $current_folder_id );
		}
		$html .= '</ul>';
	}

	$html .= '</li>';

	return $html;
}
