<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIncident;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Location;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookIncidentSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ambil admin untuk resolved_by
        $admin = User::first();
        if (! $admin) {
            return;
        }

        // 2. Ambil 4 loan items dengan status 'LOST' / 'DAMAGED' untuk dijadikan contoh.
        $member = Member::first();
        if (! $member) {
            return;
        }

        $book = Book::first();
        $location = Location::first();

        if (! $book || ! $location) {
            return;
        }

        $loan = Loan::firstOrCreate(
            ['loan_number' => 'LOAN-INCIDENT-SEED'],
            [
                'member_id' => $member->id,
                'borrowed_at' => now()->subDays(10),
                'status' => 'COMPLETED',
            ]
        );

        $incidentCases = [
            ['type' => 'LOST', 'status' => 'OPEN', 'resolution' => null, 'barcode' => 'INC-SEED-01'],
            ['type' => 'DAMAGED', 'status' => 'OPEN', 'resolution' => null, 'barcode' => 'INC-SEED-02'],
            ['type' => 'LOST', 'status' => 'RESOLVED', 'resolution' => 'PAID_MONEY', 'barcode' => 'INC-SEED-03'],
            ['type' => 'DAMAGED', 'status' => 'RESOLVED', 'resolution' => 'REPLACED_BOOK', 'barcode' => 'INC-SEED-04'],
        ];

        DB::transaction(function () use ($loan, $incidentCases, $admin, $book, $location) {
            foreach ($incidentCases as $case) {
                // Ensure specific BookCopy exists
                $copy = BookCopy::firstOrCreate(
                    ['barcode' => $case['barcode']],
                    [
                        'book_id' => $book->id,
                        'inventory_number' => $case['barcode'],
                        'status' => 'AVAILABLE',
                        'location_id' => $location->id,
                        'acquisition_price' => 50000,
                    ]
                );

                // Check if incident already exists for this book copy to make it idempotent
                if (BookIncident::where('book_copy_id', $copy->id)->exists()) {
                    continue;
                }

                $copy->status = $case['type'];
                $copy->save();

                $loanItem = LoanItem::firstOrCreate(
                    [
                        'loan_id' => $loan->id,
                        'book_copy_id' => $copy->id,
                    ],
                    [
                        'borrowed_at' => now()->subDays(10),
                        'due_at' => now()->subDays(7),
                        'status' => $case['type'],
                    ]
                );

                $incidentData = [
                    'loan_item_id' => $loanItem->id,
                    'book_copy_id' => $copy->id,
                    'type' => $case['type'],
                    'status' => $case['status'],
                    'opened_at' => now()->subDays(5),
                    'amount_due' => $copy->acquisition_price ?? 50000,
                ];

                if ($case['status'] === 'RESOLVED') {
                    $incidentData['resolution_type'] = $case['resolution'];
                    $incidentData['resolved_at'] = now();
                    $incidentData['resolved_by'] = $admin->id;

                    if ($case['resolution'] === 'PAID_MONEY') {
                        $incidentData['amount_paid'] = $incidentData['amount_due'];
                    } elseif ($case['resolution'] === 'REPLACED_BOOK') {
                        // Create a dummy replacement copy that is still AVAILABLE
                        $replacementCopy = BookCopy::create([
                            'book_id' => $copy->book_id,
                            'barcode' => $copy->barcode.'-REP',
                            'inventory_number' => $copy->inventory_number.'-REP',
                            'location_id' => $copy->location_id,
                            'status' => 'AVAILABLE',
                            'acquisition_price' => $copy->acquisition_price,
                        ]);
                        $incidentData['replacement_book_copy_id'] = $replacementCopy->id;
                    }
                }

                BookIncident::create($incidentData);
            }
        });
    }
}
