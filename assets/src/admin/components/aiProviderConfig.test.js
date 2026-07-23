import { canTestSavedProvider } from './aiProviderConfig';

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
