<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin Dashboard',
            'email' => 'superadmin-dashboard@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->superAdmin->assignRole('SUPER_ADMIN');

        $this->libraryStaff = User::factory()->create([
            'name' => 'Library Staff Dashboard',
            'email' => 'staff-dashboard@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->libraryStaff->assignRole('LIBRARY_STAFF');

        $this->member = User::factory()->create([
            'name' => 'Member Dashboard',
            'email' => 'member-dashboard@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->member->assignRole('MEMBER');
    }

    // -------------------------------------------------------------------------
    // AUTHENTICATION
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/dashboard/summary');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // AUTHORIZATION
    // -------------------------------------------------------------------------

    public function test_member_cannot_access_dashboard(): void
    {
        $response = $this
            ->actingAs($this->member, 'sanctum')
            ->getJson('/api/dashboard/summary');

        $response->assertStatus(403);
    }

    public function test_library_staff_can_access_dashboard(): void
    {
        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/dashboard/summary');

        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_dashboard(): void
    {
        $response = $this
            ->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/dashboard/summary');

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // RESPONSE
    // -------------------------------------------------------------------------

    public function test_dashboard_returns_expected_response_structure(): void
    {
        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/dashboard/summary');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'totals' => [
                    'books',
                    'book_copies',
                    'members',
                    'active_members',
                    'active_loans',
                    'active_loan_items',
                    'available_book_copies',
                    'borrowed_book_copies',
                    'lost_book_copies',
                    'damaged_book_copies',
                ],
                'loans' => [
                    'active',
                    'overdue_items',
                    'overdue_members',
                ],
                'fines' => [
                    'unpaid_count',
                    'partial_count',
                    'outstanding_count',
                    'outstanding_amount',
                    'paid_count',
                    'total_assessed_amount',
                    'total_paid_amount',
                ],
                'incidents' => [
                    'open',
                ],
                'acquisitions' => [
                    'total',
                    'this_month',
                    'this_year',
                    'this_year_amount',
                ],
                'stock_opnames' => [
                    'total',
                    'draft',
                    'in_progress',
                    'completed',
                    'cancelled',
                    'latest',
                ],
                'periods' => [
                    'today' => [
                        'loans',
                        'returned_items',
                        'fines_assessed',
                        'fines_assessed_amount',
                        'acquisitions',
                        'acquisition_amount',
                        'stock_opnames',
                    ],
                    'this_week' => [
                        'loans',
                        'returned_items',
                        'fines_assessed',
                        'fines_assessed_amount',
                        'acquisitions',
                        'acquisition_amount',
                        'stock_opnames',
                    ],
                    'this_month' => [
                        'loans',
                        'returned_items',
                        'fines_assessed',
                        'fines_assessed_amount',
                        'acquisitions',
                        'acquisition_amount',
                        'stock_opnames',
                    ],
                    'this_year' => [
                        'loans',
                        'returned_items',
                        'fines_assessed',
                        'fines_assessed_amount',
                        'acquisitions',
                        'acquisition_amount',
                        'stock_opnames',
                    ],
                ],
            ],
        ]);
    }

    public function test_empty_database_returns_zero_dashboard_totals(): void
    {
        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/dashboard/summary');

        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.totals.books',
            0
        );

        $response->assertJsonPath(
            'data.totals.book_copies',
            0
        );

        $response->assertJsonPath(
            'data.totals.members',
            0
        );

        $response->assertJsonPath(
            'data.loans.active',
            0
        );

        $response->assertJsonPath(
            'data.loans.overdue_items',
            0
        );

        $response->assertJsonPath(
            'data.fines.unpaid_count',
            0
        );

        $response->assertJsonPath(
            'data.incidents.open',
            0
        );

        $response->assertJsonPath(
            'data.acquisitions.total',
            0
        );

        $response->assertJsonPath(
            'data.stock_opnames.total',
            0
        );

        $response->assertJsonPath(
            'data.stock_opnames.latest',
            null
        );
    }

    // -------------------------------------------------------------------------
    // PERMISSION REGRESSION
    // -------------------------------------------------------------------------

    public function test_library_staff_has_dashboard_permission(): void
    {
        $this->assertTrue(
            $this->libraryStaff->can('dashboard.view')
        );
    }

    public function test_member_does_not_have_dashboard_permission(): void
    {
        $this->assertFalse(
            $this->member->can('dashboard.view')
        );
    }

    public function test_super_admin_has_dashboard_permission(): void
    {
        $this->assertTrue(
            $this->superAdmin->can('dashboard.view')
        );
    }
}
