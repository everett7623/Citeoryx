import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Notice, TabPanel, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import Dashboard from './components/Dashboard';
import Inventory from './components/Inventory';
import Issues from './components/Issues';
import Settings from './components/Settings';
import Onboarding from './components/Onboarding';
import Optimizer from './components/Optimizer';
import Integrations from './components/Integrations';
import Reports from './components/Reports';
import Planning from './components/Planning';
import { getSettingsData } from './settingsData';
import { getApiErrorMessage } from './apiError';

const adminUser = window.citeoryxAdmin?.user || {};

const App = () => {
	const [ settingsData, setSettingsData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const fetchSettings = () => {
		setLoading( true );
		setError( null );
		apiFetch( { path: 'citeoryx/v1/settings' } )
			.then( ( response ) =>
				setSettingsData( getSettingsData( response ) )
			)
			.catch( ( err ) =>
				setError(
					getApiErrorMessage(
						err,
						__(
							'无法加载插件设置，请检查 WordPress 错误日志后重试。',
							'citeoryx'
						)
					)
				)
			)
			.finally( () => setLoading( false ) );
	};

	useEffect( () => {
		if ( adminUser.canSettings ) {
			fetchSettings();
			return;
		}
		setLoading( false );
	}, [] );

	const tabs = [
		...( adminUser.canViewDashboard
			? [ { name: 'dashboard', title: __( '总览', 'citeoryx' ) } ]
			: [] ),
		{ name: 'inventory', title: __( '内容资产', 'citeoryx' ) },
		{ name: 'issues', title: __( '问题与机会', 'citeoryx' ) },
		{ name: 'optimizer', title: __( '优化器', 'citeoryx' ) },
		...( adminUser.canManageIntegrations
			? [ { name: 'integrations', title: __( '集成', 'citeoryx' ) } ]
			: [] ),
		...( adminUser.canViewDashboard
			? [
					{ name: 'planning', title: __( '内容规划', 'citeoryx' ) },
					{ name: 'reports', title: __( '报告', 'citeoryx' ) },
			  ]
			: [] ),
		...( adminUser.canSettings
			? [ { name: 'settings', title: __( '设置', 'citeoryx' ) } ]
			: [] ),
	];
	const hashTab = window.location.hash.replace( /^#\//, '' );
	const initialTab = tabs.some( ( tab ) => tab.name === hashTab )
		? hashTab
		: tabs[ 0 ].name;

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
			case 'reports':
				return <Reports />;
			case 'planning':
				return <Planning />;
			case 'settings':
				return (
					<Settings
						initialData={ settingsData }
						onSaved={ setSettingsData }
					/>
				);
			default:
				return <Inventory />;
		}
	};

	if ( loading ) {
		return <Spinner />;
	}

	if ( adminUser.canSettings && ( error || ! settingsData ) ) {
		return (
			<div className="citeoryx-admin">
				<Notice status="error" isDismissible={ false }>
					{ error || __( '设置响应无效。', 'citeoryx' ) }
				</Notice>
				<Button variant="secondary" onClick={ fetchSettings }>
					{ __( '重试', 'citeoryx' ) }
				</Button>
			</div>
		);
	}

	if ( adminUser.canSettings && ! settingsData.profile_complete ) {
		return (
			<div className="citeoryx-admin">
				<Onboarding
					initialData={ settingsData }
					onComplete={ setSettingsData }
				/>
			</div>
		);
	}

	return (
		<div className="citeoryx-admin">
			<h1 className="citeoryx-admin__title">
				{ __( 'Citeoryx', 'citeoryx' ) }
			</h1>
			<TabPanel
				className="citeoryx-admin__tabs"
				activeClass="active-tab"
				tabs={ tabs }
				initialTab={ initialTab }
				onSelect={ ( tab ) => {
					window.location.hash = `/${ tab }`;
				} }
			>
				{ renderTab }
			</TabPanel>
		</div>
	);
};

export default App;
