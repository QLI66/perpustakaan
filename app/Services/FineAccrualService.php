<?php

namespace App\Services;

use App\Models\Fine;
use App\Models\LoanItem;
use App\Support\LoanLateness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FineAccrualService
{
    /**
     * Return non-persisted daily fines currently accruing for a member.
     *
     * @return Collection<int, Fine>
     */
    public function currentForMember(int $memberId): Collection
    {
        return $this->overdueDailyItems()
            ->whereHas('loan', fn (Builder $query) => $query->where('member_id', $memberId))
            ->get()
            ->filter(fn (LoanItem $item) => $item->duration_unit !== 'hour')
            ->map(fn (LoanItem $item) => $this->makeAccruedFine($item))
            ->values();
    }

    public function currentForLoanItem(int $loanItemId): ?Fine
    {
        $item = $this->overdueDailyItems()
            ->whereKey($loanItemId)
            ->first();

        if (! $item || $item->duration_unit === 'hour') {
            return null;
        }

        return $this->makeAccruedFine($item);
    }

    /**
     * Return non-persisted daily fines currently accruing across all active overdue loans.
     *
     * @return Collection<int, Fine>
     */
    public function allCurrent(): Collection
    {
        return $this->overdueDailyItems()
            ->get()
            ->filter(fn (LoanItem $item) => $item->duration_unit !== 'hour')
            ->map(fn (LoanItem $item) => $this->makeAccruedFine($item))
            ->values();
    }

    private function overdueDailyItems(): Builder
    {
        return LoanItem::query()
            ->with(['loan.member', 'bookCopy.book'])
            ->where('status', 'ACTIVE')
            ->where('due_at', '<', now())
            ->whereDoesntHave('fine');
    }

    private function makeAccruedFine(LoanItem $item): Fine
    {
        $lateDays = LoanLateness::lateDays($item->due_at, now());
        $rate = (int) config('library.fine_rate_per_day', 1000);
        $fine = new Fine([
            'loan_item_id' => $item->id,
            'late_days' => $lateDays,
            'rate_per_day' => $rate,
            'late_unit' => 'day',
            'late_units' => $lateDays,
            'rate_per_unit' => $rate,
            'amount' => $lateDays * $rate,
            'amount_paid' => 0,
            'status' => 'UNPAID',
            'assessed_at' => now(),
        ]);
        $fine->setAttribute('is_accruing', true);
        $fine->setRelation('loanItem', $item);

        return $fine;
    }
}
