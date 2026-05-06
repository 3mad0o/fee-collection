<?php

namespace Emad\FeeCollection\Models;

use Carbon\Carbon;
use Emad\FeeCollection\Contracts\AccountStatementServiceInterface;
use Emad\FeeCollection\Contracts\PaymentSplitterServiceInterface;
use Emad\FeeCollection\Enums\AccountStatementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

class UpcomingPayment extends Model
{
    protected $fillable = [
        'payable_id',
        'payable_type',
        'amount',
        'remaining_amount',
        'due_date',
        'split_parent_id',
    ];

    protected $casts = [
        'amount' => 'float',
        'remaining_amount' => 'float',
        'due_date' => 'datetime',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function invoice(): HasOneThrough
    {
        return $this->hasOneThrough(
            AccountStatement::class,
            AccountStatementUpcomingPayment::class,
            'upcoming_payment_id', // FK on pivot table pointing to upcoming_payments
            'id',                  // PK on account_statements
            'id',                  // PK on upcoming_payments
            'account_statement_id' // FK on pivot pointing to account_statements
        )->where('type', AccountStatementType::INVOICE);
    }

    public function receipt(): HasOneThrough
    {
        return $this->hasOneThrough(
            AccountStatement::class,
            AccountStatementUpcomingPayment::class,
            'upcoming_payment_id',   // FK on pivot
            'id',                    // PK on account_statements
            'id',                    // PK on upcoming_payments
            'account_statement_id'   // FK on pivot
        )->where('type', AccountStatementType::RECEIPT);
    }


    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'split_parent_id');
    }

    /**
     * @param array<int, array{amount: float|int|string, due_date: Carbon|string}> $data
     */
    public function split(array $data): Collection
    {
        return app(PaymentSplitterServiceInterface::class)->split($this, $data);
    }


    public function createInvoice(string $description, ?Carbon $date = null, ?string $document = null): AccountStatement
    {
        return app(AccountStatementServiceInterface::class)->createInvoice($this, $description, $date, $document);
    }

    public function createReceipt(string $description, ?Carbon $date = null, ?string $document = null): AccountStatement
    {
        return app(AccountStatementServiceInterface::class)->createUpcomingPaymentReceipt($this, $description, $date, $document);
    }

}
