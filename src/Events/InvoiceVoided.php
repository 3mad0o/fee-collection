<?php

namespace Emad\FeeCollection\Events;

use Emad\FeeCollection\Models\AccountStatement;

class InvoiceVoided
{
    public function __construct(public AccountStatement $statement) {}
}
