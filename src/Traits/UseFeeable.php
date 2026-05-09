<?php

namespace Emad\FeeCollection\Traits;

use Carbon\Carbon;
use Emad\FeeCollection\Contracts\AccountStatementServiceInterface;
use Emad\FeeCollection\Enums\AccountStatementType;
use Emad\FeeCollection\FeeCollectionException;
use Emad\FeeCollection\Models\AccountStatement;
use Emad\FeeCollection\Models\UpcomingPayment;
use Emad\FeeCollection\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;


/**
 * @mixin Model
 */
trait UseFeeable
{
    /**
     * Register a payable upcoming payment item.
     */
    public function registerPayment(float $amount, Carbon $due_date): UpcomingPayment
    {
        if (!$this->exists || $this->getKey() === null) {
            throw new FeeCollectionException('No Fee Collection Registered');
        }

        $upcomingPayment = UpcomingPayment::create([
            'amount' => $amount,
            'remaining_amount' => $amount,
            'due_date' => $due_date,
            'payable_id' => $this->getKey(),
            'payable_type' => get_class($this),
        ]);

        // If wallet has enough credit, consume it immediately via invoice.
        if (config('fee_collection.auto_invoice_on_receipt', true) && $this->balance() >= $amount) {
            $upcomingPayment->createInvoice('Auto-generated invoice from wallet balance', now());
            $upcomingPayment->update([
                'remaining_amount' => 0,
            ]);
        }

        return $upcomingPayment;
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(UpcomingPayment::class, 'payable');
    }

    public function accountStatements(): MorphMany
    {
        return $this->morphMany(AccountStatement::class, 'accountable');
    }

    public function wallet(): MorphOne
    {
        return $this->morphOne(WalletTransaction::class, 'walletable');
    }

    /**
     * Current balance calculated from wallet transactions when available.
     */
    public function balance()
    {
        $walletBalance = $this->wallet()->value('balance');
        if ($walletBalance !== null) {
            return (float) $walletBalance;
        }

        $debit = $this->accountStatements()->sum('debit');
        $credit = $this->accountStatements()->sum('credit');

        return (float) ($debit - $credit);
    }

    public function lastInvoiceNumber(): int
    {
        return (int) ($this->accountStatements()
            ->whereType(AccountStatementType::INVOICE)
            ->max('number') ?? 0);
    }

    public function lastReceiptNumber(): int
    {
        return (int) ($this->accountStatements()
            ->whereType(AccountStatementType::RECEIPT)
            ->max('number') ?? 0);
    }

    public function overduePayments(): \Illuminate\Support\Collection
    {
        return $this->payments()
            ->where('due_date', '<', today())
            ->where('remaining_amount', '>', 0)
            ->get()
            ->filter(fn (UpcomingPayment $payment): bool => $payment->isOverdue())
            ->values();
    }

    public function generateDueInvoices(?Carbon $date = null): \Illuminate\Support\Collection
    {
        return app(AccountStatementServiceInterface::class)->generateDueInvoices($this, $date);
    }

    public function createReceipt(float $amount, string $description, ?Carbon $date = null, $document = null, ?bool $autoInvoice = null): AccountStatement
    {
        return app(AccountStatementServiceInterface::class)->createReceipt($this, $amount, $description, $date, $document, $autoInvoice);
    }

}
