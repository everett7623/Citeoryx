import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { optimizerCategoryLabel } from './optimizerLabels';

const RecommendationEvidence = ( { evidence } ) => {
	const items = Array.isArray( evidence )
		? evidence.filter(
				( item ) =>
					item &&
					typeof item.label === 'string' &&
					typeof item.value === 'string' &&
					item.value !== ''
		  )
		: [];

	if ( items.length === 0 ) {
		return null;
	}

	return (
		<details className="citeoryx-recommendation-evidence">
			<summary>{ __( '查看证据', 'citeoryx' ) }</summary>
			<dl>
				{ items.map( ( item, index ) => (
					<div key={ `${ item.label }-${ index }` }>
						<dt>{ item.label }</dt>
						<dd>{ item.value }</dd>
					</div>
				) ) }
			</dl>
		</details>
	);
};

const InternalLinkSuggestions = ( { suggestions } ) => {
	const items = Array.isArray( suggestions )
		? suggestions.filter(
				( item ) =>
					item &&
					typeof item.title === 'string' &&
					typeof item.url === 'string' &&
					typeof item.suggested_anchor === 'string'
		  )
		: [];

	return (
		<Card>
			<CardHeader>{ __( '内链建议', 'citeoryx' ) }</CardHeader>
			<CardBody>
				{ items.length === 0 ? (
					<p className="citeoryx-muted">
						{ __(
							'暂无可靠的内链候选。完成更多内容扫描后再试。',
							'citeoryx'
						) }
					</p>
				) : (
					<ul className="citeoryx-link-suggestions">
						{ items.map( ( suggestion ) => (
							<li
								className="citeoryx-link-suggestions__item"
								key={ suggestion.target_content_id }
							>
								<div className="citeoryx-link-suggestions__header">
									<strong>{ suggestion.title }</strong>
									<span>
										{ `${ __( '相关度', 'citeoryx' ) } ${
											Number( suggestion.score ) || 0
										}` }
									</span>
								</div>
								{ Array.isArray( suggestion.reasons ) &&
									suggestion.reasons.length > 0 && (
										<p className="citeoryx-link-suggestions__reasons">
											{ suggestion.reasons.join( ' · ' ) }
										</p>
									) }
								<dl>
									<div>
										<dt>
											{ __( '建议锚文本', 'citeoryx' ) }
										</dt>
										<dd>{ suggestion.suggested_anchor }</dd>
									</div>
								</dl>
								<Button
									size="small"
									href={ suggestion.url }
									target="_blank"
									rel="noreferrer"
								>
									{ __( '打开目标内容', 'citeoryx' ) }
								</Button>
							</li>
						) ) }
					</ul>
				) }
			</CardBody>
		</Card>
	);
};

const OptimizerResults = ( { data } ) => (
	<div className="citeoryx-optimizer__rule-results">
		<Card>
			<CardHeader>{ __( '规则评分', 'citeoryx' ) }</CardHeader>
			<CardBody>
				<div className="citeoryx-stat-grid">
					<div className="citeoryx-stat">
						<span className="citeoryx-stat__value">
							{ data.scores.health.score ?? '-' }
						</span>
						<span className="citeoryx-stat__label">
							{ __( '健康分', 'citeoryx' ) }
						</span>
					</div>
					<div className="citeoryx-stat">
						<span className="citeoryx-stat__value">
							{ data.scores.aeo.score ?? '-' }
						</span>
						<span className="citeoryx-stat__label">
							{ __( 'AI 可发现性', 'citeoryx' ) }
						</span>
					</div>
				</div>
			</CardBody>
		</Card>

		<Card>
			<CardHeader>{ __( '规则优化建议', 'citeoryx' ) }</CardHeader>
			<CardBody>
				{ data.recommendations.length === 0 ? (
					<p>{ __( '暂无优化建议，内容状态良好。', 'citeoryx' ) }</p>
				) : (
					<ul className="citeoryx-recommendations">
						{ data.recommendations.map(
							( recommendation, index ) => (
								<li key={ recommendation.issue_id || index }>
									<div className="citeoryx-rec__header">
										<span
											className={ `citeoryx-badge--${ recommendation.priority }` }
										>
											{ recommendation.priority }
										</span>
										<strong>
											{ recommendation.title }
										</strong>
										<span className="citeoryx-rec__category">
											{ optimizerCategoryLabel(
												recommendation.category
											) }
										</span>
									</div>
									<p>{ recommendation.description }</p>
									<RecommendationEvidence
										evidence={ recommendation.evidence }
									/>
									{ recommendation.issue_id && (
										<Button
											size="small"
											href={ `post.php?post=${ data.content.object_id }&action=edit` }
										>
											{ recommendation.action }
										</Button>
									) }
								</li>
							)
						) }
					</ul>
				) }
			</CardBody>
		</Card>

		<InternalLinkSuggestions suggestions={ data.link_suggestions } />
	</div>
);

export default OptimizerResults;
