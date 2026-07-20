<?php
/**
 * Plugin constants.
 *
 * @package Citeoryx\Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CITEORYX_DB_VERSION' ) ) {
	define( 'CITEORYX_DB_VERSION', '2026071401' );
}

if ( ! defined( 'CITEORYX_REST_NAMESPACE' ) ) {
	define( 'CITEORYX_REST_NAMESPACE', 'citeoryx/v1' );
}

if ( ! defined( 'CITEORYX_TABLE_CONTENT_ITEMS' ) ) {
	define( 'CITEORYX_TABLE_CONTENT_ITEMS', 'cx_content_items' );
}
if ( ! defined( 'CITEORYX_TABLE_METRICS_DAILY' ) ) {
	define( 'CITEORYX_TABLE_METRICS_DAILY', 'cx_metrics_daily' );
}
if ( ! defined( 'CITEORYX_TABLE_QUERY_PAGES' ) ) {
	define( 'CITEORYX_TABLE_QUERY_PAGES', 'cx_query_pages' );
}
if ( ! defined( 'CITEORYX_TABLE_ISSUES' ) ) {
	define( 'CITEORYX_TABLE_ISSUES', 'cx_issues' );
}
if ( ! defined( 'CITEORYX_TABLE_LINKS' ) ) {
	define( 'CITEORYX_TABLE_LINKS', 'cx_links' );
}
if ( ! defined( 'CITEORYX_TABLE_SCAN_RUNS' ) ) {
	define( 'CITEORYX_TABLE_SCAN_RUNS', 'cx_scan_runs' );
}
if ( ! defined( 'CITEORYX_TABLE_AI_PROMPT_RUNS' ) ) {
	define( 'CITEORYX_TABLE_AI_PROMPT_RUNS', 'cx_ai_prompt_runs' );
}

if ( ! defined( 'CITEORYX_STATUS_UNKNOWN' ) ) {
	define( 'CITEORYX_STATUS_UNKNOWN', 'unknown' );
}
if ( ! defined( 'CITEORYX_STATUS_HEALTHY' ) ) {
	define( 'CITEORYX_STATUS_HEALTHY', 'healthy' );
}
if ( ! defined( 'CITEORYX_STATUS_GROWING' ) ) {
	define( 'CITEORYX_STATUS_GROWING', 'growing' );
}
if ( ! defined( 'CITEORYX_STATUS_OPPORTUNITY' ) ) {
	define( 'CITEORYX_STATUS_OPPORTUNITY', 'opportunity' );
}
if ( ! defined( 'CITEORYX_STATUS_DECAYING' ) ) {
	define( 'CITEORYX_STATUS_DECAYING', 'decaying' );
}
if ( ! defined( 'CITEORYX_STATUS_STALE' ) ) {
	define( 'CITEORYX_STATUS_STALE', 'stale' );
}
if ( ! defined( 'CITEORYX_STATUS_COMPETING' ) ) {
	define( 'CITEORYX_STATUS_COMPETING', 'competing' );
}
if ( ! defined( 'CITEORYX_STATUS_ORPHANED' ) ) {
	define( 'CITEORYX_STATUS_ORPHANED', 'orphaned' );
}
if ( ! defined( 'CITEORYX_STATUS_BROKEN' ) ) {
	define( 'CITEORYX_STATUS_BROKEN', 'broken' );
}
if ( ! defined( 'CITEORYX_STATUS_NEEDS_REVIEW' ) ) {
	define( 'CITEORYX_STATUS_NEEDS_REVIEW', 'needs_review' );
}
if ( ! defined( 'CITEORYX_STATUS_ARCHIVED' ) ) {
	define( 'CITEORYX_STATUS_ARCHIVED', 'archived' );
}
