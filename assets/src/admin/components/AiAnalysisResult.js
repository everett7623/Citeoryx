import { __ } from '@wordpress/i18n';
import { getAiAnalysisResult } from '../aiAnalysis';
import { confidenceLabel, optimizerCategoryLabel } from './optimizerLabels';

const TextList = ( { emptyText, items } ) =>
	items.length ? (
		<ul>
			{ items.map( ( item, index ) => (
				<li key={ `${ item }-${ index }` }>{ item }</li>
			) ) }
		</ul>
	) : (
		<p className="citeoryx-muted">{ emptyText }</p>
	);

const AiAnalysisResult = ( { task } ) => {
	const { discoverability, suggestions } = getAiAnalysisResult( task );

	return (
		<div className="citeoryx-ai-analysis__result">
			<div className="citeoryx-stat-grid">
				<div className="citeoryx-stat">
					<span className="citeoryx-stat__value">
						{ discoverability.score ?? '-' }
					</span>
					<span className="citeoryx-stat__label">
						{ __( 'AI 引用潜力', 'citeoryx' ) }
					</span>
				</div>
				<div className="citeoryx-stat">
					<span className="citeoryx-stat__value citeoryx-stat__value--text">
						{ confidenceLabel( discoverability.confidence ) }
					</span>
					<span className="citeoryx-stat__label">
						{ __( '分析置信度', 'citeoryx' ) }
					</span>
				</div>
			</div>

			{ discoverability.summary && (
				<section>
					<h3>{ __( '分析摘要', 'citeoryx' ) }</h3>
					<p>{ discoverability.summary }</p>
				</section>
			) }

			<div className="citeoryx-ai-analysis__evidence">
				<section>
					<h3>{ __( '优势', 'citeoryx' ) }</h3>
					<TextList
						emptyText={ __( '未识别到明确优势。', 'citeoryx' ) }
						items={ discoverability.strengths || [] }
					/>
				</section>
				<section>
					<h3>{ __( '薄弱点', 'citeoryx' ) }</h3>
					<TextList
						emptyText={ __( '未识别到明确薄弱点。', 'citeoryx' ) }
						items={ discoverability.weaknesses || [] }
					/>
				</section>
			</div>

			<section>
				<h3>{ __( 'AI 优化建议', 'citeoryx' ) }</h3>
				{ suggestions.length === 0 ? (
					<p className="citeoryx-muted">
						{ __( 'AI 未返回额外建议。', 'citeoryx' ) }
					</p>
				) : (
					<ul className="citeoryx-recommendations">
						{ suggestions.map( ( suggestion, index ) => (
							<li key={ `${ suggestion.title }-${ index }` }>
								<div className="citeoryx-rec__header">
									<span
										className={ `citeoryx-badge--${ suggestion.priority }` }
									>
										{ suggestion.priority }
									</span>
									<strong>{ suggestion.title }</strong>
									<span className="citeoryx-rec__category">
										{ optimizerCategoryLabel(
											suggestion.category
										) }
									</span>
								</div>
								<p>{ suggestion.description }</p>
							</li>
						) ) }
					</ul>
				) }
			</section>
		</div>
	);
};

export default AiAnalysisResult;
