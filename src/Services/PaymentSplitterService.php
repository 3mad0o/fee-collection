<?php

namespace Emad\FeeCollection\Services;

use Carbon\Carbon;
use Emad\FeeCollection\Contracts\PaymentSplitterServiceInterface;
use Emad\FeeCollection\FeeCollectionException;
use Emad\FeeCollection\Models\UpcomingPayment;
use Illuminate\Support\Collection;

class PaymentSplitterService implements PaymentSplitterServiceInterface
{
    /**
     * @param array<int, array{amount: float|int|string, due_date: Carbon|string}> $data
     */
    public function split(UpcomingPayment $upcomingPayment, array $data = []): Collection
    {
        $this->validate($upcomingPayment, $data);
        $dataToSplit = collect($data)
            ->map(function (array $value) use ($upcomingPayment): array {
                return [
                    'due_date' => $value['due_date'],
                    'amount' => $value['amount'],
                    'remaining_amount' => $value['amount'],
                    'payable_id' => $upcomingPayment->payable_id,
                    'payable_type' => $upcomingPayment->payable_type,
                    'split_parent_id' => $upcomingPayment->id,
                ];
            })
            ->values()
            ->all();

        if ($dataToSplit === []) {
            throw new FeeCollectionException('Split data cannot be empty');
        }

        $upcomingPayment->update(['remaining_amount' => 0]);

        return $upcomingPayment->children()->createMany($dataToSplit);
    }

    /**
     * @param array<int, array{amount: float|int|string, due_date: Carbon|string}> $data
     */
    public function validate(UpcomingPayment $upcomingPayment, array $data = []): void
    {
        foreach ($data as $index => $item) {
            if (!is_array($item)) {
                throw new FeeCollectionException("Item at index {$index} must be an array.");
            }

            if (!array_key_exists('amount', $item) || !array_key_exists('due_date', $item)) {
                throw new FeeCollectionException("Item at index {$index} must have amount and due_date.");
            }

            if (!is_numeric($item['amount'])) {
                throw new FeeCollectionException("Amount at index {$index} must be numeric.");
            }

            if (!$item['due_date'] instanceof Carbon && !is_string($item['due_date'])) {
                throw new FeeCollectionException("due_date at index {$index} must be a Carbon instance or date string.");
            }
        }
        $sumPayments = collect($data)->sum('amount');
        if ((float) $sumPayments <= 0 || (float) $sumPayments !== (float) $upcomingPayment->amount) {
            throw new FeeCollectionException('Amount Is Not Valid');
        }
    }
}
