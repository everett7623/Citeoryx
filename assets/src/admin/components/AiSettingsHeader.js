import { Button, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const AiSettingsHeader = ( { enabled, onToggle, provider } ) => (
	<div className="citeoryx-ai-settings__header">
		<div className="citeoryx-ai-settings__header-copy">
			<h2>{ __( 'AI 内容分析设置', 'citeoryx' ) }</h2>
			<p>
				{ __(
					'配置内容优化器使用的 AI 服务商和请求参数。',
					'citeoryx'
				) }
			</p>
		</div>
		<div className="citeoryx-ai-settings__header-actions">
			<Button
				variant="secondary"
				href="admin.php?page=citeoryx-dashboard#/optimizer"
			>
				{ __( '前往 AI 分析', 'citeoryx' ) }
			</Button>
			<ToggleControl
				label={
					enabled
						? __( '已启用', 'citeoryx' )
						: __( '已关闭', 'citeoryx' )
				}
				checked={ enabled }
				disabled={ provider === 'none' }
				onChange={ onToggle }
			/>
		</div>
	</div>
);

export default AiSettingsHeader;
