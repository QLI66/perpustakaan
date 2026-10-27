<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportingOverviewApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::create([
            'name' => 'Overview Super Admin',
            'email' => 'overview-admin@library.test',
            'password' => Hash::make('password'),
        ]);

        $this->superAdmin->assignRole('SUPER_ADMIN');

        $this->libraryStaff = User::create([
            'name' => 'Overview Library Staff',
            'email' => 'overview-staff@library.test',
            'password' => Hash::make('password'),
        ]);

        $this->libraryStaff->assignRole('LIBRARY_STAFF');

        $this->member = User::create([
            'name' => 'Overview Member',
            'email' => 'overview-member@library.test',
            'password' => Hash::make('password'),
        ]);

        $this->member->assignRole('MEMBER');
    }

    public function test_guest_cannot_access_reporting_overview(): void
    {
        $this->getJson('/api/reports/overview')
            ->assertStatus(401);
    }

    public function test_member_cannot_access_reporting_overview(): void
    {
        $this->actingAs(
            $this->member,
            'sanctum'
        )->getJson('/api/reports/overview')
            ->assertStatus(403);
    }

    public function test_library_staff_can_access_reporting_overview(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/overview')
            ->assertStatus(200);
    }

    public function test_super_admin_can_access_reporting_overview(): void
    {
        $this->actingAs(
            $this->superAdmin,
            'sanctum'
        )->getJson('/api/reports/overview')
            ->assertStatus(200);
    }

    public function test_reporting_overview_returns_expected_structure(): void
    {
        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/overview');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'period' => [
                        'date_from',
                        'date_to',
                    ],
                    'reports' => [
                        'loans',
                        'returns',
                        'fines',
                        'incidents',
                        'acquisitions',
                        'stock_opnames',
                        'reservations',
                        'members',
                        'catalog',
                    ],
                ],
            ]);
    }

    public function test_reporting_overview_returns_empty_zero_sections(): void
    {
        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/overview');

        $response->assertStatus(200);

        $this->assertEquals(
            0,
            $response->json('data.reports.loans.total_loans')
        );

        $this->assertEquals(
            0,
            $response->json('data.reports.members.total_members')
        );

        $this->assertEquals(
            0,
            $response->json('data.reports.catalog.total_books')
        );
    }

    public function test_reporting_overview_can_filter_by_date_range(): void
    {
        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/overview'
            .'?date_from=2026-09-01'
            .'&date_to=2026-09-30'
        );

        $response->assertStatus(200);

        $this->assertEquals(
            '2026-09-01',
            $response->json('data.period.date_from')
        );

        $this->assertEquals(
            '2026-09-30',
            $response->json('data.period.date_to')
        );
    }

    public function test_reporting_overview_rejects_invalid_date_range(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/overview'
            .'?date_from=2026-09-30'
            .'&date_to=2026-09-01'
        )->assertStatus(422);
    }

    public function test_reporting_overview_uses_dashboard_permission(): void
    {
        $this->actingAs(
            $this->member,
            'sanctum'
        )->getJson('/api/reports/overview')
            ->assertStatus(403);
    }
}
