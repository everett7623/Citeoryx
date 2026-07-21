<?php
/**
 * PHPUnit bootstrap.
 *
 * @package Citeoryx\Tests
 */

// Path to WordPress tests bootstrap.
$wordpress_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $wordpress_tests_dir ) {
	$wordpress_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( "{$wordpress_tests_dir}/includes/functions.php" ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test bootstrap writes only to the CLI.
	echo "Could not find {$wordpress_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh?" . PHP_EOL;
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$wordpress_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 *
 * @return void
 */
function citeoryx_manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/citeoryx.php';
}

tests_add_filter( 'muplugins_loaded', 'citeoryx_manually_load_plugin' );

// Start up the WP testing environment.
require_once "{$wordpress_tests_dir}/includes/bootstrap.php";

// Activation hooks do not run in the WordPress test bootstrap.
( new \Citeoryx\Infrastructure\Database\SchemaManager() )->install();

global $wpdb;
foreach ( array( 'cx_ai_prompt_runs', 'cx_scan_runs', 'cx_links', 'cx_issues', 'cx_query_pages', 'cx_metrics_daily', 'cx_content_items' ) as $table ) {
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
