import { createRoot } from '@wordpress/element';
import { act } from 'react';
import apiFetch from '@wordpress/api-fetch';
import OptimizerRevisionPanel from './components/OptimizerRevisionPanel';

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
	TextControl: ( { label, onChange, value } ) => (
		<input
			aria-label={ label }
			value={ value }
			onChange={ ( event ) => onChange( event.target.value ) }
		/>
	),
	TextareaControl: ( { label, onChange, value } ) => (
		<textarea
			aria-label={ label }
			value={ value }
			onChange={ ( event ) => onChange( event.target.value ) }
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

const findButton = ( container, label ) =>
	Array.from( container.querySelectorAll( 'button' ) ).find(
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

const editor = {
	available: true,
	revisions_enabled: true,
	title: 'Original title',
	content: '<p>Original content</p>',
	excerpt: 'Original excerpt',
	base_content_hash: 'a'.repeat( 64 ),
	workflow: {
		state: 'awaiting_review',
		can_verify: false,
	},
};

describe( 'Optimizer revision workflow requests', () => {
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

	it( 'does not restore old optimizer data after the panel is unmounted', async () => {
		const workflowRequest = deferred();
		const onDataRefresh = jest.fn();
		apiFetch.mockReturnValue( workflowRequest.promise );

		await act( async () => {
			root.render(
				<OptimizerRevisionPanel
					canScan={ false }
					contentId={ 1 }
					editor={ editor }
					onDataRefresh={ onDataRefresh }
					performance={ null }
				/>
			);
		} );

		const refreshButton = findButton( container, '刷新状态' );
		act( () => {
			refreshButton.dispatchEvent(
				new window.MouseEvent( 'click', { bubbles: true } )
			);
		} );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: 'citeoryx/v1/optimizer/1',
		} );

		act( () => root.render( <div>new content selected</div> ) );
		await act( async () => {
			workflowRequest.resolve( {
				data: {
					content: { id: 1 },
					editor,
					revision_performance: null,
				},
			} );
		} );

		expect( container.textContent ).toBe( 'new content selected' );
		expect( onDataRefresh ).not.toHaveBeenCalled();
	} );

	it( 'locks revision writes and workflow refreshes as one operation', async () => {
		const workflowRequest = deferred();
		const submitRequest = deferred();
		const activeEditor = {
			...editor,
			workflow: {
				state: 'published_pending_scan',
				can_verify: true,
			},
		};
		apiFetch.mockImplementation( ( options ) => {
			if ( options.path === 'citeoryx/v1/optimizer/1' ) {
				return workflowRequest.promise;
			}
			if ( options.path === 'citeoryx/v1/recommendations/apply' ) {
				return submitRequest.promise;
			}
			return Promise.reject(
				new Error( `Unexpected path: ${ options.path }` )
			);
		} );

		await act( async () => {
			root.render(
				<OptimizerRevisionPanel
					canScan
					contentId={ 1 }
					editor={ activeEditor }
					onDataRefresh={ jest.fn() }
					performance={ null }
				/>
			);
		} );
		act( () => {
			setInputValue(
				container.querySelector( 'input' ),
				'Updated title'
			);
		} );

		act( () => {
			findButton( container, '刷新状态' ).click();
		} );
		expect( findButton( container, '刷新中…' ).disabled ).toBe( true );
		expect( findButton( container, '重新扫描并验证' ).disabled ).toBe(
			true
		);
		expect( findButton( container, '创建 Revision' ).disabled ).toBe(
			true
		);

		await act( async () => {
			workflowRequest.resolve( {
				data: {
					content: { id: 1 },
					editor: activeEditor,
					revision_performance: null,
				},
			} );
		} );
		expect( findButton( container, '创建 Revision' ).disabled ).toBe(
			false
		);

		act( () => {
			findButton( container, '创建 Revision' ).click();
		} );
		expect( findButton( container, '刷新状态' ).disabled ).toBe( true );
		expect( findButton( container, '重新扫描并验证' ).disabled ).toBe(
			true
		);

		await act( async () => {
			submitRequest.resolve( {
				data: {
					revision: {
						created: true,
						compare_url: 'https://example.com/revision',
					},
					workflow: activeEditor.workflow,
				},
			} );
		} );
		expect( findButton( container, '刷新状态' ).disabled ).toBe( false );
		expect( findButton( container, '重新扫描并验证' ).disabled ).toBe(
			false
		);
	} );
} );
