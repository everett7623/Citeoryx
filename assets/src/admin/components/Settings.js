import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Card, CardBody, CardHeader, Button, TextControl, SelectControl, ToggleControl, Spinner, Notice } from '@wordpress/components';

const Settings = ( { onProfileSaved } ) => {
	const [ settings, setSettings ] = useState( { auto_scan: true, remove_data_on_uninstall: false } );
	const [ profile, setProfile ] = useState( {} );
	const [ loading, setLoading ] = useState( true );
	const [ saved, setSaved ] = useState( false );

	useEffect( () => {
		apiFetch( { path: 'citeoryx/v1/settings' } )
			.then( ( response ) => {
				setSettings( response.data.settings || {} );
				setProfile( response.data.profile || {} );
			} )
			.finally( () => setLoading( false ) );
	}, [] );

	const save = () => {
		apiFetch( {
			path: 'citeoryx/v1/settings',
			method: 'POST',
			data: { settings, profile },
		} ).then( () => {
			setSaved( true );
			setTimeout( () => setSaved( false ), 3000 );
			if ( onProfileSaved ) {
				onProfileSaved();
			}
		} );
	};

	if ( loading ) {
		return <Spinner />;
	}

	return (
		<div className="citeoryx-settings">
			{ saved && (
				<Notice status="success" isDismissible={ false }>
					{ __( '设置已保存。', 'citeoryx' ) }
				</Notice>
			) }

			<Card>
				<CardHeader>{ __( '站点画像', 'citeoryx' ) }</CardHeader>
				<CardBody>
					<SelectControl
						label={ __( '站点类型', 'citeoryx' ) }
						value={ profile.site_type || '' }
						options={ [
							{ label: __( '请选择', 'citeoryx' ), value: '' },
							{ label: __( '博客', 'citeoryx' ), value: 'blog' },
							{ label: __( '企业站', 'citeoryx' ), value: 'corporate' },
							{ label: __( '商城', 'citeoryx' ), value: 'shop' },
							{ label: __( '媒体', 'citeoryx' ), value: 'media' },
							{ label: __( '文档 / 知识库', 'citeoryx' ), value: 'docs' },
							{ label: __( '本地服务', 'citeoryx' ), value: 'local' },
						] }
						onChange={ ( value ) => setProfile( { ...profile, site_type: value } ) }
					/>
					<SelectControl
						label={ __( '主要目标', 'citeoryx' ) }
						value={ profile.primary_goal || '' }
						options={ [
							{ label: __( '请选择', 'citeoryx' ), value: '' },
							{ label: __( '流量', 'citeoryx' ), value: 'traffic' },
							{ label: __( '询盘', 'citeoryx' ), value: 'leads' },
							{ label: __( '销售', 'citeoryx' ), value: 'sales' },
							{ label: __( '订阅', 'citeoryx' ), value: 'subscriptions' },
							{ label: __( '品牌曝光', 'citeoryx' ), value: 'brand' },
							{ label: __( '支持分流', 'citeoryx' ), value: 'support' },
						] }
						onChange={ ( value ) => setProfile( { ...profile, primary_goal: value } ) }
					/>
					<TextControl
						label={ __( '主要语言', 'citeoryx' ) }
						value={ profile.main_language || '' }
						onChange={ ( value ) => setProfile( { ...profile, main_language: value } ) }
					/>
					<TextControl
						label={ __( '主要地区', 'citeoryx' ) }
						value={ profile.main_region || '' }
						onChange={ ( value ) => setProfile( { ...profile, main_region: value } ) }
					/>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>{ __( '通用设置', 'citeoryx' ) }</CardHeader>
				<CardBody>
					<ToggleControl
						label={ __( '启用自动扫描', 'citeoryx' ) }
						checked={ settings.auto_scan }
						onChange={ ( value ) => setSettings( { ...settings, auto_scan: value } ) }
					/>
					<ToggleControl
						label={ __( '卸载时删除数据', 'citeoryx' ) }
						checked={ settings.remove_data_on_uninstall }
						onChange={ ( value ) => setSettings( { ...settings, remove_data_on_uninstall: value } ) }
					/>
				</CardBody>
			</Card>

			<Button variant="primary" onClick={ save }>
				{ __( '保存设置', 'citeoryx' ) }
			</Button>
		</div>
	);
};

export default Settings;
