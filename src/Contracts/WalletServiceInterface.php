<?php

namespace Emad\FeeCollection\Contracts;

use Emad\FeeCollection\Enums\AccountStatementType;
use Emad\FeeCollection\Models\WalletTransaction;
use Emad\FeeCollection\Traits\UseFeeable;
use Illuminate\Database\Eloquent\Model;

interface WalletServiceInterface
{
    /**
     * @param Model&UseFeeable $payable
     */
    public function record(
        Model $payable,
        AccountStatementType $type,
        float $amount
    ): WalletTransaction;

    /**
     * @param Model&UseFeeable $payable
     */
    public function balance(Model $payable): float;
}
