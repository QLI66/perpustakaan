<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Acquisition;
use App\Models\AcquisitionItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AcquisitionService
{
    /**
     * Create a new acquisition transaction.
     *
     * @param array<int, array{
     *     book_id:int,
     *     quantity:int,
     *     unit_price:numeric|null,
     *     notes:string|null
     * }> $items
     */
    public function createAcquisition(
        Carbon $acquisitionDate,
        string $sourceType,
        ?string $sourceName,
        ?string $referenceNumber,
        array $items,
        ?int $createdBy = null,
        ?string $notes = null
    ): Acquisition {
        return DB::transaction(function () use (
            $acquisitionDate,
            $sourceType,
            $sourceName,
            $referenceNumber,
            $items,
            $createdBy,
            $notes
        ) {
            // 1. Validate source type at domain level
            $allowedSourceTypes = [
                'PURCHASE',
                'DONATION',
                'GRANT',
                'GIFT',
                'OTHER',
            ];

            if (! in_array($sourceType, $allowedSourceTypes, true)) {
                throw new BusinessRuleException(
                    'Acquisition source type must be PURCHASE, DONATION, GRANT, GIFT, or OTHER.'
                );
            }

            // 2. At least one acquisition item is required
            if (count($items) === 0) {
                throw new BusinessRuleException(
                    'At least one acquisition item is required.'
                );
            }

            // 3. Validate duplicate books
            $bookIds = array_map(
                fn (array $item): int => (int) $item['book_id'],
                $items
            );

            if (count($bookIds) !== count(array_unique($bookIds))) {
                throw new BusinessRuleException(
                    'The same book cannot be added more than once to one acquisition.'
                );
            }

            // 4. Validate each item
            $totalAmount = 0;
            $hasUnitPrice = false;

            foreach ($items as $item) {
                $quantity = (int) ($item['quantity'] ?? 0);

                if ($quantity <= 0) {
                    throw new BusinessRuleException(
                        'Acquisition item quantity must be greater than zero.'
                    );
                }

                $unitPrice = $item['unit_price'] ?? null;

                if ($unitPrice !== null && (float) $unitPrice < 0) {
                    throw new BusinessRuleException(
                        'Acquisition item unit price cannot be negative.'
                    );
                }

                if ($unitPrice !== null) {
                    $hasUnitPrice = true;
                    $totalAmount += $quantity * (float) $unitPrice;
                }
            }

            // 5. Generate a concurrency-safe acquisition number
            $acquisitionNumber = $this->generateAcquisitionNumber(
                $acquisitionDate
            );

            // 6. Create acquisition header
            $acquisition = Acquisition::create([
                'acquisition_number' => $acquisitionNumber,
                'acquisition_date' => $acquisitionDate,
                'source_type' => $sourceType,
                'source_name' => $sourceName,
                'reference_number' => $referenceNumber,
                'total_amount' => $hasUnitPrice ? $totalAmount : null,
                'created_by' => $createdBy,
                'notes' => $notes,
            ]);

            // 7. Create acquisition items
            foreach ($items as $item) {
                AcquisitionItem::create([
                    'acquisition_id' => $acquisition->id,
                    'book_id' => $item['book_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $acquisition->load('items.book');
        });
    }

    /**
     * Generate a concurrency-safe acquisition number.
     *
     * Format: ACQ-YYYY-XXXXXX
     */
    private function generateAcquisitionNumber(Carbon $acquisitionDate): string
    {
        $year = $acquisitionDate->year;

        DB::statement(
            'SELECT pg_advisory_xact_lock(hashtext(:key))',
            [
                'key' => "acquisition_number_generator_{$year}",
            ]
        );

        $count = Acquisition::whereYear(
            'acquisition_date',
            $year
        )->count();

        $sequence = $count + 1;

        $candidate = sprintf(
            'ACQ-%d-%06d',
            $year,
            $sequence
        );

        while (
            Acquisition::where(
                'acquisition_number',
                $candidate
            )->exists()
        ) {
            $sequence++;

            $candidate = sprintf(
                'ACQ-%d-%06d',
                $year,
                $sequence
            );
        }

        return $candidate;
    }
}
