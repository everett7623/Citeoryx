import { createRoot } from '@wordpress/element';
import { act } from 'react';
import apiFetch from '@wordpress/api-fetch';
import Dashboard from './components/Dashboard';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '@wordpress/icons', () => ( { postList: null } ) );
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
	Placeholder: ( { children, instructions, label } ) => (
		<div>
			{ label } { instructions } { children }
		</div>
	),
	Spinner: () => <span>Loading</span>,
} ) );

const deferred = () => {
	let resolve;
	const promise = new Promise( ( promiseResolve ) => {
		resolve = promiseResolve;
	} );
	return { promise, resolve };
};

const dashboardData = ( recentScans ) => ( {
	total_content: 1,
	status_counts: { healthy: 1 },
	high_priority: [],
	recent_scans: recentScans,
} );

describe( 'Dashboard scan polling', () => {
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

	it( 'keeps the scan failure reason after refreshing dashboard data', async () => {
		const initialDashboard = deferred();
		const failedScan = deferred();
		const refreshedDashboard = deferred();
		let dashboardRequests = 0;

		apiFetch.mockImplementation( ( options ) => {
			if ( options.path === 'citeoryx/v1/dashboard' ) {
				++dashboardRequests;
				return dashboardRequests === 1
					? initialDashboard.promise
					: refreshedDashboard.promise;
			}
			if ( options.path === 'citeoryx/v1/scans/9' ) {
				return failedScan.promise;
			}
			return Promise.reject(
				new Error( `Unexpected path: ${ options.path }` )
			);
		} );

		await act( async () => {
			root.render( <Dashboard /> );
		} );
		await act( async () => {
			initialDashboard.resolve( {
				data: dashboardData( [
					{
						id: 9,
						status: 'running',
						processed_items: 2,
						total_items: 5,
					},
				] ),
			} );
		} );
		await act( async () => {
			failedScan.resolve( {
				data: {
					id: 9,
					status: 'failed',
					error_log: 'Deterministic scan failure',
				},
			} );
		} );

		expect( container.textContent ).toContain(
			'Deterministic scan failure'
		);
		expect( dashboardRequests ).toBe( 2 );

		await act( async () => {
			refreshedDashboard.resolve( {
				data: dashboardData( [ { id: 9, status: 'failed' } ] ),
			} );
		} );

		expect( container.textContent ).toContain(
			'Deterministic scan failure'
		);
	} );
} );
