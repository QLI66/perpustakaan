<?php

namespace Database\Seeders;

use App\Models\Fine;
use App\Models\FinePayment;
use App\Models\LoanItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Fetch dependencies
        // Get RETURNED loan items
        $loanItems = LoanItem::where('status', 'RETURNED')->orderBy('id')->take(4)->get();
        if ($loanItems->count() < 4) {
            return;
        }

        $staff = User::role('LIBRARY_STAFF')->first();

        // Data scenarios:
        // [item_index, late_days, status]
        // 0 -> UNPAID (late 3 days)
        // 1 -> UNPAID (late 5 days)
        // 2 -> PAID (late 2 days)
        // 3 -> PARTIAL (late 4 days)
        $scenarios = [
            ['item' => 0, 'late' => 3, 'status' => 'UNPAID'],
            ['item' => 1, 'late' => 5, 'status' => 'UNPAID'],
            ['item' => 2, 'late' => 2, 'status' => 'PAID'],
            ['item' => 3, 'late' => 4, 'status' => 'PARTIAL'],
        ];

        foreach ($scenarios as $sc) {
            $item = $loanItems[$sc['item']];

            // Domain logic simulation for seeding
            $lateDays = $sc['late'];
            $rate = 1000.00;
            $amount = $lateDays * $rate;

            $amountPaid = 0;
            $paidAt = null;

            if ($sc['status'] === 'PAID') {
                $amountPaid = $amount;
                $paidAt = Carbon::now()->subDays(1);
            } elseif ($sc['status'] === 'PARTIAL') {
                $amountPaid = $amount / 2; // pay half
            }

            // Create fine idempotently
            $fine = Fine::firstOrCreate(
                ['loan_item_id' => $item->id],
                [
                    'late_days' => $lateDays,
                    'rate_per_day' => $rate,
                    'amount' => $amount,
                    'amount_paid' => $amountPaid,
                    'status' => $sc['status'],
                    'assessed_at' => clone $item->returned_at,
                    'paid_at' => $paidAt,
                ]
            );

            // Create payments if applicable
            if ($amountPaid > 0 && $fine->payments()->count() === 0) {
                FinePayment::create([
                    'fine_id' => $fine->id,
                    'amount' => $amountPaid,
                    'paid_at' => $paidAt ?? Carbon::now()->subDays(2),
                    'payment_method' => 'CASH',
                    'received_by' => $staff ? $staff->id : null,
                    'reference_number' => 'REF-FINE-'.$fine->id,
                ]);
            }
        }
    }
}
