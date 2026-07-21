<?php
/**
 * Privacy API support.
 *
 * @package Citeoryx\Support
 */

namespace Citeoryx\Support;

/**
 * WordPress privacy exporter / eraser integration.
 */
class Privacy {

	/**
	 * Register privacy hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Register personal data exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['citeoryx'] = array(
			'exporter_friendly_name' => __( 'Citeoryx Content Health Data', 'citeoryx' ),
			'callback'               => array( $this, 'export_data' ),
		);

		return $exporters;
	}

	/**
	 * Register personal data eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['citeoryx'] = array(
			'eraser_friendly_name' => __( 'Citeoryx Content Health Data', 'citeoryx' ),
			'callback'             => array( $this, 'erase_data' ),
		);

		return $erasers;
	}

	/**
	 * Export user-related data.
	 *
	 * @param string $email_address Email.
	 * @param int    $page Page.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress privacy callback signature.
	public function export_data( string $email_address, int $page = 1 ): array {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$data = array(
			array(
				'name'  => __( 'Assigned Issues', 'citeoryx' ),
				'value' => $this->count_assigned_issues( $user->ID ),
			),
		);

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erase user-related data.
	 *
	 * @param string $email_address Email.
	 * @param int    $page Page.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<string>, done: bool}
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress privacy callback signature.
	public function erase_data( string $email_address, int $page = 1 ): array {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		global $wpdb;
		$table = $wpdb->prefix . CITEORYX_TABLE_ISSUES;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array( 'assigned_user_id' => null ),
			array( 'assigned_user_id' => $user->ID ),
			array( '%d' ),
			array( '%d' )
		);

		return array(
			'items_removed'  => true,
			'items_retained' => false,
			'messages'       => array( __( 'Citeoryx issue assignments removed.', 'citeoryx' ) ),
			'done'           => true,
		);
	}

	/**
	 * Count assigned issues.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	private function count_assigned_issues( int $user_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . CITEORYX_TABLE_ISSUES;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE assigned_user_id = %d',
				$table,
				$user_id
			)
		);
	}
}
