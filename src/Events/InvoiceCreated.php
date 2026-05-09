<?php

namespace Emad\FeeCollection\Events;

use Emad\FeeCollection\Models\AccountStatement;

class InvoiceCreated
{
    public function __construct(public AccountStatement $statement) {}
}
