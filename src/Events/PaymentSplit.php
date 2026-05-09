<?php

namespace Emad\FeeCollection\Events;

use Emad\FeeCollection\Models\UpcomingPayment;
use Illuminate\Support\Collection;

class PaymentSplit
{
    /**
     * @param Collection<int, UpcomingPayment> $children
     */
    public function __construct(public UpcomingPayment $payment, public Collection $children) {}
}
