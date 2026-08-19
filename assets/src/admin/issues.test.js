import { createRoot } from '@wordpress/element';
import { act } from 'react';
import apiFetch from '@wordpress/api-fetch';
import Issues from './components/Issues';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '@wordpress/icons', () => ( { warning: null } ) );
jest.mock( '@wordpress/components', () => ( {
	Button: ( { children, ...props } ) => (
		<button type="button" { ...props }>
			{ children }
		</button>
	),
	Card: ( { children } ) => children,
	CardBody: ( { children } ) => children,
	CardHeader: ( { children } ) => children,
	Notice: ( { children } ) => children,
	Placeholder: ( { label, instructions } ) => (
		<div>
			{ label } { instructions }
		</div>
	),
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
	Spinner: () => <span>Loading</span>,
} ) );

const issue = {
	id: 19,
	issue_code: 'CX_TEST_REQUEST_ORDER',
	title: 'Latest resolved issue',
	category: 'content',
	severity: 'high',
	priority_score: 88.5,
	status: 'resolved',
};

const deferred = () => {
	let resolve;
	const promise = new Promise( ( promiseResolve ) => {
		resolve = promiseResolve;
	} );
	return { promise, resolve };
};

describe( 'Issues request ordering', () => {
	let container;
	let root;

	beforeAll( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
	} );

	afterAll( () => {
		delete global.IS_REACT_ACT_ENVIRONMENT;
	} );

	beforeEach( () => {
		window.citeoryxAdmin = {
			user: { canExport: true, canManageIssues: true },
		};
		container = document.createElement( 'div' );
		root = createRoot( container );
	} );

	afterEach( () => {
		act( () => root.unmount() );
		delete window.citeoryxAdmin;
		jest.clearAllMocks();
	} );

	it( 'ignores an older list response after the status changes', async () => {
		const staleOpenResponse = deferred();
		const resolvedResponse = deferred();
		let openRequests = 0;

		apiFetch.mockImplementation( ( options ) => {
			if ( options.method === 'PATCH' ) {
				return Promise.resolve( { data: { ...issue } } );
			}
			if ( options.path.includes( 'status=open' ) ) {
				openRequests += 1;
				if ( openRequests === 1 ) {
					return Promise.resolve( {
						data: {
							items: [ { ...issue, status: 'open' } ],
							total: 1,
						},
					} );
				}
				return staleOpenResponse.promise;
			}
			return resolvedResponse.promise;
		} );

		await act( async () => {
			root.render( <Issues /> );
		} );
		expect( container.textContent ).toContain( 'CX_TEST_REQUEST_ORDER' );

		await act( async () => {
			const resolveButton = Array.from(
				container.querySelectorAll( 'button' )
			).find( ( button ) => button.textContent === '解决' );
			resolveButton.dispatchEvent(
				new window.MouseEvent( 'click', { bubbles: true } )
			);
		} );
		expect( openRequests ).toBe( 2 );

		act( () => {
			const select = container.querySelector( 'select' );
			select.value = 'resolved';
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		await act( async () => {
			resolvedResponse.resolve( {
				data: { items: [ issue ], total: 1 },
			} );
		} );
		expect( container.textContent ).toContain( 'CX_TEST_REQUEST_ORDER' );

		await act( async () => {
			staleOpenResponse.resolve( { data: { items: [], total: 0 } } );
		} );
		expect( container.textContent ).toContain( 'CX_TEST_REQUEST_ORDER' );
	} );
} );
