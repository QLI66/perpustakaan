<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIncident;
use App\Models\CollectionType;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FineIncidentReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $memberUser;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin-fi-report@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->superAdmin->assignRole('SUPER_ADMIN');

        $this->libraryStaff = User::create([
            'name' => 'Library Staff',
            'email' => 'staff-fi-report@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->libraryStaff->assignRole('LIBRARY_STAFF');

        $memberType = MemberType::create([
            'code' => 'STD-FIR',
            'name' => 'Student',
        ]);

        $this->memberUser = User::create([
            'name' => 'Member User',
            'email' => 'member-fi-report@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->memberUser->assignRole('MEMBER');

        $this->member = Member::create([
            'user_id' => $this->memberUser->id,
            'member_number' => 'MEM-FIR-001',
            'name' => 'Member Report',
            'member_type_id' => $memberType->id,
            'joined_at' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);
    }

    private function makeLoanItem(string $suffix): LoanItem
    {
        $location = Location::create([
            'code' => 'LOC-FIR-'.$suffix,
            'name' => 'Library '.$suffix,
        ]);

        $collectionType = CollectionType::create([
            'code' => 'COL-FIR-'.$suffix,
            'name' => 'Collection '.$suffix,
        ]);

        $book = Book::create([
            'title' => 'Book '.$suffix,
            'collection_type_id' => $collectionType->id,
        ]);

        $copy = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'BC-FIR-'.$suffix,
            'inventory_number' => 'INV-FIR-'.$suffix,
            'location_id' => $location->id,
            'status' => 'RETURNED',
        ]);

        $loan = Loan::create([
            'loan_number' => 'LOAN-FIR-'.$suffix,
            'member_id' => $this->member->id,
            'borrowed_at' => '2026-09-10 09:00:00',
            'status' => 'COMPLETED',
        ]);

        return LoanItem::create([
            'loan_id' => $loan->id,
            'book_copy_id' => $copy->id,
            'borrowed_at' => '2026-09-10 09:00:00',
            'due_at' => '2026-09-13 00:00:00',
            'returned_at' => '2026-09-15 10:00:00',
            'status' => 'RETURNED',
        ]);
    }

    private function makeFine(
        string $suffix,
        int $amount,
        int $amountPaid,
        string $status,
        string $assessedAt
    ): Fine {
        $item = $this->makeLoanItem($suffix);

        return Fine::create([
            'loan_item_id' => $item->id,
            'late_days' => intdiv($amount, 1000),
            'rate_per_day' => 1000,
            'amount' => $amount,
            'amount_paid' => $amountPaid,
            'status' => $status,
            'assessed_at' => $assessedAt,
            'paid_at' => $status === 'PAID' ? $assessedAt : null,
        ]);
    }

    private function makeIncident(
        string $suffix,
        string $type,
        string $status,
        int $amountDue,
        int $amountPaid,
        string $openedAt
    ): BookIncident {
        $item = $this->makeLoanItem('INC-'.$suffix);

        return BookIncident::create([
            'incident_number' => 'INC-FIR-'.$suffix,
            'book_copy_id' => $item->book_copy_id,
            'loan_item_id' => $item->id,
            'member_id' => $this->member->id,
            'type' => $type,
            'status' => $status,
            'amount_due' => $amountDue,
            'amount_paid' => $amountPaid,
            'opened_at' => $openedAt,
            'reported_at' => $openedAt,
            'reported_by' => null,
            'resolution_type' => $status === 'RESOLVED' ? 'PAID_MONEY' : null,
            'resolved_at' => $status === 'RESOLVED' ? $openedAt : null,
            'resolved_by' => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // AUTHENTICATION
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_fine_report(): void
    {
        $this->getJson('/api/reports/fines')
            ->assertStatus(401);
    }

    public function test_guest_cannot_access_incident_report(): void
    {
        $this->getJson('/api/reports/incidents')
            ->assertStatus(401);
    }

    public function test_member_cannot_access_fine_report(): void
    {
        $this->actingAs($this->memberUser, 'sanctum')
            ->getJson('/api/reports/fines')
            ->assertStatus(403);
    }

    public function test_member_cannot_access_incident_report(): void
    {
        $this->actingAs($this->memberUser, 'sanctum')
            ->getJson('/api/reports/incidents')
            ->assertStatus(403);
    }

    public function test_library_staff_can_access_fine_report(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/fines')
            ->assertStatus(200);
    }

    public function test_library_staff_can_access_incident_report(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/incidents')
            ->assertStatus(200);
    }

    public function test_super_admin_can_access_fine_report(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/reports/fines')
            ->assertStatus(200);
    }

    public function test_super_admin_can_access_incident_report(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/reports/incidents')
            ->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // FINE REPORT
    // -------------------------------------------------------------------------

    public function test_fine_report_returns_expected_structure(): void
    {
        $this->makeFine(
            'FRS1',
            1000,
            0,
            'UNPAID',
            '2026-09-20 10:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/fines');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'summary' => [
                        'total_fines',
                        'unpaid_count',
                        'partial_count',
                        'paid_count',
                        'total_amount',
                        'total_amount_paid',
                        'total_outstanding',
                        'period_fine_count',
                        'period_fine_amount',
                    ],
                    'filters_applied' => [
                        'date_from',
                        'date_to',
                        'member_id',
                        'status',
                    ],
                ],
            ]);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_fines')
        );

        $this->assertEquals(
            1000,
            $response->json('data.summary.total_outstanding')
        );
    }

    public function test_fine_report_can_filter_by_status(): void
    {
        $this->makeFine(
            'FRS2',
            1000,
            0,
            'UNPAID',
            '2026-09-01 10:00:00'
        );

        $this->makeFine(
            'FRS3',
            2000,
            2000,
            'PAID',
            '2026-09-02 10:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/fines?status=UNPAID');

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_fines')
        );

        $this->assertEquals(
            'UNPAID',
            $response->json('data.filters_applied.status')
        );
    }

    public function test_fine_report_can_filter_by_member(): void
    {
        $this->makeFine(
            'FRM1',
            1000,
            0,
            'UNPAID',
            '2026-09-01 10:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson(
                '/api/reports/fines?member_id='.$this->member->id
            );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_fines')
        );

        $this->assertEquals(
            $this->member->id,
            $response->json('data.filters_applied.member_id')
        );
    }

    public function test_fine_report_can_filter_by_date_range(): void
    {
        $this->makeFine(
            'FRD1',
            1000,
            0,
            'UNPAID',
            '2026-08-31 10:00:00'
        );

        $this->makeFine(
            'FRD2',
            2000,
            1000,
            'PARTIAL',
            '2026-09-10 10:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson(
                '/api/reports/fines'
                .'?date_from=2026-09-01'
                .'&date_to=2026-09-30'
            );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_fines')
        );

        $this->assertEquals(
            2000,
            $response->json('data.summary.total_amount')
        );
    }

    // -------------------------------------------------------------------------
    // INCIDENT REPORT
    // -------------------------------------------------------------------------

    public function test_incident_report_returns_expected_structure(): void
    {
        $this->makeIncident(
            'IR1',
            'LOST',
            'OPEN',
            100000,
            0,
            '2026-09-20 10:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/incidents');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'summary' => [
                        'total_incidents',
                        'open_count',
                        'resolved_count',
                        'lost_count',
                        'damaged_count',
                        'total_amount_due',
                        'total_amount_paid',
                        'total_outstanding',
                        'period_opened_count',
                    ],
                    'filters_applied' => [
                        'date_from',
                        'date_to',
                        'member_id',
                        'type',
                        'status',
                    ],
                ],
            ]);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_incidents')
        );

        $this->assertEquals(
            100000,
            $response->json('data.summary.total_outstanding')
        );
    }

    public function test_incident_report_can_filter_by_type(): void
    {
        $this->makeIncident(
            'IRT1',
            'LOST',
            'OPEN',
            100000,
            0,
            '2026-09-01 10:00:00'
        );

        $this->makeIncident(
            'IRT2',
            'DAMAGED',
            'OPEN',
            50000,
            0,
            '2026-09-02 10:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/incidents?type=LOST');

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_incidents')
        );

        $this->assertEquals(
            'LOST',
            $response->json('data.filters_applied.type')
        );
    }

    public function test_incident_report_can_filter_by_status(): void
    {
        $this->makeIncident(
            'IRS1',
            'LOST',
            'OPEN',
            100000,
            0,
            '2026-09-01 10:00:00'
        );

        $this->makeIncident(
            'IRS2',
            'DAMAGED',
            'RESOLVED',
            50000,
            50000,
            '2026-09-02 10:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/incidents?status=RESOLVED');

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_incidents')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.resolved_count')
        );
    }

    public function test_incident_report_can_filter_by_member(): void
    {
        $this->makeIncident(
            'IRM1',
            'LOST',
            'OPEN',
            100000,
            0,
            '2026-09-01 10:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson(
                '/api/reports/incidents?member_id='.$this->member->id
            );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_incidents')
        );

        $this->assertEquals(
            $this->member->id,
            $response->json('data.filters_applied.member_id')
        );
    }

    public function test_incident_report_can_filter_by_date_range(): void
    {
        $this->makeIncident(
            'IRD1',
            'LOST',
            'OPEN',
            100000,
            0,
            '2026-08-31 10:00:00'
        );

        $this->makeIncident(
            'IRD2',
            'DAMAGED',
            'OPEN',
            50000,
            0,
            '2026-09-10 10:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson(
                '/api/reports/incidents'
                .'?date_from=2026-09-01'
                .'&date_to=2026-09-30'
            );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_incidents')
        );

        $this->assertEquals(
            50000,
            $response->json('data.summary.total_amount_due')
        );
    }

    // -------------------------------------------------------------------------
    // VALIDATION
    // -------------------------------------------------------------------------

    public function test_fine_report_rejects_invalid_status(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/fines?status=INVALID')
            ->assertStatus(422);
    }

    public function test_incident_report_rejects_invalid_type(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/incidents?type=INVALID')
            ->assertStatus(422);
    }

    public function test_incident_report_rejects_invalid_status(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/incidents?status=INVALID')
            ->assertStatus(422);
    }

    public function test_fine_report_rejects_invalid_date_range(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson(
                '/api/reports/fines'
                .'?date_from=2026-09-30'
                .'&date_to=2026-09-01'
            )
            ->assertStatus(422);
    }

    public function test_incident_report_rejects_invalid_date_range(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson(
                '/api/reports/incidents'
                .'?date_from=2026-09-30'
                .'&date_to=2026-09-01'
            )
            ->assertStatus(422);
    }

    public function test_fine_report_rejects_unknown_member(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/fines?member_id=999999')
            ->assertStatus(422);
    }

    public function test_incident_report_rejects_unknown_member(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/incidents?member_id=999999')
            ->assertStatus(422);
    }
}
