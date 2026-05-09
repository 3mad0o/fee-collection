<?php

namespace Emad\FeeCollection\Events;

use Emad\FeeCollection\Models\UpcomingPayment;

class PaymentOverdue
{
    public function __construct(public UpcomingPayment $payment) {}
}
