<?php

namespace Emad\FeeCollection\Contracts;

use Carbon\Carbon;
use Emad\FeeCollection\Models\AccountStatement;
use Emad\FeeCollection\Models\UpcomingPayment;
use Emad\FeeCollection\Traits\UseFeeable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface AccountStatementServiceInterface
{
    public function createInvoice(
        UpcomingPayment $upcomingPayment,
        string $description,
        ?Carbon $date = null,
        ?string $document = null
    ): AccountStatement;

    public function createUpcomingPaymentReceipt(
        UpcomingPayment $upcomingPayment,
        string $description,
        ?Carbon $date = null,
        ?string $document = null,
        ?bool $autoInvoice = null
    ): AccountStatement;

    /**
     * @param Model&UseFeeable $payable
     */
    public function createReceipt(
        Model $payable,
        float $amount,
        string $description,
        ?Carbon $date = null,
        ?string $document = null,
        ?bool $autoInvoice = null
    ): AccountStatement;

    public function createCreditNote(
        AccountStatement $invoice,
        string $description,
        ?Carbon $date = null
    ): AccountStatement;

    public function voidInvoice(AccountStatement $invoice, string $reason): bool;

    /**
     * @param Model&UseFeeable $payable
     * @return Collection<int, AccountStatement>
     */
    public function generateDueInvoices(Model $payable, ?Carbon $date = null): Collection;

    /**
     * @param Model&UseFeeable $model
     * @return Collection<int, AccountStatement>
     */
    public function list(Model $model): Collection;

    /**
     * @param Model&UseFeeable $payable
     */
    public function recalculateBalances(Model $payable): void;
}
