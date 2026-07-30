<?php
/**
 * AI analysis REST contract tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Core\Container;
use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Infrastructure\Encryption\KeyStore;
use Citeoryx\Infrastructure\Queue\AiAnalysisQueue;
use Citeoryx\Integrations\AiProviders\AiProviderFactory;
use Citeoryx\Integrations\AiProviders\OpenAiCompatibleProvider;
use Citeoryx\Rest\Controllers\AiAnalysisController;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Verifies the trigger and polling response contract.
 */
class AiAnalysisControllerTest extends WP_UnitTestCase {

	private int $content_id = 0;
	private int $user_id    = 0;
	private string $task_id = '';

	public function setUp(): void {
		parent::setUp();
		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->user_id );

		OpenAiCompatibleProvider::save_api_key( 'queue-contract-key' );
		AiProviderFactory::save_provider_settings(
			'openai_compatible',
			'queue-contract-model',
			'https://example.com/v1/chat/completions'
		);
		update_option( AiProviderFactory::OPTION_PROVIDER, 'openai_compatible' );

		$item                = new ContentItem();
		$item->object_type   = 'post';
		$item->canonical_url = home_url( '/ai-analysis-contract' );
		$item->url_hash      = md5( $item->canonical_url );
		$this->content_id    = ( new ContentRepository() )->save( $item );
	}

	public function tearDown(): void {
		if ( $this->task_id ) {
			wp_clear_scheduled_hook( AiAnalysisQueue::HOOK, array( 'task_id' => $this->task_id ) );
			delete_transient( 'citeoryx_ai_analysis_task_' . $this->task_id );
		}
		delete_option( 'citeoryx_ai_analysis_lock_' . md5( $this->user_id . ':' . $this->content_id ) );
		delete_transient( 'citeoryx_ai_analysis_latest_' . md5( $this->user_id . ':' . $this->content_id ) );
		delete_option( AiProviderFactory::OPTION_PROVIDER );
		delete_option( AiProviderFactory::OPTION_SETTINGS );
		( new KeyStore() )->delete( OpenAiCompatibleProvider::KEY_NAME );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Trigger returns 202 and GET returns the same owner-scoped task.
	 *
	 * @return void
	 */
	public function test_trigger_and_status_use_async_contract(): void {
		$controller = new AiAnalysisController( new Container() );
		$trigger    = new WP_REST_Request( 'POST', '/citeoryx/v1/integrations/ai/analyze/' . $this->content_id );
		$trigger->set_param( 'id', $this->content_id );

		$response      = $controller->analyze_content( $trigger );
		$data          = $response->get_data()['data'];
		$this->task_id = (string) $data['task_id'];

		$this->assertSame( 202, $response->get_status() );
		$this->assertSame( 'queued', $data['status'] );
		$this->assertSame( $this->content_id, $data['content_id'] );
		$this->assertArrayNotHasKey( 'user_id', $data );

		$duplicate      = $controller->analyze_content( $trigger );
		$duplicate_data = $duplicate->get_data()['data'];
		$this->assertTrue( $duplicate_data['reused'] );
		$this->assertSame( $this->task_id, $duplicate_data['task_id'] );

		$status = new WP_REST_Request( 'GET', '/citeoryx/v1/integrations/ai/analyze/' . $this->content_id );
		$status->set_param( 'id', $this->content_id );
		$status->set_param( 'task_id', $this->task_id );
		$status_response = $controller->get_analysis( $status );

		$this->assertSame( 200, $status_response->get_status() );
		$this->assertSame( $this->task_id, $status_response->get_data()['data']['task_id'] );

		$status->set_param( 'task_id', '' );
		$latest = $controller->get_analysis( $status );
		$this->assertSame( $this->task_id, $latest->get_data()['data']['task_id'] );

		$status->set_param( 'task_id', wp_generate_uuid4() );
		$this->assertSame( 404, $controller->get_analysis( $status )->get_status() );
	}

	/**
	 * Optimizer availability exposes no provider settings or credentials.
	 *
	 * @return void
	 */
	public function test_availability_returns_minimal_provider_state(): void {
		$data = ( new AiAnalysisController( new Container() ) )->get_availability()->get_data()['data'];

		$this->assertSame( 'openai_compatible', $data['provider'] );
		$this->assertTrue( $data['configured'] );
		$this->assertArrayNotHasKey( 'settings', $data );
		$this->assertArrayNotHasKey( 'api_key', $data );
	}
}
