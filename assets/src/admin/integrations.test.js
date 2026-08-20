import { createRoot } from '@wordpress/element';
import { act } from 'react';
import apiFetch from '@wordpress/api-fetch';
import Integrations from './components/Integrations';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '@wordpress/components', () => ( {
	Button: ( { children, isBusy, ...props } ) => (
		<button type="button" { ...props }>
			{ children }
		</button>
	),
	Card: ( { children } ) => children,
	CardBody: ( { children } ) => children,
	CardHeader: ( { children } ) => children,
	Notice: ( { children } ) => <div>{ children }</div>,
	Spinner: () => <span>Loading</span>,
	SelectControl: ( { label, onChange, options, value } ) => (
		<select
			aria-label={ label }
			value={ value }
			onChange={ ( event ) => onChange( event.target.value ) }
		>
			{ options.map( ( option ) => (
				<option key={ option.value } value={ option.value }>
					{ option.label }
				</option>
			) ) }
		</select>
	),
	TextControl: ( { label, onChange, type, value } ) => (
		<input
			aria-label={ label }
			type={ type }
			value={ value }
			onChange={ ( event ) => onChange( event.target.value ) }
		/>
	),
	ToggleControl: ( { checked, disabled, label, onChange } ) => (
		<div>
			{ label }
			<input
				type="checkbox"
				checked={ checked }
				disabled={ disabled }
				onChange={ ( event ) => onChange( event.target.checked ) }
			/>
		</div>
	),
} ) );

const deferred = () => {
	let resolve;
	const promise = new Promise( ( promiseResolve ) => {
		resolve = promiseResolve;
	} );
	return { promise, resolve };
};

const findButtons = ( container, label ) =>
	Array.from( container.querySelectorAll( 'button' ) ).filter(
		( button ) => button.textContent === label
	);

const setInputValue = ( input, value ) => {
	const valueSetter = Object.getOwnPropertyDescriptor(
		window.HTMLInputElement.prototype,
		'value'
	).set;
	valueSetter.call( input, value );
	input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
};

describe( 'Search integration actions', () => {
	let container;
	let root;

	beforeAll( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
	} );

	afterAll( () => {
		delete global.IS_REACT_ACT_ENVIRONMENT;
	} );

	beforeEach( () => {
		container = document.createElement( 'div' );
		root = createRoot( container );
	} );

	afterEach( () => {
		act( () => root.unmount() );
		jest.clearAllMocks();
	} );

	it( 'blocks disconnect actions while a connection validation is active', async () => {
		const validation = deferred();
		apiFetch.mockImplementation( ( options ) => {
			switch ( options.path ) {
				case 'citeoryx/v1/integrations/gsc':
					return Promise.resolve( {
						data: {
							connected: true,
							has_credentials: true,
							health: { status: 'unknown' },
							redirect_uri: 'https://example.com/callback',
						},
					} );
				case 'citeoryx/v1/integrations/bing':
					return Promise.resolve( {
						data: {
							connected: true,
							health: { status: 'unknown' },
						},
					} );
				case 'citeoryx/v1/integrations/ai':
					return Promise.resolve( { data: {} } );
				case 'citeoryx/v1/integrations/gsc/validate':
					return validation.promise;
				default:
					return Promise.reject(
						new Error( `Unexpected path: ${ options.path }` )
					);
			}
		} );

		await act( async () => {
			root.render( <Integrations /> );
		} );

		const validateButtons = findButtons( container, '验证连接' );
		const disconnectButtons = findButtons( container, '断开连接' );
		expect( validateButtons ).toHaveLength( 2 );
		expect( disconnectButtons ).toHaveLength( 2 );

		act( () => {
			validateButtons[ 0 ].dispatchEvent(
				new window.MouseEvent( 'click', { bubbles: true } )
			);
		} );
		expect( disconnectButtons.every( ( button ) => button.disabled ) ).toBe(
			true
		);

		await act( async () => {
			validation.resolve( {
				data: {
					valid: true,
					message: 'Google connection healthy',
					health: { status: 'healthy', message: 'Healthy' },
				},
			} );
		} );
		expect(
			disconnectButtons.every( ( button ) => ! button.disabled )
		).toBe( true );
	} );

	it( 'preserves unsaved AI fields while a search status refresh completes', async () => {
		const refreshedGsc = deferred();
		const refreshedBing = deferred();
		let gscRequests = 0;
		let bingRequests = 0;
		let aiRequests = 0;
		apiFetch.mockImplementation( ( options ) => {
			switch ( options.path ) {
				case 'citeoryx/v1/integrations/gsc':
					++gscRequests;
					return gscRequests === 1
						? Promise.resolve( {
								data: {
									connected: false,
									has_credentials: false,
									redirect_uri:
										'https://example.com/callback',
								},
						  } )
						: refreshedGsc.promise;
				case 'citeoryx/v1/integrations/bing':
					++bingRequests;
					return bingRequests === 1
						? Promise.resolve( {
								data: {
									connected: true,
									health: { status: 'unknown' },
								},
						  } )
						: refreshedBing.promise;
				case 'citeoryx/v1/integrations/ai':
					++aiRequests;
					return Promise.resolve( {
						data: {
							provider: 'openai',
							enabled: true,
							timeout: 60,
							has_openai_key: true,
							provider_settings: {
								openai: {
									model: 'gpt-4o-mini',
									base_url: '',
								},
							},
						},
					} );
				case 'citeoryx/v1/integrations/bing/disconnect':
					return Promise.resolve( { data: { disconnected: true } } );
				default:
					return Promise.reject(
						new Error( `Unexpected path: ${ options.path }` )
					);
			}
		} );

		await act( async () => {
			root.render( <Integrations /> );
		} );
		const apiKeyInput = container.querySelector(
			'input[aria-label="OpenAI API Key"]'
		);
		act( () => setInputValue( apiKeyInput, 'unsaved-secret' ) );

		await act( async () => {
			findButtons( container, '断开连接' )[ 0 ].click();
			await Promise.resolve();
		} );
		expect(
			container.querySelector( 'input[aria-label="OpenAI API Key"]' )
				.value
		).toBe( 'unsaved-secret' );
		expect( findButtons( container, '断开连接' )[ 0 ].disabled ).toBe(
			true
		);

		await act( async () => {
			refreshedGsc.resolve( {
				data: {
					connected: false,
					has_credentials: false,
					redirect_uri: 'https://example.com/callback',
				},
			} );
			refreshedBing.resolve( {
				data: { connected: false, health: { status: 'unknown' } },
			} );
		} );

		expect(
			container.querySelector( 'input[aria-label="OpenAI API Key"]' )
				.value
		).toBe( 'unsaved-secret' );
		expect( aiRequests ).toBe( 1 );
	} );
} );
