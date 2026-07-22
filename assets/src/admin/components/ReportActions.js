import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Notice } from '@wordpress/components';
import { exportReportCsv } from '../reportCsv';
import { exportReportPdf } from '../reportPdf';

const ReportActions = ( { data, loading, onRefresh, canExport } ) => {
	const [ pdfLoading, setPdfLoading ] = useState( false );
	const [ pdfError, setPdfError ] = useState( '' );

	const downloadPdf = async () => {
		setPdfLoading( true );
		setPdfError( '' );
		try {
			await exportReportPdf( data );
		} catch ( error ) {
			setPdfError(
				error instanceof Error
					? error.message
					: __( 'PDF 生成失败，请稍后重试。', 'citeoryx' )
			);
		} finally {
			setPdfLoading( false );
		}
	};

	return (
		<>
			<div className="citeoryx-dashboard__actions">
				<Button
					onClick={ onRefresh }
					disabled={ loading || pdfLoading }
				>
					{ loading
						? __( '刷新中…', 'citeoryx' )
						: __( '刷新报告', 'citeoryx' ) }
				</Button>
				{ canExport && (
					<>
						<Button
							variant="secondary"
							onClick={ () => exportReportCsv( data ) }
							disabled={ ! data || pdfLoading }
						>
							{ __( '导出 CSV', 'citeoryx' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ downloadPdf }
							disabled={ ! data || loading || pdfLoading }
							isBusy={ pdfLoading }
						>
							{ pdfLoading
								? __( '生成 PDF…', 'citeoryx' )
								: __( '导出 PDF', 'citeoryx' ) }
						</Button>
					</>
				) }
			</div>
			{ pdfError && (
				<Notice status="error" isDismissible={ false }>
					{ pdfError }
				</Notice>
			) }
		</>
	);
};

export default ReportActions;
