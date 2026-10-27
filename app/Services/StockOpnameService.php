<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\BookCopy;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StockOpnameService
{
    private const STATUS_DRAFT = 'DRAFT';

    private const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    private const STATUS_COMPLETED = 'COMPLETED';

    private const STATUS_CANCELLED = 'CANCELLED';

    private const RESULT_NOT_CHECKED = 'NOT_CHECKED';

    private const RESULT_FOUND = 'FOUND';

    private const RESULT_MISSING = 'MISSING';

    private const RESULT_DIFFERENT = 'DIFFERENT';

    private const ALLOWED_STATUS = [
        'AVAILABLE',
        'BORROWED',
        'RESERVED',
        'LOST',
        'DAMAGED',
    ];

    private const ALLOWED_CONDITION = [
        'NEW',
        'GOOD',
        'FAIR',
        'POOR',
    ];

    /**
     * Create a new stock opname in DRAFT status.
     *
     * Book copies are intentionally NOT snapshotted here.
     * The snapshot is created when the opname is started.
     */
    public function createStockOpname(array $data, ?int $createdBy = null): StockOpname
    {
        return DB::transaction(function () use ($data, $createdBy): StockOpname {
            if (empty($data['opname_date'])) {
                throw new BusinessRuleException('Opname date is required.');
            }

            $opnameDate = Carbon::parse($data['opname_date'])->toDateString();

            $locationId = array_key_exists('location_id', $data)
                && $data['location_id'] !== null
                ? (int) $data['location_id']
                : null;

            $opnameNumber = $this->generateOpnameNumber($opnameDate);

            $opname = StockOpname::create([
                'opname_number' => $opnameNumber,
                'opname_date' => $opnameDate,
                'location_id' => $locationId,
                'status' => self::STATUS_DRAFT,
                'created_by' => $createdBy,
                'notes' => $data['notes'] ?? null,
            ]);

            return $opname->load([
                'location',
                'creator',
            ]);
        });
    }

    /**
     * Update DRAFT stock opname metadata.
     */
    public function updateStockOpname(int $id, array $data): StockOpname
    {
        return DB::transaction(function () use ($id, $data): StockOpname {
            $opname = StockOpname::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $this->ensureDraft($opname);

            $updates = [];

            if (
                array_key_exists('opname_date', $data)
                && $data['opname_date'] !== null
            ) {
                $updates['opname_date'] = Carbon::parse(
                    $data['opname_date']
                )->toDateString();
            }

            if (array_key_exists('location_id', $data)) {
                $updates['location_id'] = $data['location_id'] !== null
                    ? (int) $data['location_id']
                    : null;
            }

            if (array_key_exists('notes', $data)) {
                $updates['notes'] = $data['notes'];
            }

            if ($updates !== []) {
                $opname->update($updates);
            }

            return $opname->fresh()->load([
                'location',
                'creator',
                'items.bookCopy.book',
            ]);
        });
    }

    /**
     * Start a DRAFT stock opname.
     *
     * At this point the current BookCopy inventory is snapshotted.
     */
    public function startStockOpname(
        int $id,
        ?Carbon $startedAt = null
    ): StockOpname {
        return DB::transaction(function () use ($id, $startedAt): StockOpname {
            $opname = StockOpname::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $this->ensureStatus(
                $opname,
                self::STATUS_DRAFT,
                'Only a DRAFT stock opname can be started.'
            );

            $copyQuery = BookCopy::query()
                ->orderBy('id')
                ->lockForUpdate();

            if ($opname->location_id !== null) {
                $copyQuery->where(
                    'location_id',
                    $opname->location_id
                );
            }

            $copies = $copyQuery->get([
                'id',
                'status',
                'condition',
            ]);

            if ($copies->isEmpty()) {
                throw new BusinessRuleException(
                    'No book copies found for the selected stock opname scope.'
                );
            }

            foreach ($copies as $copy) {
                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'book_copy_id' => $copy->id,
                    'expected_status' => $copy->status,
                    'expected_condition' => $copy->condition,
                    'result' => self::RESULT_NOT_CHECKED,
                ]);
            }

            $opname->update([
                'status' => self::STATUS_IN_PROGRESS,
                'started_at' => (
                    $startedAt ?? now()
                )->toDateTimeString(),
            ]);

            return $opname->fresh()->load([
                'location',
                'creator',
                'items.bookCopy.book',
                'items.bookCopy.location',
            ]);
        });
    }

    /**
     * Check or re-check one stock opname item.
     *
     * The BookCopy itself is NOT modified.
     * Stock opname acts as an audit/snapshot.
     */
    public function checkItem(
        int $itemId,
        array $data,
        ?int $checkedBy = null
    ): StockOpnameItem {
        return DB::transaction(
            function () use (
                $itemId,
                $data,
                $checkedBy
            ): StockOpnameItem {
                $item = StockOpnameItem::query()
                    ->lockForUpdate()
                    ->findOrFail($itemId);

                $opname = StockOpname::query()
                    ->lockForUpdate()
                    ->findOrFail($item->stock_opname_id);

                $this->ensureStatus(
                    $opname,
                    self::STATUS_IN_PROGRESS,
                    'Only an IN_PROGRESS stock opname can be checked.'
                );

                $result = $data['result'] ?? null;
                $actualStatus = $data['actual_status'] ?? null;
                $actualCondition = $data['actual_condition'] ?? null;

                $this->validateCheckResult(
                    $item,
                    $result,
                    $actualStatus,
                    $actualCondition
                );

                $item->update([
                    'actual_status' => $actualStatus,
                    'actual_condition' => $actualCondition,
                    'result' => $result,
                    'checked_at' => (
                        isset($data['checked_at'])
                        && $data['checked_at'] !== null
                    )
                        ? Carbon::parse(
                            $data['checked_at']
                        )->toDateTimeString()
                        : now()->toDateTimeString(),
                    'checked_by' => $checkedBy,
                    'notes' => $data['notes'] ?? null,
                ]);

                return $item->fresh()->load([
                    'stockOpname',
                    'bookCopy.book',
                    'bookCopy.location',
                    'checkedBy',
                ]);
            }
        );
    }

    /**
     * Complete an IN_PROGRESS stock opname.
     *
     * Every item must already be checked.
     */
    public function completeStockOpname(
        int $id,
        ?Carbon $completedAt = null
    ): StockOpname {
        return DB::transaction(function () use (
            $id,
            $completedAt
        ): StockOpname {
            $opname = StockOpname::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $this->ensureStatus(
                $opname,
                self::STATUS_IN_PROGRESS,
                'Only an IN_PROGRESS stock opname can be completed.'
            );

            $hasUncheckedItems = $opname->items()
                ->where(
                    'result',
                    self::RESULT_NOT_CHECKED
                )
                ->exists();

            if ($hasUncheckedItems) {
                throw new BusinessRuleException(
                    'All stock opname items must be checked before completion.'
                );
            }

            $opname->update([
                'status' => self::STATUS_COMPLETED,
                'completed_at' => (
                    $completedAt ?? now()
                )->toDateTimeString(),
            ]);

            return $opname->fresh()->load([
                'location',
                'creator',
                'items.bookCopy.book',
                'items.bookCopy.location',
                'items.checkedBy',
            ]);
        });
    }

    /**
     * Cancel DRAFT or IN_PROGRESS stock opname.
     */
    public function cancelStockOpname(int $id): StockOpname
    {
        return DB::transaction(function () use ($id): StockOpname {
            $opname = StockOpname::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if (! in_array(
                $opname->status,
                [
                    self::STATUS_DRAFT,
                    self::STATUS_IN_PROGRESS,
                ],
                true
            )) {
                throw new BusinessRuleException(
                    'Only a DRAFT or IN_PROGRESS stock opname can be cancelled.'
                );
            }

            $opname->update([
                'status' => self::STATUS_CANCELLED,
            ]);

            return $opname->fresh()->load([
                'location',
                'creator',
            ]);
        });
    }

    /**
     * Delete only a DRAFT stock opname.
     */
    public function deleteStockOpname(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $opname = StockOpname::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $this->ensureStatus(
                $opname,
                self::STATUS_DRAFT,
                'Only a DRAFT stock opname can be deleted.'
            );

            $opname->delete();
        });
    }

    private function ensureDraft(StockOpname $opname): void
    {
        $this->ensureStatus(
            $opname,
            self::STATUS_DRAFT,
            'Only a DRAFT stock opname can be updated.'
        );
    }

    private function ensureStatus(
        StockOpname $opname,
        string $expectedStatus,
        string $message
    ): void {
        if ($opname->status !== $expectedStatus) {
            throw new BusinessRuleException($message);
        }
    }

    private function validateCheckResult(
        StockOpnameItem $item,
        ?string $result,
        ?string $actualStatus,
        ?string $actualCondition,
    ): void {
        if (! in_array(
            $result,
            [
                self::RESULT_FOUND,
                self::RESULT_MISSING,
                self::RESULT_DIFFERENT,
            ],
            true
        )) {
            throw new BusinessRuleException(
                'Invalid stock opname result.'
            );
        }

        if (
            $actualStatus !== null
            && ! in_array(
                $actualStatus,
                self::ALLOWED_STATUS,
                true
            )
        ) {
            throw new BusinessRuleException(
                'Invalid actual book copy status.'
            );
        }

        if (
            $actualCondition !== null
            && ! in_array(
                $actualCondition,
                self::ALLOWED_CONDITION,
                true
            )
        ) {
            throw new BusinessRuleException(
                'Invalid actual book copy condition.'
            );
        }

        /*
         * FOUND:
         * actual status and condition must both be present
         * and must exactly match the snapshot.
         */
        if ($result === self::RESULT_FOUND) {
            if (
                $actualStatus === null
                || $actualCondition === null
            ) {
                throw new BusinessRuleException(
                    'FOUND result requires actual status and actual condition.'
                );
            }

            if (
                $actualStatus !== $item->expected_status
                || $actualCondition !== $item->expected_condition
            ) {
                throw new BusinessRuleException(
                    'FOUND result requires actual status and condition to match the expected values.'
                );
            }

            return;
        }

        /*
         * MISSING:
         * the physical copy was not found, therefore actual values
         * remain NULL.
         */
        if ($result === self::RESULT_MISSING) {
            if (
                $actualStatus !== null
                || $actualCondition !== null
            ) {
                throw new BusinessRuleException(
                    'MISSING result cannot have actual status or actual condition.'
                );
            }

            return;
        }

        /*
         * DIFFERENT:
         * at least one actual value must be different from expected.
         */
        $hasDifference = (
            $actualStatus !== null
            && $actualStatus !== $item->expected_status
        ) || (
            $actualCondition !== null
            && $actualCondition !== $item->expected_condition
        );

        if (! $hasDifference) {
            throw new BusinessRuleException(
                'DIFFERENT result requires at least one actual value to differ from the expected value.'
            );
        }
    }

    /**
     * Generate:
     * OPN-YYYY-000001
     * OPN-YYYY-000002
     * etc.
     *
     * PostgreSQL advisory transaction lock prevents duplicate
     * numbers when multiple requests happen concurrently.
     */
    private function generateOpnameNumber(string $opnameDate): string
    {
        $year = Carbon::parse($opnameDate)->year;

        $lockKey = sprintf(
            '%u',
            crc32("stock-opname-number:{$year}")
        );

        DB::select(
            'SELECT pg_advisory_xact_lock(?)',
            [$lockKey]
        );

        $row = DB::selectOne(
            '
            SELECT COALESCE(
                MAX(
                    CAST(
                        RIGHT(opname_number, 6)
                        AS INTEGER
                    )
                ),
                0
            ) AS max_sequence
            FROM stock_opnames
            WHERE opname_number LIKE ?
            ',
            [
                "OPN-{$year}-%",
            ]
        );

        $nextSequence = (
            (int) ($row->max_sequence ?? 0)
        ) + 1;

        if ($nextSequence > 999999) {
            throw new BusinessRuleException(
                'Stock opname number sequence is exhausted for the current year.'
            );
        }

        return sprintf(
            'OPN-%d-%06d',
            $year,
            $nextSequence
        );
    }
}
