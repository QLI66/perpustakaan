<?php

namespace Database\Seeders;

use App\Models\BookCopy;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Database\Seeder;

class MemberRenewalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where(
            'email',
            'member@perpustakaan.test'
        )->firstOrFail();

        $member = $user->member;

        if (! $member) {
            throw new \RuntimeException(
                'Member record untuk member@perpustakaan.test tidak ditemukan.'
            );
        }

        $activeItem = $member->loans()
            ->with('items.bookCopy.book')
            ->where('status', 'ACTIVE')
            ->whereHas('items', function ($query) {
                $query->where('status', 'ACTIVE')
                    ->where('renewal_count', 0);
            })
            ->first();

        if ($activeItem) {
            $this->command->info(
                'Demo loan aktif dengan renewal_count=0 sudah tersedia.'
            );

            foreach ($activeItem->items as $item) {
                if ($item->status === 'ACTIVE') {
                    $this->command->info(
                        'Loan : '.$activeItem->loan_number
                    );
                    $this->command->info(
                        'Item : '.$item->id
                    );
                    $this->command->info(
                        'Buku : '.($item->bookCopy->book->title ?? '-')
                    );
                    $this->command->info(
                        'Due  : '.($item->due_at?->toDateTimeString() ?? '-')
                    );
                    $this->command->info(
                        'Barcode : '.($item->bookCopy->barcode ?? '-')
                    );
                    break;
                }
            }

            return;
        }

        $copy = BookCopy::with('book')
            ->where('status', 'AVAILABLE')
            ->whereHas('book')
            ->orderBy('id')
            ->first();

        if (! $copy) {
            throw new \RuntimeException(
                'Tidak ada Book Copy dengan status AVAILABLE untuk demo renewal.'
            );
        }

        $loanService = app(LoanService::class);

        $loan = $loanService->createLoan(
            $member->id,
            [$copy->id],
            now(),
            'Demo transaksi untuk pengujian Member Renewal.'
        );

        $loan->load('items.bookCopy.book');

        $item = $loan->items->first();

        $this->command->info(
            'Demo renewal loan berhasil dibuat.'
        );
        $this->command->info(
            'Loan : '.$loan->loan_number
        );
        $this->command->info(
            'Item : '.$item->id
        );
        $this->command->info(
            'Buku : '.($copy->book->title ?? '-')
        );
        $this->command->info(
            'Barcode : '.($copy->barcode ?? '-')
        );
        $this->command->info(
            'Borrowed : '.($item->borrowed_at?->toDateTimeString() ?? '-')
        );
        $this->command->info(
            'Due : '.($item->due_at?->toDateTimeString() ?? '-')
        );
        $this->command->info(
            'Renewal Count : '.$item->renewal_count
        );
    }
}
