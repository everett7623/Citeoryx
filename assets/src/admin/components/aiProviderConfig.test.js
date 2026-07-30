import {
	canTestSavedProvider,
	getEndpointValue,
	isSub2ApiServiceRoot,
	isValidTimeout,
} from './aiProviderConfig';

describe( 'canTestSavedProvider', () => {
	it( 'enables testing for the active provider with a saved key', () => {
		const ai = {
			provider: 'openai_compatible',
			has_openai_compatible_key: true,
		};

		expect( canTestSavedProvider( ai, 'openai_compatible' ) ).toBe( true );
	} );

	it( 'disables testing when the active provider has no saved key', () => {
		const ai = {
			provider: 'openai_compatible',
			has_openai_compatible_key: false,
		};

		expect( canTestSavedProvider( ai, 'openai_compatible' ) ).toBe( false );
	} );

	it( 'does not test a provider that is not active', () => {
		const ai = {
			provider: 'anthropic',
			has_openai_compatible_key: true,
		};

		expect( canTestSavedProvider( ai, 'openai_compatible' ) ).toBe( false );
	} );
} );

describe( 'AI provider field helpers', () => {
	it( 'shows fixed official endpoints without changing compatible URLs', () => {
		expect( getEndpointValue( 'deepseek', '' ) ).toBe(
			'https://api.deepseek.com/chat/completions'
		);
		expect(
			getEndpointValue(
				'openai_compatible',
				'https://example.com/custom-endpoint'
			)
		).toBe( 'https://example.com/custom-endpoint' );
	} );

	it( 'accepts only integer timeouts between 10 and 180 seconds', () => {
		expect( isValidTimeout( '10' ) ).toBe( true );
		expect( isValidTimeout( '120' ) ).toBe( true );
		expect( isValidTimeout( '9' ) ).toBe( false );
		expect( isValidTimeout( '180.5' ) ).toBe( false );
		expect( isValidTimeout( '181' ) ).toBe( false );
	} );

	it( 'identifies a Sub2API root entered in the wrong protocol mode', () => {
		expect(
			isSub2ApiServiceRoot( 'openai_compatible', 'https://sub2.uukk.de/' )
		).toBe( true );
		expect(
			isSub2ApiServiceRoot( 'openai_responses', 'https://sub2.uukk.de/' )
		).toBe( false );
		expect(
			isSub2ApiServiceRoot(
				'openai_compatible',
				'https://example.com/v1/chat/completions'
			)
		).toBe( false );
	} );
} );
