<?php

namespace Emad\FeeCollection\Enums;

enum AccountStatementStatus: string
{
    case Issued = 'issued';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Credited = 'credited';
    case Voided = 'voided';
}
