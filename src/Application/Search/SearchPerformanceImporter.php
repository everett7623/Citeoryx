<?php

namespace Citeoryx\Application\Search;

use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Metrics\MetricsRepository;
use Citeoryx\Integrations\SearchConsole\BingWebmasterTools;
use Citeoryx\Integrations\SearchConsole\GoogleOAuth;
use Citeoryx\Integrations\SearchConsole\GoogleSearchConsole;
use Citeoryx\Integrations\SearchConsole\SearchConsoleInterface;

class SearchPerformanceImporter {

	private ContentRepository $content_repo;
	private MetricsRepository $metrics_repo;
	private GoogleSearchConsole $google;
	private BingWebmasterTools $bing;
	private SearchIntegrationHealth $health;

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

	public function import_batch( int $after_id = 0, int $limit = 20, ?string $date = null ): array {
		$date              = $date ?: gmdate( 'Y-m-d', strtotime( '-3 days' ) );
		$items             = $this->content_repo->list_after_id( $after_id, $limit );
		$results           = array(
			'processed' => 0,
			'imported'  => 0,
			'last_id'   => $after_id,
			'complete'  => count( $items ) < $limit,
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

				$metrics = $this->fetch_page_metrics( $provider, $item->canonical_url, $date );
				if ( $provider->get_last_error() ) {
					$provider_failures[ $source ] = $provider->get_last_error();
				} elseif ( ! isset( $provider_failures[ $source ] ) ) {
					$provider_success[ $source ] = true;
				}
				if ( null === $metrics ) {
					continue;
				}
				$this->metrics_repo->save( (int) $item->id, $date, $source, $metrics );
				++$results['imported'];
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

	private function fetch_page_metrics( SearchConsoleInterface $provider, string $url, string $date ): ?array {
		$rows = $provider->get_queries_for_url( $url, $date, $date );
		if ( empty( $rows ) ) {
			return null;
		}

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
