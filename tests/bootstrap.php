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
