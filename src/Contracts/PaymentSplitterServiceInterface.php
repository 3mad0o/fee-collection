<?php

namespace Emad\FeeCollection\Contracts;

use Emad\FeeCollection\Models\UpcomingPayment;
use Illuminate\Support\Collection;

interface PaymentSplitterServiceInterface
{
    /**
     * @param array<int, array{amount: float|int|string, due_date: mixed}> $data
     * @return Collection<int, UpcomingPayment>
     */
    public function split(UpcomingPayment $upcomingPayment, array $data = []): Collection;
}
