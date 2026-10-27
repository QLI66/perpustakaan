<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportExportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            RolePermissionSeeder::class
        );

        $this->superAdmin = User::create([
            'name' => 'Export Super Admin',
            'email' => 'export-admin@library.test',
            'password' => Hash::make('password'),
        ]);

        $this->superAdmin->assignRole(
            'SUPER_ADMIN'
        );

        $this->libraryStaff = User::create([
            'name' => 'Export Library Staff',
            'email' => 'export-staff@library.test',
            'password' => Hash::make('password'),
        ]);

        $this->libraryStaff->assignRole(
            'LIBRARY_STAFF'
        );

        $this->member = User::create([
            'name' => 'Export Member',
            'email' => 'export-member@library.test',
            'password' => Hash::make('password'),
        ]);

        $this->member->assignRole(
            'MEMBER'
        );
    }

    public function test_guest_cannot_export_report(): void
    {
        $this->getJson(
            '/api/reports/export/loans'
        )->assertStatus(401);
    }

    public function test_member_cannot_export_report(): void
    {
        $this->actingAs(
            $this->member,
            'sanctum'
        )->get(
            '/api/reports/export/loans'
        )->assertStatus(403);
    }

    public function test_library_staff_can_export_loans(): void
    {
        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->get(
            '/api/reports/export/loans'
        );

        $response->assertStatus(200);

        $this->assertStringContainsString(
            'text/csv',
            (string) $response->headers->get(
                'Content-Type'
            )
        );
    }

    public function test_super_admin_can_export_overview(): void
    {
        $response = $this->actingAs(
            $this->superAdmin,
            'sanctum'
        )->get(
            '/api/reports/export/overview'
        );

        $response->assertStatus(200);

        $this->assertStringContainsString(
            'text/csv',
            (string) $response->headers->get(
                'Content-Type'
            )
        );
    }

    public function test_export_filename_contains_report_name(): void
    {
        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->get(
            '/api/reports/export/catalog'
        );

        $response->assertStatus(200);

        $disposition = (string) $response->headers->get(
            'Content-Disposition'
        );

        $this->assertStringContainsString(
            'catalog-report-',
            $disposition
        );
    }

    public function test_export_can_filter_by_date_range(): void
    {
        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->get(
            '/api/reports/export/members'
            .'?date_from=2026-09-01'
            .'&date_to=2026-09-30'
        );

        $response->assertStatus(200);
    }

    public function test_export_rejects_invalid_date_range(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->get(
            '/api/reports/export/members'
            .'?date_from=2026-09-30'
            .'&date_to=2026-09-01'
        )->assertStatus(422);
    }

    public function test_unknown_export_report_returns_404(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->get(
            '/api/reports/export/unknown'
        )->assertStatus(404);
    }

    public function test_all_supported_reports_can_export(): void
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
            $response = $this->actingAs(
                $this->libraryStaff,
                'sanctum'
            )->get(
                "/api/reports/export/{$report}"
            );

            $response->assertStatus(200);
        }
    }
}
