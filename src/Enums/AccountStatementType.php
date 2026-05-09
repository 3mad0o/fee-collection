<?php

namespace Emad\FeeCollection\Enums;

enum AccountStatementType: string
{

    case INVOICE = 'invoice';
    case RECEIPT = 'receipt';
    case CREDIT_NOTE = 'credit_note';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

}
