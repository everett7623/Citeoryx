import { __ } from '@wordpress/i18n';
import {
	CheckboxControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';

const withPlaceholder = ( options ) => [
	{ label: __( '请选择', 'citeoryx' ), value: '' },
	...( options || [] ),
];

const SiteProfileFields = ( { profile, options, onChange, onValidate, errors = {} } ) => {
	const update = ( key, value ) => onChange( { ...profile, [ key ]: value } );

	const handleBlur = ( key, value ) => {
		if ( onValidate ) {
			onValidate( key, value );
		}
	};

	const selectedContentTypes = profile.core_content_types || [];

	const toggleContentType = ( value, checked ) => {
		const next = checked
			? [ ...selectedContentTypes, value ]
			: selectedContentTypes.filter( ( item ) => item !== value );
		update( 'core_content_types', Array.from( new Set( next ) ) );
	};

	return (
		<>
			<SelectControl
				label={ __( '站点类型', 'citeoryx' ) }
				value={ profile.site_type || '' }
				options={ withPlaceholder( options.site_types ) }
				onChange={ ( value ) => update( 'site_type', value ) }
				onBlur={ () => handleBlur( 'site_type', profile.site_type ) }
				help={ errors.site_type }
				className={ errors.site_type ? 'has-error' : '' }
				__nextHasNoMarginBottom
			/>
			<SelectControl
				label={ __( '主要目标', 'citeoryx' ) }
				value={ profile.primary_goal || '' }
				options={ withPlaceholder( options.primary_goals ) }
				onChange={ ( value ) => update( 'primary_goal', value ) }
				onBlur={ () => handleBlur( 'primary_goal', profile.primary_goal ) }
				help={ errors.primary_goal }
				className={ errors.primary_goal ? 'has-error' : '' }
				__nextHasNoMarginBottom
			/>
			<fieldset className={ `citeoryx-content-types${ errors.core_content_types ? ' has-error' : '' }` }>
				<legend>{ __( '核心内容类型', 'citeoryx' ) }</legend>
				{ ( options.content_types || [] ).map( ( option ) => (
					<CheckboxControl
						key={ option.value }
						label={ option.label }
						checked={ selectedContentTypes.includes(
							option.value
						) }
						onChange={ ( checked ) => {
							toggleContentType( option.value, checked );
							// 验证内容类型
							const newTypes = checked
								? [ ...selectedContentTypes, option.value ]
								: selectedContentTypes.filter( ( item ) => item !== option.value );
							handleBlur( 'core_content_types', newTypes );
						} }
						__nextHasNoMarginBottom
					/>
				) ) }
				{ errors.core_content_types && (
					<div className="citeoryx-content-types-error">
						{ errors.core_content_types }
					</div>
				) }
			</fieldset>
			<div className="citeoryx-field-grid">
				<TextControl
					label={ __( '主要语言', 'citeoryx' ) }
					value={ profile.main_language || '' }
					onChange={ ( value ) => update( 'main_language', value ) }
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( '主要地区', 'citeoryx' ) }
					value={ profile.main_region || '' }
					onChange={ ( value ) => update( 'main_region', value ) }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( '更新节奏', 'citeoryx' ) }
					value={ profile.update_rhythm || '' }
					options={ withPlaceholder( options.update_rhythms ) }
					onChange={ ( value ) => update( 'update_rhythm', value ) }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( '内容风险等级', 'citeoryx' ) }
					value={ profile.risk_level || '' }
					options={ withPlaceholder( options.risk_levels ) }
					onChange={ ( value ) => update( 'risk_level', value ) }
					__nextHasNoMarginBottom
				/>
				<SelectControl
					label={ __( '默认审查周期', 'citeoryx' ) }
					value={ String( profile.review_cycle_days || '' ) }
					options={ withPlaceholder(
						( options.review_cycles || [] ).map( ( option ) => ( {
							...option,
							value: String( option.value ),
						} ) )
					) }
					onChange={ ( value ) =>
						update( 'review_cycle_days', Number( value ) )
					}
					__nextHasNoMarginBottom
				/>
			</div>
		</>
	);
};

export default SiteProfileFields;
