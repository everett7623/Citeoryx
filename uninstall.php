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

citeoryx_remove_capabilities();

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
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
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
		'citeoryx_activated_at',
		'citeoryx_site_profile',
		'citeoryx_installed_seo_plugin',
		'citeoryx_settings',
		'citeoryx_remove_data_on_uninstall',
		'citeoryx_gsc_client',
		'citeoryx_ai_provider',
		'citeoryx_last_change_detection',
		'citeoryx_last_incremental_scan',
		'citeoryx_last_weekly_digest_period',
		'citeoryx_notification_status',
		'citeoryx_search_integration_health',
		'citeoryx_capabilities_version',
		'citeoryx_key_gsc_tokens',
		'citeoryx_key_gsc_client_secret',
		'citeoryx_key_openai_api_key',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	foreach ( array( 'citeoryx_activation_redirect', 'citeoryx_gsc_connection_result', 'citeoryx_gsc_oauth_state' ) as $transient ) {
		delete_transient( $transient );
	}
}

/**
 * Remove capabilities added by the plugin from built-in roles.
 *
 * @return void
 */
function citeoryx_remove_capabilities(): void {
	$capabilities = array(
		'citeoryx_view_dashboard',
		'citeoryx_view_content',
		'citeoryx_run_scans',
		'citeoryx_manage_issues',
		'citeoryx_use_ai',
		'citeoryx_apply_changes',
		'citeoryx_manage_integrations',
		'citeoryx_manage_settings',
		'citeoryx_export_data',
	);

	foreach ( array( 'administrator', 'editor', 'author', 'contributor' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( ! $role ) {
			continue;
		}
		foreach ( $capabilities as $capability ) {
			$role->remove_cap( $capability );
		}
	}
}
