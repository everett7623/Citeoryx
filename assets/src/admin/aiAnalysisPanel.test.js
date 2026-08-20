import { createRoot } from '@wordpress/element';
import { act } from 'react';
import apiFetch from '@wordpress/api-fetch';
import AiAnalysisPanel from './components/AiAnalysisPanel';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( './components/AiAnalysisResult', () => () => null );
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
} ) );

const deferred = () => {
	let resolve;
	const promise = new Promise( ( promiseResolve ) => {
		resolve = promiseResolve;
	} );
	return { promise, resolve };
};

describe( 'AI analysis polling', () => {
	let container;
	let root;

	beforeAll( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
	} );

	afterAll( () => {
		delete global.IS_REACT_ACT_ENVIRONMENT;
	} );

	beforeEach( () => {
		jest.useFakeTimers();
		container = document.createElement( 'div' );
		root = createRoot( container );
	} );

	afterEach( () => {
		act( () => root.unmount() );
		jest.useRealTimers();
		jest.clearAllMocks();
	} );

	it( 'waits for the active status request before scheduling another poll', async () => {
		const firstPoll = deferred();
		const secondPoll = deferred();
		let pollRequests = 0;

		apiFetch.mockImplementation( ( options ) => {
			if ( options.path === 'citeoryx/v1/integrations/ai/availability' ) {
				return Promise.resolve( {
					data: { enabled: true, configured: true },
				} );
			}
			if ( options.path === 'citeoryx/v1/integrations/ai/analyze/7' ) {
				return Promise.resolve( {
					data: { task_id: 'task-7', status: 'queued' },
				} );
			}
			if (
				options.path ===
				'citeoryx/v1/integrations/ai/analyze/7?task_id=task-7'
			) {
				++pollRequests;
				return 1 === pollRequests
					? firstPoll.promise
					: secondPoll.promise;
			}
			return Promise.reject(
				new Error( `Unexpected path: ${ options.path }` )
			);
		} );

		await act( async () => {
			root.render(
				<AiAnalysisPanel
					canManageIntegrations={ false }
					contentId={ 7 }
				/>
			);
		} );

		act( () => jest.advanceTimersByTime( 2500 ) );
		expect( pollRequests ).toBe( 1 );
		act( () => jest.advanceTimersByTime( 10000 ) );
		expect( pollRequests ).toBe( 1 );

		await act( async () => {
			firstPoll.resolve( {
				data: { task_id: 'task-7', status: 'running' },
			} );
		} );
		act( () => jest.advanceTimersByTime( 2499 ) );
		expect( pollRequests ).toBe( 1 );
		act( () => jest.advanceTimersByTime( 1 ) );
		expect( pollRequests ).toBe( 2 );
	} );
} );
