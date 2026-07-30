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
	const [ fieldErrors, setFieldErrors ] = useState( {} );

	const validateField = ( fieldName, value ) => {
		let errorMessage = null;

		switch ( fieldName ) {
			case 'site_type':
			case 'primary_goal':
			case 'update_rhythm':
			case 'risk_level':
			case 'review_cycle_days':
				if ( ! value ) {
					errorMessage = __( '此字段必填', 'citeoryx' );
				}
				break;
			case 'main_language':
				if ( ! value || value.trim() === '' ) {
					errorMessage = __( '请输入主要语言', 'citeoryx' );
				} else if ( value.length > 20 ) {
					errorMessage = __( '语言代码不能超过 20 个字符', 'citeoryx' );
				}
				break;
			case 'main_region':
				if ( ! value || value.trim() === '' ) {
					errorMessage = __( '请输入主要地区', 'citeoryx' );
				} else if ( value.length > 20 ) {
					errorMessage = __( '地区代码不能超过 20 个字符', 'citeoryx' );
				}
				break;
			case 'core_content_types':
				if ( ! value || value.length === 0 ) {
					errorMessage = __( '请至少选择一种内容类型', 'citeoryx' );
				}
				break;
		}

		setFieldErrors( ( prev ) => {
			if ( errorMessage ) {
				return { ...prev, [ fieldName ]: errorMessage };
			} else {
				const { [ fieldName ]: _, ...rest } = prev;
				return rest;
			}
		} );

		return errorMessage === null;
	};

	const handleProfileChange = ( newProfile ) => {
		setProfile( newProfile );
		// 清除全局错误
		if ( error ) {
			setError( null );
		}
	};

	const save = () => {
		// 验证所有字段
		const fieldsToValidate = [
			'site_type',
			'primary_goal',
			'main_language',
			'main_region',
			'update_rhythm',
			'risk_level',
			'review_cycle_days',
			'core_content_types',
		];

		let hasErrors = false;
		fieldsToValidate.forEach( ( field ) => {
			const value =
				field === 'core_content_types'
					? profile.core_content_types
					: profile[ field ];
			if ( ! validateField( field, value ) ) {
				hasErrors = true;
			}
		} );

		if ( hasErrors ) {
			setError( __( '请修正表单中的错误后再提交。', 'citeoryx' ) );
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
