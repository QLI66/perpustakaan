<?php

namespace Tests\Unit;

use App\Services\ReportingOverviewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingOverviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportingOverviewService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReportingOverviewService::class);

        Carbon::setTestNow('2026-09-23 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_empty_overview_returns_all_report_sections(): void
    {
        $result = $this->service->getOverview();

        $this->assertArrayHasKey(
            'period',
            $result
        );

        $this->assertArrayHasKey(
            'reports',
            $result
        );

        $this->assertArrayHasKey(
            'loans',
            $result['reports']
        );

        $this->assertArrayHasKey(
            'returns',
            $result['reports']
        );

        $this->assertArrayHasKey(
            'fines',
            $result['reports']
        );

        $this->assertArrayHasKey(
            'incidents',
            $result['reports']
        );

        $this->assertArrayHasKey(
            'acquisitions',
            $result['reports']
        );

        $this->assertArrayHasKey(
            'stock_opnames',
            $result['reports']
        );

        $this->assertArrayHasKey(
            'reservations',
            $result['reports']
        );

        $this->assertArrayHasKey(
            'members',
            $result['reports']
        );

        $this->assertArrayHasKey(
            'catalog',
            $result['reports']
        );
    }

    public function test_empty_overview_contains_zero_loan_total(): void
    {
        $result = $this->service->getOverview();

        $this->assertEquals(
            0,
            $result['reports']['loans']['total_loans']
        );
    }

    public function test_empty_overview_contains_zero_return_total(): void
    {
        $result = $this->service->getOverview();

        $this->assertEquals(
            0,
            $result['reports']['returns']['total_returned_items']
        );
    }

    public function test_empty_overview_contains_zero_fine_total(): void
    {
        $result = $this->service->getOverview();

        $this->assertEquals(
            0,
            $result['reports']['fines']['total_fines']
        );
    }

    public function test_empty_overview_contains_zero_incident_total(): void
    {
        $result = $this->service->getOverview();

        $this->assertEquals(
            0,
            $result['reports']['incidents']['total_incidents']
        );
    }

    public function test_empty_overview_contains_zero_acquisition_total(): void
    {
        $result = $this->service->getOverview();

        $this->assertEquals(
            0,
            $result['reports']['acquisitions']['total_acquisitions']
        );
    }

    public function test_empty_overview_contains_zero_reservation_total(): void
    {
        $result = $this->service->getOverview();

        $this->assertEquals(
            0,
            $result['reports']['reservations']['total_reservations']
        );
    }

    public function test_empty_overview_contains_zero_member_total(): void
    {
        $result = $this->service->getOverview();

        $this->assertEquals(
            0,
            $result['reports']['members']['total_members']
        );
    }

    public function test_empty_overview_contains_zero_catalog_totals(): void
    {
        $result = $this->service->getOverview();

        $this->assertEquals(
            0,
            $result['reports']['catalog']['total_books']
        );

        $this->assertEquals(
            0,
            $result['reports']['catalog']['total_copies']
        );
    }

    public function test_overview_applies_period_to_all_period_based_reports(): void
    {
        $result = $this->service->getOverview(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        );

        $this->assertEquals(
            '2026-09-01',
            $result['period']['date_from']
        );

        $this->assertEquals(
            '2026-09-30',
            $result['period']['date_to']
        );
    }
}
