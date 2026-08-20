import { createRoot } from '@wordpress/element';
import { act } from 'react';
import apiFetch from '@wordpress/api-fetch';
import Inventory from './components/Inventory';
import Optimizer from './components/Optimizer';
import Planning from './components/Planning';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '@wordpress/icons', () => ( { postList: null } ) );
jest.mock( './components/AiAnalysisPanel', () => () => null );
jest.mock( './components/OptimizerResults', () => ( { data } ) => (
	<div>{ `optimizer-result-${ data.content.id }` }</div>
) );
jest.mock( './components/OptimizerRevisionPanel', () => () => null );
jest.mock( './components/PlanningCalendar', () => () => null );
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
	TextControl: ( { label, onChange, onKeyDown, value } ) => (
		<input
			aria-label={ label }
			value={ value }
			onChange={ ( event ) => onChange( event.target.value ) }
			onKeyDown={ onKeyDown }
		/>
	),
} ) );

const deferred = () => {
	let resolve;
	const promise = new Promise( ( promiseResolve ) => {
		resolve = promiseResolve;
	} );
	return { promise, resolve };
};

const clickButton = ( container, label ) => {
	const button = Array.from( container.querySelectorAll( 'button' ) ).find(
		( candidate ) => candidate.textContent === label
	);
	button.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );
};

const inventoryItem = ( id, url ) => ( {
	id,
	canonical_url: url,
	post_type: 'post',
	status: 'healthy',
	health_score: 90,
	ai_readiness_score: 80,
	modified_at: '2026-08-20 10:00:00',
} );

const opportunity = ( id, query ) => ( {
	id,
	query,
	type: 'striking_distance',
	source: 'google_search_console',
	priority_score: 88,
	recommended_action: 'improve_existing',
	metrics: { impressions: 100, clicks: 10, position_avg: 8 },
	pages: [],
} );

describe( 'Paginated list request ordering', () => {
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
			user: {
				canApplyChanges: false,
				canExport: true,
				canManageIntegrations: false,
				canScan: false,
				canUseAi: false,
			},
		};
		container = document.createElement( 'div' );
		root = createRoot( container );
	} );

	afterEach( () => {
		act( () => root.unmount() );
		delete window.citeoryxAdmin;
		jest.clearAllMocks();
	} );

	it( 'keeps the latest inventory filter response after a slow page request', async () => {
		const initialPage = deferred();
		const stalePage = deferred();
		const filteredPage = deferred();

		apiFetch.mockImplementation( ( options ) => {
			if ( options.path.includes( 'status=healthy' ) ) {
				return filteredPage.promise;
			}
			if ( options.path.includes( '?page=2&' ) ) {
				return stalePage.promise;
			}
			return initialPage.promise;
		} );

		await act( async () => {
			root.render( <Inventory /> );
		} );
		await act( async () => {
			initialPage.resolve( {
				data: {
					items: [ inventoryItem( 1, 'https://example.com/first' ) ],
					total: 21,
				},
			} );
		} );
		expect( container.textContent ).toContain(
			'https://example.com/first'
		);

		act( () => clickButton( container, '下一页' ) );
		act( () => {
			const status = container.querySelector( 'select' );
			status.value = 'healthy';
			status.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		await act( async () => {
			filteredPage.resolve( {
				data: {
					items: [ inventoryItem( 2, 'https://example.com/latest' ) ],
					total: 1,
				},
			} );
		} );
		expect( container.textContent ).toContain(
			'https://example.com/latest'
		);

		await act( async () => {
			stalePage.resolve( { data: { items: [], total: 0 } } );
		} );
		expect( container.textContent ).toContain(
			'https://example.com/latest'
		);
	} );

	it( 'keeps the latest planning filter response after a slow page request', async () => {
		const initialPage = deferred();
		const stalePage = deferred();
		const filteredPage = deferred();

		apiFetch.mockImplementation( ( options ) => {
			if ( options.path.includes( 'type=striking_distance' ) ) {
				return filteredPage.promise;
			}
			if ( options.path.includes( '?page=2&' ) ) {
				return stalePage.promise;
			}
			return initialPage.promise;
		} );

		await act( async () => {
			root.render( <Planning /> );
		} );
		await act( async () => {
			initialPage.resolve( {
				data: {
					items: [ opportunity( 'first', 'first query' ) ],
					pagination: { page: 1, total_pages: 2 },
					summary: {},
				},
			} );
		} );
		expect( container.textContent ).toContain( 'first query' );

		act( () => clickButton( container, '下一页' ) );
		act( () => {
			const type = container.querySelector( 'select' );
			type.value = 'striking_distance';
			type.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		await act( async () => {
			filteredPage.resolve( {
				data: {
					items: [ opportunity( 'latest', 'latest query' ) ],
					pagination: { page: 1, total_pages: 1 },
					summary: {},
				},
			} );
		} );
		expect( container.textContent ).toContain( 'latest query' );

		await act( async () => {
			stalePage.resolve( {
				data: { items: [], pagination: { page: 2, total_pages: 2 } },
			} );
		} );
		expect( container.textContent ).toContain( 'latest query' );
	} );

	it( 'loads optimizer content in bounded pages', async () => {
		const initialPage = deferred();
		const secondPage = deferred();

		apiFetch.mockImplementation( ( options ) => {
			if ( options.path.includes( '?page=2&' ) ) {
				return secondPage.promise;
			}
			return initialPage.promise;
		} );

		await act( async () => {
			root.render( <Optimizer /> );
		} );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: 'citeoryx/v1/content?page=1&per_page=20',
		} );
		await act( async () => {
			initialPage.resolve( {
				data: {
					items: [
						inventoryItem( 1, 'https://example.com/optimizer-1' ),
					],
					total: 21,
				},
			} );
		} );
		expect( container.textContent ).toContain(
			'https://example.com/optimizer-1'
		);

		act( () => clickButton( container, '下一页' ) );
		await act( async () => {
			secondPage.resolve( {
				data: {
					items: [
						inventoryItem( 21, 'https://example.com/optimizer-21' ),
					],
					total: 21,
				},
			} );
		} );

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: 'citeoryx/v1/content?page=2&per_page=20',
		} );
		expect( container.textContent ).toContain(
			'https://example.com/optimizer-21'
		);
	} );

	it( 'ignores an optimizer analysis response after the selection changes', async () => {
		const firstAnalysis = deferred();
		const secondAnalysis = deferred();

		apiFetch.mockImplementation( ( options ) => {
			if ( options.path === 'citeoryx/v1/optimizer/1' ) {
				return firstAnalysis.promise;
			}
			if ( options.path === 'citeoryx/v1/optimizer/2' ) {
				return secondAnalysis.promise;
			}
			return Promise.resolve( {
				data: {
					items: [
						inventoryItem( 1, 'https://example.com/optimizer-1' ),
						inventoryItem( 2, 'https://example.com/optimizer-2' ),
					],
					total: 2,
				},
			} );
		} );

		await act( async () => {
			root.render( <Optimizer /> );
		} );

		const selector = container.querySelector( 'select' );
		act( () => {
			selector.value = '1';
			selector.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		act( () => clickButton( container, '生成优化建议' ) );

		act( () => {
			selector.value = '2';
			selector.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		act( () => clickButton( container, '生成优化建议' ) );

		await act( async () => {
			secondAnalysis.resolve( { data: { content: { id: 2 } } } );
		} );
		expect( container.textContent ).toContain( 'optimizer-result-2' );

		await act( async () => {
			firstAnalysis.resolve( { data: { content: { id: 1 } } } );
		} );
		expect( container.textContent ).toContain( 'optimizer-result-2' );
		expect( container.textContent ).not.toContain( 'optimizer-result-1' );
	} );
} );
