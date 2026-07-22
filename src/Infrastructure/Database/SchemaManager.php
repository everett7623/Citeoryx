<?php
/**
 * Database schema manager.
 *
 * @package Citeoryx\Infrastructure\Database
 */

namespace Citeoryx\Infrastructure\Database;

/**
 * Manages plugin database tables.
 */
class SchemaManager {

	/**
	 * Install schema.
	 *
	 * @return void
	 */
	public function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		$sql_content_items = "CREATE TABLE IF NOT EXISTS {$prefix}cx_content_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			object_id BIGINT UNSIGNED NULL,
			object_type VARCHAR(50) NOT NULL,
			post_type VARCHAR(50) NULL,
			canonical_url VARCHAR(2048) NOT NULL,
			url_hash CHAR(32) NOT NULL,
			language_code VARCHAR(20) NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'unknown',
			health_score DECIMAL(5,2) NULL,
			health_confidence VARCHAR(20) NULL,
			ai_readiness_score DECIMAL(5,2) NULL,
			content_hash CHAR(64) NULL,
			published_at DATETIME NULL,
			modified_at DATETIME NULL,
			last_scanned_at DATETIME NULL,
			last_reviewed_at DATETIME NULL,
			assigned_user_id BIGINT UNSIGNED NULL,
			metadata_json LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_url_hash (url_hash),
			KEY idx_object (object_type, object_id),
			KEY idx_status (status),
			KEY idx_health (health_score),
			KEY idx_modified (modified_at),
			KEY idx_review_reference (last_reviewed_at, modified_at, published_at, created_at)
		) {$charset_collate};";

		$sql_metrics_daily = "CREATE TABLE IF NOT EXISTS {$prefix}cx_metrics_daily (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			content_id BIGINT UNSIGNED NOT NULL,
			metric_date DATE NOT NULL,
			source VARCHAR(30) NOT NULL,
			impressions DECIMAL(14,2) NULL,
			clicks DECIMAL(14,2) NULL,
			ctr DECIMAL(8,6) NULL,
			position_avg DECIMAL(8,3) NULL,
			sessions DECIMAL(14,2) NULL,
			conversions DECIMAL(14,2) NULL,
			revenue DECIMAL(14,2) NULL,
			extra_json LONGTEXT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_content_date_source (content_id, metric_date, source),
			KEY idx_metric_date (metric_date),
			KEY idx_content (content_id)
		) {$charset_collate};";

		$sql_query_pages = "CREATE TABLE IF NOT EXISTS {$prefix}cx_query_pages (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			content_id BIGINT UNSIGNED NOT NULL,
			source VARCHAR(30) NOT NULL DEFAULT 'unknown',
			query_text VARCHAR(500) NOT NULL,
			query_hash CHAR(32) NOT NULL,
			country_code VARCHAR(8) NULL,
			device VARCHAR(20) NULL,
			period_start DATE NOT NULL,
			period_end DATE NOT NULL,
			impressions DECIMAL(14,2) NULL,
			clicks DECIMAL(14,2) NULL,
			ctr DECIMAL(8,6) NULL,
			position_avg DECIMAL(8,3) NULL,
			intent VARCHAR(30) NULL,
			topic_id BIGINT UNSIGNED NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_query_dimension_period (content_id, source, query_hash, country_code, device, period_start, period_end),
			KEY idx_query_hash (query_hash),
			KEY idx_content_period (content_id, period_start, period_end),
			KEY idx_dimension_period (country_code, device, period_end),
			KEY idx_period_end (period_end),
			KEY idx_topic (topic_id)
		) {$charset_collate};";

		$sql_issues = "CREATE TABLE IF NOT EXISTS {$prefix}cx_issues (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			content_id BIGINT UNSIGNED NULL,
			issue_code VARCHAR(100) NOT NULL,
			category VARCHAR(50) NOT NULL,
			severity VARCHAR(20) NOT NULL,
			confidence VARCHAR(20) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			impact_score DECIMAL(5,2) NULL,
			effort_score DECIMAL(5,2) NULL,
			priority_score DECIMAL(8,3) NULL,
			title VARCHAR(500) NOT NULL,
			evidence_json LONGTEXT NULL,
			recommendation LONGTEXT NULL,
			first_seen_at DATETIME NOT NULL,
			last_seen_at DATETIME NOT NULL,
			resolved_at DATETIME NULL,
			ignored_until DATETIME NULL,
			assigned_user_id BIGINT UNSIGNED NULL,
			PRIMARY KEY (id),
			KEY idx_content_status (content_id, status),
			KEY idx_issue_code (issue_code),
			KEY idx_priority (priority_score),
			KEY idx_category (category),
			KEY idx_alert (status, severity, priority_score)
		) {$charset_collate};";

		$sql_links = "CREATE TABLE IF NOT EXISTS {$prefix}cx_links (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_content_id BIGINT UNSIGNED NOT NULL,
			target_content_id BIGINT UNSIGNED NULL,
			target_url VARCHAR(2048) NOT NULL,
			target_url_hash CHAR(32) NOT NULL,
			anchor_text VARCHAR(1000) NULL,
			link_context VARCHAR(50) NULL,
			rel_flags VARCHAR(255) NULL,
			http_status SMALLINT NULL,
			is_internal TINYINT(1) NOT NULL DEFAULT 0,
			first_seen_at DATETIME NOT NULL,
			last_seen_at DATETIME NOT NULL,
			last_checked_at DATETIME NULL,
			last_error VARCHAR(255) NULL,
			PRIMARY KEY (id),
			KEY idx_source (source_content_id),
			KEY idx_target_content (target_content_id),
			KEY idx_target_hash (target_url_hash),
			KEY idx_http_status (http_status)
		) {$charset_collate};";

		$sql_scan_runs = "CREATE TABLE IF NOT EXISTS {$prefix}cx_scan_runs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			scan_type VARCHAR(50) NOT NULL,
			status VARCHAR(20) NOT NULL,
			total_items INT UNSIGNED NOT NULL DEFAULT 0,
			processed_items INT UNSIGNED NOT NULL DEFAULT 0,
			failed_items INT UNSIGNED NOT NULL DEFAULT 0,
			trigger_type VARCHAR(30) NOT NULL,
			started_at DATETIME NULL,
			finished_at DATETIME NULL,
			config_json LONGTEXT NULL,
			summary_json LONGTEXT NULL,
			error_log LONGTEXT NULL,
			PRIMARY KEY (id),
			KEY idx_status (status),
			KEY idx_started (started_at)
		) {$charset_collate};";

		$sql_ai_prompt_runs = "CREATE TABLE IF NOT EXISTS {$prefix}cx_ai_prompt_runs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			prompt_set_id BIGINT UNSIGNED NOT NULL,
			provider VARCHAR(50) NOT NULL,
			model VARCHAR(100) NULL,
			prompt_hash CHAR(64) NOT NULL,
			region_code VARCHAR(20) NULL,
			language_code VARCHAR(20) NULL,
			mentioned TINYINT(1) NULL,
			cited TINYINT(1) NULL,
			citation_json LONGTEXT NULL,
			response_summary LONGTEXT NULL,
			response_hash CHAR(64) NULL,
			cost_amount DECIMAL(12,6) NULL,
			confidence VARCHAR(20) NULL,
			run_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_prompt_set (prompt_set_id),
			KEY idx_run_at (run_at),
			KEY idx_provider (provider)
		) {$charset_collate};";

		dbDelta( $sql_content_items );
		dbDelta( $sql_metrics_daily );
		dbDelta( $sql_query_pages );
		dbDelta( $sql_issues );
		dbDelta( $sql_links );
		dbDelta( $sql_scan_runs );
		dbDelta( $sql_ai_prompt_runs );
	}

	/**
	 * Upgrade schema if db version changed.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$current = get_option( 'citeoryx_db_version', '0' );
		if ( version_compare( $current, CITEORYX_DB_VERSION, '<' ) ) {
			$this->install();
			update_option( 'citeoryx_db_version', CITEORYX_DB_VERSION );
		}
	}
}
