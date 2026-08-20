<?php
/**
 * Service container.
 *
 * @package Citeoryx\Core
 */

namespace Citeoryx\Core;

use Citeoryx\Infrastructure\Database\SchemaManager;
use Citeoryx\Infrastructure\Queue\AiAnalysisQueue;
use Citeoryx\Infrastructure\Queue\AiAnalysisTaskStore;
use Citeoryx\Infrastructure\Queue\Scheduler;
use Citeoryx\Integrations\SeoPlugins\SeoPluginAdapterFactory;
use Citeoryx\Application\Scan\ContentScanner;
use Citeoryx\Application\Analyze\IssueEngine;
use Citeoryx\Application\Analyze\HealthScorer;
use Citeoryx\Application\Analyze\AiContentAnalyzer;
use Citeoryx\Application\Analyze\AiReadinessScorer;
use Citeoryx\Application\Analyze\ContentStatusClassifier;
use Citeoryx\Application\Scan\LinkChecker;
use Citeoryx\Application\Optimize\Optimizer;
use Citeoryx\Application\Optimize\InternalLinkSuggester;
use Citeoryx\Application\Optimize\RevisionDraftService;
use Citeoryx\Application\Optimize\RevisionPerformanceMonitor;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Issue\IssueRepository;
use Citeoryx\Domain\Link\LinkRepository;
use Citeoryx\Domain\Metrics\MetricsRepository;
use Citeoryx\Domain\Planning\OpportunityRepository;
use Citeoryx\Domain\Planning\CalendarRepository;
use Citeoryx\Domain\Scan\ScanRunRepository;
use Citeoryx\Infrastructure\Cache\RestResponseCache;
use Citeoryx\Infrastructure\Cache\Transients;
use Citeoryx\Infrastructure\Http\HttpClient;
use Citeoryx\Infrastructure\Encryption\KeyStore;
use Citeoryx\Application\Notifications\WeeklyDigest;
use Citeoryx\Application\Notifications\CriticalIssueNotifier;
use Citeoryx\Application\Search\SearchPerformanceImporter;
use Citeoryx\Application\Search\SearchIntegrationHealth;
use Citeoryx\Application\Planning\TopicOpportunityFinder;
use Citeoryx\Application\Planning\PlanningCalendar;
use Citeoryx\Integrations\SearchConsole\BingWebmasterTools;
use Citeoryx\Integrations\SearchConsole\GoogleOAuth;
use Citeoryx\Integrations\SearchConsole\GoogleSearchConsole;
use Citeoryx\Integrations\AiProviders\AiProviderFactory;

/**
 * Lightweight service container.
 */
class Container {

	/**
	 * Service instances.
	 *
	 * @var array<string, object>
	 */
	private array $services = array();

	/**
	 * Parameter bag.
	 *
	 * @var array<string, mixed>
	 */
	private array $parameters = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->parameters['plugin_dir'] = CITEORYX_PLUGIN_DIR;
		$this->parameters['plugin_url'] = CITEORYX_PLUGIN_URL;
		$this->parameters['version']    = CITEORYX_VERSION;
		$this->parameters['db_version'] = CITEORYX_DB_VERSION;
	}

	/**
	 * Get a service by class name.
	 *
	 * @template T of object
	 * @param class-string<T> $class Service class name.
	 * @return T
	 */
	public function get( string $class ): object {
		if ( ! isset( $this->services[ $class ] ) ) {
			$this->services[ $class ] = $this->build( $class );
		}

		return $this->services[ $class ];
	}

	/**
	 * Build a service.
	 *
	 * @param string $class Service class name.
	 * @return object
	 */
	private function build( string $class ): object {
		switch ( $class ) {
			case SchemaManager::class:
				return new SchemaManager();
			case Scheduler::class:
				return new Scheduler(
					$this->get( ContentScanner::class ),
					$this->get( IssueEngine::class ),
					$this->get( LinkChecker::class ),
					$this->get( ScanRunRepository::class ),
					$this->get( SearchPerformanceImporter::class )
				);
			case AiAnalysisQueue::class:
				return new AiAnalysisQueue(
					$this->get( AiAnalysisTaskStore::class ),
					$this->get( AiContentAnalyzer::class )
				);
			case AiAnalysisTaskStore::class:
				return new AiAnalysisTaskStore( $this->get( Transients::class ) );
			case SeoPluginAdapterFactory::class:
				return new SeoPluginAdapterFactory();
			case AiProviderFactory::class:
				return new AiProviderFactory();
			case ContentScanner::class:
				return new ContentScanner(
					$this->get( ContentRepository::class ),
					$this->get( LinkRepository::class ),
					$this->get( SeoPluginAdapterFactory::class )
				);
			case IssueEngine::class:
				return new IssueEngine(
					$this->get( IssueRepository::class ),
					$this->get( ContentRepository::class ),
					$this->get( LinkRepository::class ),
					$this->get( HealthScorer::class ),
					$this->get( AiReadinessScorer::class ),
					$this->get( ContentStatusClassifier::class )
				);
			case HealthScorer::class:
				return new HealthScorer();
			case AiReadinessScorer::class:
				return new AiReadinessScorer();
			case AiContentAnalyzer::class:
				return new AiContentAnalyzer(
					$this->get( ContentRepository::class ),
					$this->get( IssueRepository::class ),
					$this->get( AiProviderFactory::class )
				);
			case ContentStatusClassifier::class:
				return new ContentStatusClassifier();
			case Optimizer::class:
				return new Optimizer(
					$this->get( ContentRepository::class ),
					$this->get( IssueRepository::class ),
					$this->get( HealthScorer::class ),
					$this->get( AiReadinessScorer::class ),
					$this->get( InternalLinkSuggester::class ),
					$this->get( RevisionPerformanceMonitor::class )
				);
			case InternalLinkSuggester::class:
				return new InternalLinkSuggester(
					$this->get( ContentRepository::class ),
					$this->get( LinkRepository::class )
				);
			case RevisionDraftService::class:
				return new RevisionDraftService( $this->get( ContentRepository::class ) );
			case RevisionPerformanceMonitor::class:
				return new RevisionPerformanceMonitor(
					$this->get( RevisionDraftService::class ),
					$this->get( MetricsRepository::class )
				);
			case ContentRepository::class:
				return new ContentRepository();
			case IssueRepository::class:
				return new IssueRepository();
			case LinkRepository::class:
				return new LinkRepository();
			case MetricsRepository::class:
				return new MetricsRepository();
			case OpportunityRepository::class:
				return new OpportunityRepository();
			case CalendarRepository::class:
				return new CalendarRepository();
			case TopicOpportunityFinder::class:
				return new TopicOpportunityFinder( $this->get( OpportunityRepository::class ) );
			case PlanningCalendar::class:
				return new PlanningCalendar(
					$this->get( CalendarRepository::class ),
					$this->get( ContentRepository::class )
				);
			case GoogleOAuth::class:
				return new GoogleOAuth();
			case GoogleSearchConsole::class:
				return new GoogleSearchConsole( $this->get( GoogleOAuth::class ) );
			case BingWebmasterTools::class:
				return new BingWebmasterTools();
			case SearchIntegrationHealth::class:
				return new SearchIntegrationHealth();
			case SearchPerformanceImporter::class:
				return new SearchPerformanceImporter(
					$this->get( ContentRepository::class ),
					$this->get( MetricsRepository::class ),
					$this->get( GoogleSearchConsole::class ),
					$this->get( BingWebmasterTools::class ),
					$this->get( SearchIntegrationHealth::class )
				);
			case ScanRunRepository::class:
				return new ScanRunRepository();
			case RestResponseCache::class:
				return new RestResponseCache( $this->get( Transients::class ) );
			case Transients::class:
				return new Transients();
			case HttpClient::class:
				return new HttpClient();
			case KeyStore::class:
				return new KeyStore();
			case WeeklyDigest::class:
				return new WeeklyDigest(
					$this->get( ContentRepository::class ),
					$this->get( IssueRepository::class )
				);
			case CriticalIssueNotifier::class:
				return new CriticalIssueNotifier( $this->get( IssueRepository::class ) );
			case LinkChecker::class:
				return new LinkChecker(
					$this->get( LinkRepository::class ),
					$this->get( HttpClient::class )
				);
			default:
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered directly.
				throw new \InvalidArgumentException( "Unknown service: {$class}" );
		}
	}

	/**
	 * Get a parameter.
	 *
	 * @param string $key Parameter key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Preserve the existing public API.
	public function getParameter( string $key, $default = null ) {
		return $this->parameters[ $key ] ?? $default;
	}

	/**
	 * Set a parameter.
	 *
	 * @param string $key Parameter key.
	 * @param mixed  $value Parameter value.
	 * @return void
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Preserve the existing public API.
	public function setParameter( string $key, $value ): void {
		$this->parameters[ $key ] = $value;
	}
}
