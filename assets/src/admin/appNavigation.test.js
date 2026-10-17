import { createRoot } from '@wordpress/element';
import { act } from 'react';

let mockPanelMounts = 0;
jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( './components/Dashboard', () => () => <div>Dashboard panel</div> );
jest.mock( './components/Inventory', () => () => <div>Inventory panel</div> );
jest.mock( './components/Issues', () => () => null );
jest.mock( './components/Settings', () => () => null );
jest.mock( './components/Onboarding', () => () => null );
jest.mock( './components/Optimizer', () => () => <div>Optimizer panel</div> );
jest.mock( './components/Integrations', () => () => (
	<div>Integrations panel</div>
) );
jest.mock( './components/Reports', () => () => null );
jest.mock( './components/Planning', () => () => null );
jest.mock( '@wordpress/components', () => {
	const { useState, useEffect } = require( 'react' );
	return {
		Button: ( { children } ) => children,
		Notice: ( { children } ) => children,
		Spinner: () => null,
		TabPanel: ( { initialTabName, tabs, children, onSelect } ) => {
			const [ selected, setSelected ] = useState( initialTabName );
			useEffect( () => {
				mockPanelMounts++;
			}, [] );
			useEffect( () => {
				onSelect( selected );
			}, [ selected, onSelect ] );
			return (
				<div>
					{ tabs.map( ( tab ) => (
						<button
							key={ tab.name }
							onClick={ () => setSelected( tab.name ) }
						>
							{ tab.name }
						</button>
					) ) }
					{ children(
						tabs.find( ( tab ) => tab.name === selected )
					) }
				</div>
			);
		},
	};
} );

describe( '后台 hash 导航', () => {
	let App;
	let container;
	let root;
	beforeAll( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
		window.citeoryxAdmin = {
			user: { canViewDashboard: true, canManageIntegrations: true },
		};
		App = require( './App' ).default;
	} );
	afterAll( () => {
		delete global.IS_REACT_ACT_ENVIRONMENT;
		delete window.citeoryxAdmin;
	} );
	beforeEach( () => {
		window.history.replaceState( null, '', '#/inventory' );
		mockPanelMounts = 0;
		container = document.createElement( 'div' );
		root = createRoot( container );
	} );
	afterEach( () => act( () => root.unmount() ) );
	const navigate = ( hash ) => {
		window.history.replaceState( null, '', hash );
		window.dispatchEvent( new Event( 'hashchange' ) );
	};
	it( '响应外部跳转及返回先前页签', async () => {
		await act( async () => root.render( <App /> ) );
		expect( container.textContent ).toContain( 'Inventory panel' );
		act( () => navigate( '#/integrations' ) );
		expect( container.textContent ).toContain( 'Integrations panel' );
		act( () => navigate( '#/optimizer' ) );
		expect( container.textContent ).toContain( 'Optimizer panel' );
		act( () => navigate( '#/inventory' ) );
		expect( container.textContent ).toContain( 'Inventory panel' );
	} );
	it( '无权限及未知目标回退至可见页签', async () => {
		await act( async () => root.render( <App /> ) );
		act( () => navigate( '#/settings' ) );
		expect( container.textContent ).toContain( 'Dashboard panel' );
		act( () => navigate( '#/unknown' ) );
		expect( container.textContent ).toContain( 'Dashboard panel' );
	} );
	it( '普通页签点击不会再次挂载 TabPanel', async () => {
		await act( async () => root.render( <App /> ) );
		act( () => {
			Array.from( container.querySelectorAll( 'button' ) )
				.find( ( button ) => button.textContent === 'optimizer' )
				.click();
		} );
		act( () => window.dispatchEvent( new Event( 'hashchange' ) ) );
		expect( container.textContent ).toContain( 'Optimizer panel' );
		expect( mockPanelMounts ).toBe( 1 );
	} );
} );
