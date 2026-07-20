<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Citeoryx
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'src/Core/constants.php';

$remove_data = get_option( 'citeoryx_remove_data_on_uninstall', false );

if ( $remove_data ) {
	citeoryx_drop_tables();
	citeoryx_delete_options();
}

/**
 * Drop plugin tables.
 *
 * @return void
 */
function citeoryx_drop_tables(): void {
	global $wpdb;

	$tables = array(
		'cx_content_items',
		'cx_metrics_daily',
		'cx_query_pages',
		'cx_issues',
		'cx_links',
		'cx_scan_runs',
		'cx_ai_prompt_runs',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

/**
 * Delete plugin options.
 *
 * @return void
 */
function citeoryx_delete_options(): void {
	$options = array(
		'citeoryx_version',
		'citeoryx_db_version',
		'citeoryx_site_profile',
		'citeoryx_installed_seo_plugin',
		'citeoryx_settings',
		'citeoryx_remove_data_on_uninstall',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}
