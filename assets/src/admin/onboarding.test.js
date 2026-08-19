import { createRoot } from '@wordpress/element';
import { act } from 'react';
import Onboarding from './components/Onboarding';

let mockSiteProfileProps;

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '@wordpress/components', () => ( {
	Button: ( { children } ) => children,
	Card: ( { children } ) => children,
	CardBody: ( { children } ) => children,
	CardHeader: ( { children } ) => children,
	Notice: ( { children } ) => children,
	ToggleControl: () => null,
} ) );
jest.mock( './components/SiteProfileFields', () => ( props ) => {
	mockSiteProfileProps = props;
	return null;
} );

const initialData = {
	settings: { auto_scan: true },
	profile: {},
	profile_options: {
		defaults: {
			site_type: 'blog',
			primary_goal: 'traffic',
			main_language: 'zh-CN',
			main_region: 'CN',
			update_rhythm: 'weekly',
			risk_level: 'medium',
			review_cycle_days: 90,
			core_content_types: [ 'post' ],
		},
	},
};

describe( 'Onboarding validation', () => {
	let container;
	let root;

	beforeAll( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
	} );

	afterAll( () => {
		delete global.IS_REACT_ACT_ENVIRONMENT;
	} );

	beforeEach( () => {
		mockSiteProfileProps = null;
		container = document.createElement( 'div' );
		root = createRoot( container );
		act( () => {
			root.render(
				<Onboarding
					initialData={ initialData }
					onComplete={ jest.fn() }
				/>
			);
		} );
	} );

	afterEach( () => {
		act( () => root.unmount() );
	} );

	it( 'passes field validation errors to the profile fields', () => {
		expect( mockSiteProfileProps.errors ).toEqual( {} );

		act( () => {
			mockSiteProfileProps.onValidate( 'main_language', '' );
		} );

		expect( mockSiteProfileProps.errors.main_language ).toBe(
			'请输入主要语言'
		);
	} );

	it( 'updates the profile through the provided change handler', () => {
		act( () => {
			mockSiteProfileProps.onChange( {
				...mockSiteProfileProps.profile,
				main_language: 'en-US',
			} );
		} );

		expect( mockSiteProfileProps.profile.main_language ).toBe( 'en-US' );
	} );
} );
