<?php

namespace App\Services;

use App\Models\BookIncident;
use App\Models\Fine;
use App\Models\FinePayment;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    /**
     * Get summary of finances.
     */
    public function getSummary(?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        // 1. Calculate received money based on ACTUAL payment dates.
        $finePaymentQuery = FinePayment::query();
        if ($dateFrom) {
            $finePaymentQuery->whereDate('paid_at', '>=', $dateFrom->toDateString());
        }
        if ($dateTo) {
            $finePaymentQuery->whereDate('paid_at', '<=', $dateTo->toDateString());
        }
        $totalFinePaid = (float) $finePaymentQuery->sum('amount');

        $incidentQuery = BookIncident::query()
            ->where('status', 'RESOLVED')
            ->where('resolution_type', 'PAID_MONEY');
        if ($dateFrom) {
            $incidentQuery->whereDate('resolved_at', '>=', $dateFrom->toDateString());
        }
        if ($dateTo) {
            $incidentQuery->whereDate('resolved_at', '<=', $dateTo->toDateString());
        }
        $totalIncidentPaid = (float) $incidentQuery->sum('amount_paid');

        $totalReceived = $totalFinePaid + $totalIncidentPaid;

        // 2. Calculate Billed and Outstanding.
        // For these metrics, it usually reflects the current snapshot of the system or everything assessed in the period.
        // To be consistent with "Sisa Denda", we can just return the system's total outstanding state regardless of payment date filter,
        // OR we apply the date filter to assessed_at/opened_at for billed amounts.
        // We will apply the date filter to assessed_at/opened_at for billed/outstanding so it makes sense contextually.

        $fineBilledQuery = Fine::query();
        if ($dateFrom) {
            $fineBilledQuery->whereDate('assessed_at', '>=', $dateFrom->toDateString());
        }
        if ($dateTo) {
            $fineBilledQuery->whereDate('assessed_at', '<=', $dateTo->toDateString());
        }
        $totalFineBilled = (float) $fineBilledQuery->sum('amount');
        $totalFineOutstanding = (float) $fineBilledQuery->clone()->whereIn('status', ['UNPAID', 'PARTIAL'])->sum(DB::raw('amount - amount_paid'));

        $incidentBilledQuery = BookIncident::query()->whereNotNull('amount_due');
        if ($dateFrom) {
            $incidentBilledQuery->whereDate('opened_at', '>=', $dateFrom->toDateString());
        }
        if ($dateTo) {
            $incidentBilledQuery->whereDate('opened_at', '<=', $dateTo->toDateString());
        }
        $totalIncidentBilled = (float) $incidentBilledQuery->sum('amount_due');
        $totalIncidentOutstanding = (float) $incidentBilledQuery->clone()->where('status', 'OPEN')->sum(DB::raw('COALESCE(amount_due, 0) - COALESCE(amount_paid, 0)'));

        return [
            'fines' => [
                'billed' => $totalFineBilled,
                'paid' => $totalFinePaid,
                'outstanding' => $totalFineOutstanding,
            ],
            'incidents' => [
                'billed' => $totalIncidentBilled,
                'paid' => $totalIncidentPaid,
                'outstanding' => $totalIncidentOutstanding,
            ],
            'total_received' => $totalReceived,
            'filters' => [
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
            ],
        ];
    }

    /**
     * Get paginated transactions combining Fine payments and BookIncident payments.
     */
    public function getTransactions(
        int $perPage = 15,
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
        ?string $type = null,
        ?string $search = null
    ): LengthAwarePaginator {
        // Query 1: Fine Payments
        $fines = DB::table('fine_payments')
            ->join('fines', 'fines.id', '=', 'fine_payments.fine_id')
            ->join('loan_items', 'loan_items.id', '=', 'fines.loan_item_id')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->join('members', 'members.id', '=', 'loans.member_id')
            ->join('book_copies', 'book_copies.id', '=', 'loan_items.book_copy_id')
            ->join('books', 'books.id', '=', 'book_copies.book_id')
            ->leftJoin('users', 'users.id', '=', 'fine_payments.received_by')
            ->select([
                'fine_payments.id as id',
                DB::raw("'DENDA' as type"),
                'fine_payments.paid_at as date',
                'members.name as member_name',
                'members.member_number as member_number',
                'books.title as book_title',
                'loans.loan_number as reference',
                'fine_payments.amount as amount',
                'fine_payments.payment_method as payment_method',
                'fine_payments.reference_number as payment_reference',
                'users.name as received_by_name',
            ]);

        // Query 2: Incident Payments
        $incidents = DB::table('book_incidents')
            ->join('loan_items', 'loan_items.id', '=', 'book_incidents.loan_item_id')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->join('members', 'members.id', '=', 'loans.member_id')
            ->join('book_copies', 'book_copies.id', '=', 'book_incidents.book_copy_id')
            ->join('books', 'books.id', '=', 'book_copies.book_id')
            ->leftJoin('users', 'users.id', '=', 'book_incidents.resolved_by')
            ->where('book_incidents.status', 'RESOLVED')
            ->where('book_incidents.resolution_type', 'PAID_MONEY')
            ->select([
                'book_incidents.id as id',
                DB::raw("'GANTI_RUGI' as type"),
                'book_incidents.resolved_at as date',
                'members.name as member_name',
                'members.member_number as member_number',
                'books.title as book_title',
                'loans.loan_number as reference',
                'book_incidents.amount_paid as amount',
                DB::raw("'CASH' as payment_method"),
                DB::raw('NULL as payment_reference'),
                'users.name as received_by_name',
            ]);

        // Apply Date Filters
        if ($dateFrom) {
            $fines->whereDate('fine_payments.paid_at', '>=', $dateFrom->toDateString());
            $incidents->whereDate('book_incidents.resolved_at', '>=', $dateFrom->toDateString());
        }
        if ($dateTo) {
            $fines->whereDate('fine_payments.paid_at', '<=', $dateTo->toDateString());
            $incidents->whereDate('book_incidents.resolved_at', '<=', $dateTo->toDateString());
        }

        // Apply Search Filters
        if ($search) {
            $fines->where(function ($q) use ($search) {
                $q->where('members.name', 'ilike', "%{$search}%")
                    ->orWhere('members.member_number', 'ilike', "%{$search}%")
                    ->orWhere('books.title', 'ilike', "%{$search}%")
                    ->orWhere('loans.loan_number', 'ilike', "%{$search}%");
            });
            $incidents->where(function ($q) use ($search) {
                $q->where('members.name', 'ilike', "%{$search}%")
                    ->orWhere('members.member_number', 'ilike', "%{$search}%")
                    ->orWhere('books.title', 'ilike', "%{$search}%")
                    ->orWhere('loans.loan_number', 'ilike', "%{$search}%");
            });
        }

        if ($type === 'DENDA') {
            $query = $fines;
        } elseif ($type === 'GANTI_RUGI') {
            $query = $incidents;
        } else {
            $query = $fines->unionAll($incidents);
        }

        // Wrap in subquery to allow sorting and pagination on union
        $subquery = DB::table(DB::raw("({$query->toSql()}) as transactions"))
            ->mergeBindings($query)
            ->orderByDesc('date');

        return $subquery->paginate($perPage);
    }
}
