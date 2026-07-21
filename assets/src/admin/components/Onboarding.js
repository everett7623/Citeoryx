import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardBody,
	CardHeader,
	Button,
	Notice,
	ToggleControl,
} from '@wordpress/components';
import SiteProfileFields from './SiteProfileFields';
import { getSettingsData } from '../settingsData';
import { getApiErrorMessage } from '../apiError';

const Onboarding = ( { initialData, onComplete } ) => {
	const profileOptions = initialData.profile_options;
	const [ profile, setProfile ] = useState( {
		...profileOptions.defaults,
		...initialData.profile,
	} );
	const [ settings, setSettings ] = useState( initialData.settings );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	const save = () => {
		const required = [
			profile.site_type,
			profile.primary_goal,
			profile.main_language,
			profile.main_region,
			profile.update_rhythm,
			profile.risk_level,
			profile.review_cycle_days,
		];
		if ( required.some( ( value ) => ! value ) ) {
			setError( __( '请完成所有站点画像字段。', 'citeoryx' ) );
			return;
		}
		if ( ! profile.core_content_types?.length ) {
			setError( __( '请至少选择一种核心内容类型。', 'citeoryx' ) );
			return;
		}

		setError( null );
		setLoading( true );
		apiFetch( {
			path: 'citeoryx/v1/settings',
			method: 'POST',
			data: { settings, profile },
		} )
			.then( ( response ) => {
				if ( onComplete ) {
					onComplete( getSettingsData( response ) );
				}
			} )
			.catch( ( err ) =>
				setError(
					getApiErrorMessage(
						err,
						__( '保存失败，请稍后重试。', 'citeoryx' )
					)
				)
			)
			.finally( () => setLoading( false ) );
	};

	return (
		<div className="citeoryx-onboarding">
			<h2>{ __( '欢迎使用 Citeoryx', 'citeoryx' ) }</h2>
			<p>
				{ __(
					'请完成站点画像，Citeoryx 将根据你的目标定制分析建议。',
					'citeoryx'
				) }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<Card>
				<CardHeader>{ __( '站点画像', 'citeoryx' ) }</CardHeader>
				<CardBody>
					<SiteProfileFields
						profile={ profile }
						options={ profileOptions }
						onChange={ setProfile }
					/>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>{ __( '通用设置', 'citeoryx' ) }</CardHeader>
				<CardBody>
					<ToggleControl
						label={ __( '启用自动扫描', 'citeoryx' ) }
						checked={ settings.auto_scan }
						onChange={ ( value ) =>
							setSettings( { ...settings, auto_scan: value } )
						}
						__nextHasNoMarginBottom
					/>
				</CardBody>
			</Card>

			<Button variant="primary" onClick={ save } disabled={ loading }>
				{ loading
					? __( '保存中…', 'citeoryx' )
					: __( '开始使用', 'citeoryx' ) }
			</Button>
		</div>
	);
};

export default Onboarding;
