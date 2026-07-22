<?php
/**
 * Search performance importer.
 *
 * @package Citeoryx\Application\Search
 */

namespace Citeoryx\Application\Search;

use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Metrics\MetricsRepository;
use Citeoryx\Integrations\SearchConsole\BingWebmasterTools;
use Citeoryx\Integrations\SearchConsole\GoogleSearchConsole;
use Citeoryx\Integrations\SearchConsole\SearchConsoleInterface;

/**
 * Imports bounded per-content search performance snapshots.
 */
class SearchPerformanceImporter {

	/**
	 * Content repository.
	 *
	 * @var ContentRepository
	 */
	private ContentRepository $content_repo;
	/**
	 * Metrics repository.
	 *
	 * @var MetricsRepository
	 */
	private MetricsRepository $metrics_repo;
	/**
	 * Google provider.
	 *
	 * @var GoogleSearchConsole
	 */
	private GoogleSearchConsole $google;
	/**
	 * Bing provider.
	 *
	 * @var BingWebmasterTools
	 */
	private BingWebmasterTools $bing;
	/**
	 * Provider health store.
	 *
	 * @var SearchIntegrationHealth
	 */
	private SearchIntegrationHealth $health;

	/**
	 * Constructor.
	 *
	 * @param ContentRepository       $content_repo Content repository.
	 * @param MetricsRepository       $metrics_repo Metrics repository.
	 * @param GoogleSearchConsole     $google Google provider.
	 * @param BingWebmasterTools      $bing Bing provider.
	 * @param SearchIntegrationHealth $health Provider health store.
	 */
	public function __construct(
		ContentRepository $content_repo,
		MetricsRepository $metrics_repo,
		GoogleSearchConsole $google,
		BingWebmasterTools $bing,
		SearchIntegrationHealth $health
	) {
		$this->content_repo = $content_repo;
		$this->metrics_repo = $metrics_repo;
		$this->google       = $google;
		$this->bing         = $bing;
		$this->health       = $health;
	}

	/**
	 * Import one immutable-ID content batch.
	 *
	 * @param int         $after_id Exclusive content ID cursor.
	 * @param int         $limit Batch size.
	 * @param string|null $date Google finalized data date.
	 * @return array<string, mixed>
	 */
	public function import_batch( int $after_id = 0, int $limit = 20, ?string $date = null ): array {
		if ( null === $date ) {
			$date = gmdate( 'Y-m-d', strtotime( '-3 days' ) );
		}
		$items             = $this->content_repo->list_after_id( $after_id, $limit );
		$results           = array(
			'processed'      => 0,
			'imported'       => 0,
			'dimension_rows' => 0,
			'last_id'        => $after_id,
			'complete'       => count( $items ) < $limit,
		);
		$provider_failures = array();
		$provider_success  = array();
		$providers         = $this->get_providers();

		foreach ( $items as $item ) {
			++$results['processed'];
			$results['last_id'] = (int) $item->id;
			foreach ( $providers as $source => $provider ) {
				if ( isset( $provider_failures[ $source ] ) ) {
					continue;
				}

				$page_snapshots = $this->fetch_page_metrics( $provider, $item->canonical_url, $date );
				if ( $provider->get_last_error() ) {
					$provider_failures[ $source ] = $provider->get_last_error();
				} elseif ( ! isset( $provider_failures[ $source ] ) ) {
					$provider_success[ $source ] = true;
				}
				if ( empty( $page_snapshots ) ) {
					continue;
				}
				foreach ( $page_snapshots as $snapshot_date => $page_data ) {
					$this->metrics_repo->save( (int) $item->id, $snapshot_date, $source, $page_data['metrics'] );
					$results['dimension_rows'] += $this->metrics_repo->save_query_pages(
						(int) $item->id,
						$source,
						$snapshot_date,
						$snapshot_date,
						$page_data['rows']
					);
					++$results['imported'];
				}
			}
		}

		foreach ( $provider_failures as $source => $message ) {
			$this->health->record_failure( $source, (string) $message );
		}
		foreach ( array_keys( $provider_success ) as $source ) {
			if ( ! isset( $provider_failures[ $source ] ) ) {
				$this->health->record_success( $source );
			}
		}
		$results['health'] = $this->health->all();

		return $results;
	}

	/**
	 * Return connected search providers keyed by storage source.
	 *
	 * @return array<string, SearchConsoleInterface>
	 */
	private function get_providers(): array {
		$providers = array();
		if ( $this->google->is_connected() ) {
			$providers['google_search_console'] = $this->google;
		}
		if ( $this->bing->is_connected() ) {
			$providers['bing_webmaster_tools'] = $this->bing;
		}
		return $providers;
	}

	/**
	 * Fetch and group one page's provider rows by their real metric date.
	 *
	 * @param SearchConsoleInterface $provider Provider.
	 * @param string                 $url Canonical URL.
	 * @param string                 $date Fallback metric date.
	 * @return array<string, array<string, mixed>>
	 */
	private function fetch_page_metrics( SearchConsoleInterface $provider, string $url, string $date ): array {

		$rows = $provider->get_queries_for_url(
			$url,
			$date,
			$date,
			array(
				'dimensions' => array( 'query', 'country', 'device' ),
				'row_limit'  => 100,
			)
		);
		if ( empty( $rows ) ) {
			return array();
		}

		$grouped = array();
		foreach ( $rows as $row ) {
			$row_date = (string) ( $row['metric_date'] ?? $date );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $row_date ) ) {
				$row_date = $date;
			}
			$grouped[ $row_date ][] = $row;
		}

		$snapshots = array();
		foreach ( $grouped as $row_date => $date_rows ) {
			$snapshots[ $row_date ] = array(
				'metrics' => $this->summarize_rows( $date_rows ),
				'rows'    => $date_rows,
			);
		}
		return $snapshots;
	}

	/**
	 * Aggregate provider rows into one dated content snapshot.
	 *
	 * @param array<int, array<string, mixed>> $rows Provider rows.
	 * @return array<string, float>
	 */
	private function summarize_rows( array $rows ): array {

		$impressions = 0.0;
		$clicks      = 0.0;
		$positions   = 0.0;
		foreach ( $rows as $row ) {
			$impressions += (float) ( $row['impressions'] ?? 0 );
			$clicks      += (float) ( $row['clicks'] ?? 0 );
			$positions   += (float) ( $row['position'] ?? 0 ) * (float) ( $row['impressions'] ?? 0 );
		}

		return array(
			'impressions'  => $impressions,
			'clicks'       => $clicks,
			'ctr'          => $impressions > 0 ? $clicks / $impressions : 0.0,
			'position_avg' => $impressions > 0 ? $positions / $impressions : 0.0,
		);
	}
}
