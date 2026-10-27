<?php

namespace Database\Seeders;

use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class LoanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Fetch dependencies
        // Member and BookCopy need to be created first in previous seeders
        $members = Member::orderBy('id')->take(5)->get();
        if ($members->isEmpty()) {
            return;
        }

        $bookCopies = BookCopy::orderBy('id')->take(10)->get();
        if ($bookCopies->count() < 8) {
            return;
        }

        // Transactions structure: [member_index, copy_index, status, days_ago]
        $transactions = [
            // COMPLETED loans (status RETURNED, book copy AVAILABLE)
            ['member' => 0, 'copy' => 0, 'status' => 'COMPLETED', 'days_ago' => 30],
            ['member' => 1, 'copy' => 1, 'status' => 'COMPLETED', 'days_ago' => 20],
            ['member' => 2, 'copy' => 2, 'status' => 'COMPLETED', 'days_ago' => 15],
            ['member' => 3, 'copy' => 3, 'status' => 'COMPLETED', 'days_ago' => 10],

            // ACTIVE loans (status ACTIVE, book copy BORROWED)
            ['member' => 4, 'copy' => 4, 'status' => 'ACTIVE', 'days_ago' => 1],
            ['member' => 0, 'copy' => 5, 'status' => 'ACTIVE', 'days_ago' => 2],
            ['member' => 1, 'copy' => 6, 'status' => 'ACTIVE', 'days_ago' => 1],
            ['member' => 2, 'copy' => 7, 'status' => 'ACTIVE', 'days_ago' => 0],
        ];

        $loanCounter = 1;
        foreach ($transactions as $tx) {
            $member = $members[$tx['member']];
            $copy = $bookCopies[$tx['copy']];
            $loanNumber = 'LOAN-2026-'.str_pad($loanCounter++, 6, '0', STR_PAD_LEFT);
            $borrowedAt = Carbon::now()->subDays($tx['days_ago']);
            $dueAt = clone $borrowedAt;
            $dueAt->addDays(3);

            $loan = Loan::firstOrCreate(
                ['loan_number' => $loanNumber],
                [
                    'member_id' => $member->id,
                    'borrowed_at' => $borrowedAt,
                    'status' => $tx['status'],
                ]
            );

            // Handle Item Status
            $itemStatus = ($tx['status'] === 'COMPLETED') ? 'RETURNED' : 'ACTIVE';
            $returnedAt = ($tx['status'] === 'COMPLETED') ? (clone $borrowedAt)->addDays(2) : null;

            // Create the LoanItem idempotently based on loan_id and book_copy_id
            $item = LoanItem::firstOrCreate(
                [
                    'loan_id' => $loan->id,
                    'book_copy_id' => $copy->id,
                ],
                [
                    'borrowed_at' => $borrowedAt,
                    'due_at' => $dueAt,
                    'returned_at' => $returnedAt,
                    'status' => $itemStatus,
                    'renewal_count' => 0,
                ]
            );

            // Synchronize the BookCopy status consistently (Idempotent for Seeder logic)
            $copyStatus = ($itemStatus === 'RETURNED') ? 'AVAILABLE' : 'BORROWED';
            if ($copy->status !== $copyStatus) {
                $copy->update(['status' => $copyStatus]);
            }
        }
    }
}
