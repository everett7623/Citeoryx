<?php
/**
 * Link HTTP status checker.
 *
 * @package Citeoryx\Application\Scan
 */

namespace Citeoryx\Application\Scan;

use Citeoryx\Domain\Link\LinkRepository;
use Citeoryx\Infrastructure\Http\HttpClient;

/**
 * Checks external links and records their HTTP status.
 */
class LinkChecker {

	private LinkRepository $link_repo;
	private HttpClient $http;

	public function __construct( LinkRepository $link_repo, HttpClient $http ) {
		$this->link_repo = $link_repo;
		$this->http      = $http;
	}

	/**
	 * Check a batch of external links.
	 *
	 * @param int $limit Number of links to check.
	 * @param int $after_id Exclusive link ID cursor.
	 * @return array{checked: int, broken: int, last_id: int}
	 */
	public function check_batch( int $limit = 50, int $after_id = 0 ): array {
		$links   = $this->link_repo->get_for_status_check( $limit, $after_id );
		$checked = 0;
		$broken  = 0;
		$last_id = $after_id;

		foreach ( $links as $link ) {
			$last_id = max( $last_id, (int) $link->id );
			if ( $link->is_internal ) {
				continue;
			}

			$response = $this->http->head( $link->target_url, array( 'timeout' => 15 ) );

			if ( ! $response['success'] ) {
				// Some hosts reject HEAD while serving the same URL over GET.
				$response = $this->http->get( $link->target_url, array( 'timeout' => 15 ) );
			}

			$status = $response['code'] ?: 0;
			$error  = $response['error'] ?? '';

			$this->link_repo->update_status( $link->id, $status, $error );
			++$checked;

			if ( $status >= 400 || 0 === $status ) {
				++$broken;
			}

			// Be polite to external hosts.
			usleep( 200000 ); // 0.2s
		}

		return array(
			'checked' => $checked,
			'broken'  => $broken,
			'last_id' => $last_id,
		);
	}
}
