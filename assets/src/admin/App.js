import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TabPanel, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import Dashboard from './components/Dashboard';
import Inventory from './components/Inventory';
import Issues from './components/Issues';
import Settings from './components/Settings';
import Onboarding from './components/Onboarding';
import Optimizer from './components/Optimizer';
import Integrations from './components/Integrations';

const App = () => {
	const [ activeTab, setActiveTab ] = useState( 'dashboard' );
	const [ profile, setProfile ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const fetchProfile = () => {
		apiFetch( { path: 'citeoryx/v1/settings' } )
			.then( ( response ) => {
				setProfile( response.data.profile || {} );
			} )
			.catch( () => setProfile( {} ) )
			.finally( () => setLoading( false ) );
	};

	useEffect( () => {
		fetchProfile();
	}, [] );

	const tabs = [
		{ name: 'dashboard', title: __( '总览', 'citeoryx' ) },
		{ name: 'inventory', title: __( '内容资产', 'citeoryx' ) },
		{ name: 'issues', title: __( '问题与机会', 'citeoryx' ) },
		{ name: 'optimizer', title: __( '优化器', 'citeoryx' ) },
		{ name: 'integrations', title: __( '集成', 'citeoryx' ) },
		{ name: 'settings', title: __( '设置', 'citeoryx' ) },
	];

	const renderTab = ( tab ) => {
		switch ( tab.name ) {
			case 'dashboard':
				return <Dashboard />;
			case 'inventory':
				return <Inventory />;
			case 'issues':
				return <Issues />;
			case 'optimizer':
				return <Optimizer />;
			case 'integrations':
				return <Integrations />;
			case 'settings':
				return <Settings onProfileSaved={ fetchProfile } />;
			default:
				return <Dashboard />;
		}
	};

	if ( loading ) {
		return <Spinner />;
	}

	if ( ! profile || ! profile.site_type ) {
		return (
			<div className="citeoryx-admin">
				<Onboarding onComplete={ fetchProfile } />
			</div>
		);
	}

	return (
		<div className="citeoryx-admin">
			<h1 className="citeoryx-admin__title">{ __( 'Citeoryx', 'citeoryx' ) }</h1>
			<TabPanel
				className="citeoryx-admin__tabs"
				activeClass="active-tab"
				tabs={ tabs }
				onSelect={ setActiveTab }
			>
				{ renderTab }
			</TabPanel>
		</div>
	);
};

export default App;
