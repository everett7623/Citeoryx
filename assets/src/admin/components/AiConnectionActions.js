import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const AiConnectionActions = ( {
	canTest,
	onSave,
	onTest,
	saving,
	showTest,
	testing,
} ) => (
	<>
		<div className="citeoryx-ai-settings__actions">
			{ showTest && (
				<Button
					variant="secondary"
					onClick={ onTest }
					disabled={ saving || testing || ! canTest }
					isBusy={ testing }
				>
					{ __( '测试连接', 'citeoryx' ) }
				</Button>
			) }
			<Button
				variant="primary"
				onClick={ onSave }
				disabled={ saving || testing }
				isBusy={ saving }
			>
				{ __( '保存 AI 设置', 'citeoryx' ) }
			</Button>
		</div>
		{ showTest && ! canTest && (
			<p className="citeoryx-ai-settings__test-hint">
				{ __( '请先保存当前提供商设置，再测试连接。', 'citeoryx' ) }
			</p>
		) }
	</>
);

export default AiConnectionActions;
