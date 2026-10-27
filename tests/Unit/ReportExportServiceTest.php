<?php

namespace Tests\Unit;

use App\Services\ReportExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportExportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(
            ReportExportService::class
        );
    }

    public function test_supported_report_types_are_accepted(): void
    {
        foreach (
            [
                'loans',
                'returns',
                'fines',
                'incidents',
                'acquisitions',
                'stock_opnames',
                'reservations',
                'members',
                'catalog',
                'overview',
            ] as $report
        ) {
            $this->assertTrue(
                $this->service->supports($report)
            );
        }
    }

    public function test_unknown_report_type_is_rejected(): void
    {
        $this->assertFalse(
            $this->service->supports('unknown')
        );
    }

    public function test_empty_loans_export_contains_zero_summary_metrics(): void
    {
        $rows = $this->service->buildRows(
            'loans'
        );

        $this->assertIsArray($rows);

        $totalLoans = null;

        foreach ($rows as $row) {
            if (
                $row['section'] === 'loans'
                && $row['metric'] === 'total_loans'
            ) {
                $totalLoans = $row['value'];
                break;
            }
        }

        $this->assertEquals(
            0,
            $totalLoans
        );
    }

    public function test_empty_overview_export_contains_all_sections(): void
    {
        $rows = $this->service->buildRows(
            'overview'
        );

        $sections = array_values(
            array_unique(
                array_column($rows, 'section')
            )
        );

        sort($sections);

        $this->assertEquals(
            [
                'acquisitions',
                'catalog',
                'fines',
                'incidents',
                'loans',
                'members',
                'reservations',
                'returns',
                'stock_opnames',
            ],
            $sections
        );
    }

    public function test_empty_members_export_contains_total_metric(): void
    {
        $rows = $this->service->buildRows(
            'members'
        );

        $metrics = array_column(
            $rows,
            'metric'
        );

        $this->assertContains(
            'total_members',
            $metrics
        );
    }

    public function test_empty_catalog_export_contains_total_metrics(): void
    {
        $rows = $this->service->buildRows(
            'catalog'
        );

        $metrics = array_column(
            $rows,
            'metric'
        );

        $this->assertContains(
            'total_books',
            $metrics
        );

        $this->assertContains(
            'total_copies',
            $metrics
        );
    }
}
