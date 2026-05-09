<?php

namespace Emad\FeeCollection\Events;

use Emad\FeeCollection\Models\AccountStatement;

class ReceiptCreated
{
    public function __construct(public AccountStatement $statement) {}
}
