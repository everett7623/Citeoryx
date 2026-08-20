import { createRoot } from '@wordpress/element';
import { act } from 'react';
import apiFetch from '@wordpress/api-fetch';
import PlanningCalendar from './components/PlanningCalendar';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '@wordpress/components', () => ( {
	Button: ( { children, ...props } ) => (
		<button type="button" { ...props }>
			{ children }
		</button>
	),
	Card: ( { children } ) => children,
	CardBody: ( { children } ) => children,
	CardHeader: ( { children } ) => children,
	Notice: ( { children } ) => <div>{ children }</div>,
	Spinner: () => <span>Loading</span>,
} ) );

const deferred = () => {
	let resolve;
	const promise = new Promise( ( promiseResolve ) => {
		resolve = promiseResolve;
	} );
	return { promise, resolve };
};

const calendarData = {
	timezone: 'Asia/Shanghai',
	review_cycle_days: 90,
	scheduled: { items: [], data_limited: false },
	overdue_reviews: {
		data_limited: false,
		items: [
			{
				content_id: 1,
				title: 'First review',
				url: 'https://example.com/first',
				overdue_days: 4,
			},
			{
				content_id: 2,
				title: 'Second review',
				url: 'https://example.com/second',
				overdue_days: 2,
			},
		],
	},
};

const findButtons = ( container, label ) =>
	Array.from( container.querySelectorAll( 'button' ) ).filter(
		( button ) => button.textContent === label
	);

describe( 'Planning calendar review actions', () => {
	let container;
	let root;

	beforeAll( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
	} );

	afterAll( () => {
		delete global.IS_REACT_ACT_ENVIRONMENT;
	} );

	beforeEach( () => {
		window.citeoryxAdmin = { user: { canManageIssues: true } };
		container = document.createElement( 'div' );
		root = createRoot( container );
	} );

	afterEach( () => {
		act( () => root.unmount() );
		delete window.citeoryxAdmin;
		jest.clearAllMocks();
	} );

	it( 'locks all review and refresh actions until the write is refreshed', async () => {
		const completion = deferred();
		const refreshedCalendar = deferred();
		let calendarRequests = 0;

		apiFetch.mockImplementation( ( options ) => {
			if (
				options.path ===
				'citeoryx/v1/planning/calendar?horizon_days=90&limit=50'
			) {
				++calendarRequests;
				return calendarRequests === 1
					? Promise.resolve( { data: calendarData } )
					: refreshedCalendar.promise;
			}
			if ( options.path === 'citeoryx/v1/planning/reviews/1/complete' ) {
				return completion.promise;
			}
			return Promise.reject(
				new Error( `Unexpected path: ${ options.path }` )
			);
		} );

		await act( async () => {
			root.render( <PlanningCalendar /> );
		} );

		const reviewButtons = findButtons( container, '标记已复核' );
		const refreshButton = findButtons(
			container,
			'刷新发布与复核计划'
		)[ 0 ];
		expect( reviewButtons ).toHaveLength( 2 );

		act( () => {
			reviewButtons[ 0 ].dispatchEvent(
				new window.MouseEvent( 'click', { bubbles: true } )
			);
		} );

		expect( refreshButton.disabled ).toBe( true );
		expect(
			Array.from( container.querySelectorAll( 'button' ) )
				.filter( ( button ) =>
					[ '保存中…', '标记已复核' ].includes( button.textContent )
				)
				.every( ( button ) => button.disabled )
		).toBe( true );

		await act( async () => {
			completion.resolve( { data: { reviewed_at: '2026-08-20' } } );
		} );
		expect( calendarRequests ).toBe( 2 );
		expect( refreshButton.disabled ).toBe( true );

		await act( async () => {
			refreshedCalendar.resolve( { data: calendarData } );
		} );
		expect(
			findButtons( container, '标记已复核' ).every(
				( button ) => ! button.disabled
			)
		).toBe( true );
	} );
} );
