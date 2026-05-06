<?php

namespace Emad\FeeCollection\Services;

use Emad\FeeCollection\Contracts\WalletServiceInterface;
use Emad\FeeCollection\Enums\AccountStatementType;
use Emad\FeeCollection\Models\WalletTransaction;
use Emad\FeeCollection\Traits\UseFeeable;
use Illuminate\Database\Eloquent\Model;

class WalletService implements WalletServiceInterface
{
    /**
     * @param Model&UseFeeable $payable
     */
    public function record(
        Model $payable,
        AccountStatementType $type,
        float $amount
    ): WalletTransaction {
        // Wallet semantics: invoice consumes balance, receipt adds balance.
        $signedAmount = $type === AccountStatementType::INVOICE ? -$amount : $amount;
        $wallet = WalletTransaction::query()
            ->where('walletable_id', $payable->getKey())
            ->where('walletable_type', $payable::class)
            ->first();
        $previousBalance = (float) ($wallet?->balance ?? 0);
        $newBalance = $previousBalance + $signedAmount;

        if ($wallet) {
            $wallet->update([
                'balance' => $newBalance,
            ]);

            return $wallet->refresh();
        }

        return WalletTransaction::create([
            'walletable_id' => $payable->getKey(),
            'walletable_type' => $payable::class,
            'balance' => $newBalance,
        ]);
    }

    /**
     * @param Model&UseFeeable $payable
     */
    public function balance(Model $payable): float
    {
        return (float) (WalletTransaction::query()
            ->where('walletable_id', $payable->getKey())
            ->where('walletable_type', $payable::class)
            ->value('balance') ?? 0);
    }
}
