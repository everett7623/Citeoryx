import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Card, CardBody, CardHeader, Button, SelectControl, TextControl, Notice } from '@wordpress/components';

const Onboarding = ( { onComplete } ) => {
	const [ profile, setProfile ] = useState( {
		primary_goal: '',
		site_type: '',
		main_language: '',
		main_region: '',
	} );
	const [ settings, setSettings ] = useState( { auto_scan: true, remove_data_on_uninstall: false } );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	const save = () => {
		if ( ! profile.primary_goal || ! profile.site_type ) {
			setError( __( '请填写站点类型和主要目标。', 'citeoryx' ) );
			return;
		}

		setLoading( true );
		apiFetch( {
			path: 'citeoryx/v1/settings',
			method: 'POST',
			data: { settings, profile },
		} )
			.then( () => {
				if ( onComplete ) {
					onComplete();
				}
			} )
			.catch( ( err ) => setError( err.message || __( '保存失败。', 'citeoryx' ) ) )
			.finally( () => setLoading( false ) );
	};

	return (
		<div className="citeoryx-onboarding">
			<h2>{ __( '欢迎使用 Citeoryx', 'citeoryx' ) }</h2>
			<p>{ __( '请完成站点画像，Citeoryx 将根据你的目标定制分析建议。', 'citeoryx' ) }</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<Card>
				<CardHeader>{ __( '站点画像', 'citeoryx' ) }</CardHeader>
				<CardBody>
					<SelectControl
						label={ __( '站点类型', 'citeoryx' ) }
						value={ profile.site_type }
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
						value={ profile.primary_goal }
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
						value={ profile.main_language }
						onChange={ ( value ) => setProfile( { ...profile, main_language: value } ) }
					/>
					<TextControl
						label={ __( '主要地区', 'citeoryx' ) }
						value={ profile.main_region }
						onChange={ ( value ) => setProfile( { ...profile, main_region: value } ) }
					/>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>{ __( '通用设置', 'citeoryx' ) }</CardHeader>
				<CardBody>
					<div className="citeoryx-toggle-list">
						<label>
							<input
								type="checkbox"
								checked={ settings.auto_scan }
								onChange={ ( e ) => setSettings( { ...settings, auto_scan: e.target.checked } ) }
							/>
							{ __( '启用自动扫描', 'citeoryx' ) }
						</label>
					</div>
				</CardBody>
			</Card>

			<Button variant="primary" onClick={ save } disabled={ loading }>
				{ loading ? __( '保存中…', 'citeoryx' ) : __( '开始使用', 'citeoryx' ) }
			</Button>
		</div>
	);
};

export default Onboarding;
