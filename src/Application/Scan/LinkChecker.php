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
	 * @param int $offset Offset.
	 * @return array{checked: int, broken: int}
	 */
	public function check_batch( int $limit = 50, int $offset = 0 ): array {
		$links  = $this->link_repo->get_for_status_check( $limit, $offset );
		$checked = 0;
		$broken  = 0;

		foreach ( $links as $link ) {
			if ( $link->is_internal ) {
				continue;
			}

			$response = $this->http->head( $link->target_url, array( 'timeout' => 15 ) );

			if ( ! $response['success'] && empty( $response['code'] ) ) {
				// HEAD may not be supported; fallback to GET.
				$response = $this->http->get( $link->target_url, array( 'timeout' => 15 ) );
			}

			$status = $response['code'] ?: 0;
			$error  = $response['error'] ?? '';

			$this->link_repo->update_status( $link->id, $status, $error );
			++$checked;

			if ( $status >= 400 || $status === 0 ) {
				++$broken;
			}

			// Be polite to external hosts.
			usleep( 200000 ); // 0.2s
		}

		return array( 'checked' => $checked, 'broken' => $broken );
	}
}
