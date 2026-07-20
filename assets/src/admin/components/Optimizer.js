import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Card, CardBody, CardHeader, Button, SelectControl, Spinner, Notice, Badge } from '@wordpress/components';

const Optimizer = () => {
	const [ contentId, setContentId ] = useState( '' );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ items, setItems ] = useState( [] );
	const [ itemLoading, setItemLoading ] = useState( true );

	useEffect( () => {
		apiFetch( { path: 'citeoryx/v1/content?per_page=100' } )
			.then( ( response ) => {
				const list = response.data.items || [];
				setItems( list.map( ( item ) => ( { label: item.canonical_url, value: item.id.toString() } ) ) );
			} )
			.catch( () => setItems( [] ) )
			.finally( () => setItemLoading( false ) );
	}, [] );

	const analyze = () => {
		if ( ! contentId ) {
			setError( __( '请选择要分析的内容。', 'citeoryx' ) );
			return;
		}
		setLoading( true );
		setError( null );
		apiFetch( { path: `citeoryx/v1/optimizer/${ contentId }` } )
			.then( ( response ) => setData( response.data ) )
			.catch( ( err ) => setError( err.message || __( '分析失败。', 'citeoryx' ) ) )
			.finally( () => setLoading( false ) );
	};

	const categoryLabel = ( category ) => {
		const labels = {
			content: __( '内容', 'citeoryx' ),
			structure: __( '结构', 'citeoryx' ),
			links: __( '链接', 'citeoryx' ),
			discoverability: __( '可发现性', 'citeoryx' ),
			aeo: __( 'AI 可发现性', 'citeoryx' ),
			general: __( '通用', 'citeoryx' ),
		};
		return labels[ category ] || category;
	};

	return (
		<div className="citeoryx-optimizer">
			<h2>{ __( '内容优化器', 'citeoryx' ) }</h2>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<Card>
				<CardBody>
					{ itemLoading ? <Spinner /> : (
						<>
							<SelectControl
								label={ __( '选择内容', 'citeoryx' ) }
								value={ contentId }
								options={ [
									{ label: __( '请选择', 'citeoryx' ), value: '' },
									...items,
								] }
								onChange={ ( value ) => setContentId( value ) }
							/>
							<Button variant="primary" onClick={ analyze } disabled={ loading }>
								{ loading ? __( '分析中…', 'citeoryx' ) : __( '生成优化建议', 'citeoryx' ) }
							</Button>
						</>
					) }
				</CardBody>
			</Card>

			{ data && (
				<>
					<Card>
						<CardHeader>{ __( '评分', 'citeoryx' ) }</CardHeader>
						<CardBody>
							<div className="citeoryx-stats">
								<div className="citeoryx-stat">
									<span className="citeoryx-stat__value">{ data.scores.health.score ?? '-' }</span>
									<span className="citeoryx-stat__label">{ __( '健康分', 'citeoryx' ) }</span>
								</div>
								<div className="citeoryx-stat">
									<span className="citeoryx-stat__value">{ data.scores.aeo.score ?? '-' }</span>
									<span className="citeoryx-stat__label">{ __( 'AI 可发现性', 'citeoryx' ) }</span>
								</div>
							</div>
						</CardBody>
					</Card>

					<Card>
						<CardHeader>{ __( '优化建议', 'citeoryx' ) }</CardHeader>
						<CardBody>
							{ data.recommendations.length === 0 ? (
								<p>{ __( '暂无优化建议，内容状态良好。', 'citeoryx' ) }</p>
							) : (
								<ul className="citeoryx-recommendations">
									{ data.recommendations.map( ( rec, index ) => (
										<li key={ index }>
											<div className="citeoryx-rec__header">
												<Badge className={ `citeoryx-badge--${ rec.priority }` }>
													{ rec.priority }
												</Badge>
												<strong>{ rec.title }</strong>
												<span className="citeoryx-rec__category">{ categoryLabel( rec.category ) }</span>
											</div>
											<p>{ rec.description }</p>
											{ rec.issue_id && (
												<Button size="small" href={ `post.php?post=${ data.content.object_id }&action=edit` }>
													{ rec.action }
												</Button>
											) }
										</li>
									) ) }
								</ul>
							) }
						</CardBody>
					</Card>
				</>
			) }
		</div>
	);
};

export default Optimizer;
