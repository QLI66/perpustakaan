<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\IdempotencyConflictException;
use App\Models\Fine;
use App\Models\FinePayment;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use JsonException;

class FinePaymentService
{
    /**
     * Process a fine payment.
     *
     * @param  array{amount: numeric|string, payment_method: string, idempotency_key: string, paid_at?: string|null, reference_number?: string|null, notes?: string|null}  $data
     * @return array{payment: FinePayment, replayed: bool}
     *
     * @throws BusinessRuleException
     * @throws IdempotencyConflictException
     * @throws JsonException
     */
    public function recordPayment(int $fineId, array $data): array
    {
        $actorId = Auth::id();
        $idempotencyKey = strtolower($data['idempotency_key']);
        $paymentAmount = (string) $data['amount'];

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', $paymentAmount)) {
            throw new BusinessRuleException('Payment amount must have no more than two decimal places.');
        }

        $paymentAmount = bcadd($paymentAmount, '0', 2);
        $paidAtInput = ! empty($data['paid_at'])
            ? Carbon::parse($data['paid_at'])->utc()
            : null;

        $normalizedPayload = [
            'fine_id' => $fineId,
            'received_by' => $actorId,
            'amount' => $paymentAmount,
            'payment_method' => $data['payment_method'],
            'paid_at' => $paidAtInput?->format('Y-m-d H:i:s'),
            'reference_number' => $this->normalizeOptionalText($data['reference_number'] ?? null),
            'notes' => $this->normalizeOptionalText($data['notes'] ?? null),
        ];
        $requestHash = hash('sha256', json_encode($normalizedPayload, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($fineId, $idempotencyKey, $paymentAmount, $paidAtInput, $normalizedPayload, $requestHash, $actorId) {
                $fine = Fine::lockForUpdate()->findOrFail($fineId);

                $existingPayment = FinePayment::where('idempotency_key', $idempotencyKey)->first();
                if ($existingPayment) {
                    return [
                        'payment' => $this->resolveIdempotencyReplay($existingPayment, $fineId, $actorId, $requestHash),
                        'replayed' => true,
                    ];
                }

                $outstandingAmount = bcsub((string) $fine->amount, (string) $fine->amount_paid, 2);
                if (bccomp($outstandingAmount, '0.00', 2) <= 0) {
                    throw new BusinessRuleException('This fine is already fully paid.');
                }

                if (bccomp($paymentAmount, '0.00', 2) <= 0) {
                    throw new BusinessRuleException('Payment amount must be greater than zero.');
                }

                if (bccomp($paymentAmount, $outstandingAmount, 2) === 1) {
                    throw new BusinessRuleException("Payment amount ({$paymentAmount}) cannot exceed the outstanding balance ({$outstandingAmount}).");
                }

                $paidAt = $paidAtInput ?? now();
                $payment = FinePayment::create([
                    'fine_id' => $fine->id,
                    'amount' => $paymentAmount,
                    'paid_at' => $paidAt,
                    'payment_method' => $normalizedPayload['payment_method'],
                    'reference_number' => $normalizedPayload['reference_number'],
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'notes' => $normalizedPayload['notes'],
                    'received_by' => $actorId,
                ]);

                $newAmountPaid = bcadd((string) $fine->amount_paid, $paymentAmount, 2);
                $fine->amount_paid = $newAmountPaid;

                if (bccomp($newAmountPaid, (string) $fine->amount, 2) === 0) {
                    $fine->status = 'PAID';
                    $fine->paid_at = clone $paidAt;
                } else {
                    $fine->status = 'PARTIAL';
                }

                $fine->save();
                $payment->setRelation('fine', $fine);

                return [
                    'payment' => $payment,
                    'replayed' => false,
                ];
            });
        } catch (QueryException $exception) {
            if (! $this->isIdempotencyKeyUniqueViolation($exception)) {
                throw $exception;
            }

            $existingPayment = FinePayment::where('idempotency_key', $idempotencyKey)->first();
            if (! $existingPayment) {
                throw $exception;
            }

            return [
                'payment' => $this->resolveIdempotencyReplay($existingPayment, $fineId, $actorId, $requestHash),
                'replayed' => true,
            ];
        }
    }

    private function resolveIdempotencyReplay(FinePayment $payment, int $fineId, ?int $actorId, string $requestHash): FinePayment
    {
        if (
            (int) $payment->fine_id !== $fineId
            || ($payment->received_by === null ? null : (int) $payment->received_by) !== $actorId
            || ! hash_equals((string) $payment->request_hash, $requestHash)
        ) {
            throw new IdempotencyConflictException('This idempotency key was already used for a different payment request.');
        }

        return $payment;
    }

    private function isIdempotencyKeyUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();

        return $sqlState === '23505'
            && str_contains($exception->getMessage(), 'fine_payments_idempotency_key_unique');
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        $normalizedValue = trim((string) $value);

        return $normalizedValue === '' ? null : $normalizedValue;
    }
}
