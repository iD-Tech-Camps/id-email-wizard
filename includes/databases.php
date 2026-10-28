<?php

// The builder's own table: one row per template post with its builder data and HTML.
// (The analytics tables the Wizard used to create and sync moved to the Email Analyzer app.)
function idemailwiz_create_databases()
{
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	$wizTemplateTableName = $wpdb->prefix . 'wiz_templates';
	$sql = "CREATE TABLE IF NOT EXISTS $wizTemplateTableName (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		post_id BIGINT UNSIGNED NOT NULL,
		user_id BIGINT UNSIGNED NOT NULL,
		template_data LONGTEXT NOT NULL,
		template_html LONGTEXT NOT NULL,
		PRIMARY KEY (id),
		FOREIGN KEY (post_id) REFERENCES {$wpdb->prefix}posts(ID) ON DELETE CASCADE,
		FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID)
	) ENGINE=InnoDB $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}
