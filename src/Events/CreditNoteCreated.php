<?php

namespace Emad\FeeCollection\Events;

use Emad\FeeCollection\Models\AccountStatement;

class CreditNoteCreated
{
    public function __construct(public AccountStatement $statement) {}
}
