import { buildReportCsv } from './reportCsv';

test( 'CSV export neutralizes spreadsheet formulas', () => {
	const csv = buildReportCsv( {
		generated_at: '2026-07-21 12:00:00',
		content: {
			total: 1,
			average_health_score: 80,
			average_ai_readiness_score: null,
			status_counts: [ { label: '=SUM(1,1)', count: 1 } ],
		},
		issues: {
			open_total: 0,
			severity_counts: [],
			category_counts: [],
		},
	} );

	expect( csv ).toContain( '"\'=SUM(1,1)"' );
} );
