<?php
/**
 * Service container.
 *
 * @package Citeoryx\Core
 */

namespace Citeoryx\Core;

use Citeoryx\Infrastructure\Database\SchemaManager;
use Citeoryx\Infrastructure\Queue\Scheduler;
use Citeoryx\Integrations\SeoPlugins\SeoPluginAdapterFactory;
use Citeoryx\Application\Scan\ContentScanner;
use Citeoryx\Application\Analyze\IssueEngine;
use Citeoryx\Application\Analyze\HealthScorer;
use Citeoryx\Application\Analyze\AiReadinessScorer;
use Citeoryx\Application\Scan\LinkChecker;
use Citeoryx\Application\Optimize\Optimizer;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Issue\IssueRepository;
use Citeoryx\Domain\Link\LinkRepository;
use Citeoryx\Domain\Metrics\MetricsRepository;
use Citeoryx\Domain\Scan\ScanRunRepository;
use Citeoryx\Infrastructure\Cache\Transients;
use Citeoryx\Infrastructure\Http\HttpClient;
use Citeoryx\Infrastructure\Encryption\KeyStore;

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
		$this->parameters['plugin_dir']  = CITEORYX_PLUGIN_DIR;
		$this->parameters['plugin_url'] = CITEORYX_PLUGIN_URL;
		$this->parameters['version']     = CITEORYX_VERSION;
		$this->parameters['db_version']  = CITEORYX_DB_VERSION;
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
					$this->get( LinkChecker::class )
				);
			case SeoPluginAdapterFactory::class:
				return new SeoPluginAdapterFactory();
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
					$this->get( AiReadinessScorer::class )
				);
			case HealthScorer::class:
				return new HealthScorer();
			case AiReadinessScorer::class:
				return new AiReadinessScorer();
			case Optimizer::class:
				return new Optimizer(
					$this->get( ContentRepository::class ),
					$this->get( IssueRepository::class ),
					$this->get( HealthScorer::class ),
					$this->get( AiReadinessScorer::class )
				);
			case ContentRepository::class:
				return new ContentRepository();
			case IssueRepository::class:
				return new IssueRepository();
			case LinkRepository::class:
				return new LinkRepository();
			case MetricsRepository::class:
				return new MetricsRepository();
			case ScanRunRepository::class:
				return new ScanRunRepository();
			case Transients::class:
				return new Transients();
			case HttpClient::class:
				return new HttpClient();
			case KeyStore::class:
				return new KeyStore();
			case LinkChecker::class:
				return new LinkChecker(
					$this->get( LinkRepository::class ),
					$this->get( HttpClient::class )
				);
			default:
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
	public function setParameter( string $key, $value ): void {
		$this->parameters[ $key ] = $value;
	}
}


