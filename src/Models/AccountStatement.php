<?php

namespace Emad\FeeCollection\Models;

use Carbon\Carbon;
use Emad\FeeCollection\Contracts\AccountStatementServiceInterface;
use Emad\FeeCollection\Contracts\StatementPdfServiceInterface;
use Emad\FeeCollection\Enums\AccountStatementStatus;
use Emad\FeeCollection\Enums\AccountStatementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccountStatement extends Model
{
    protected $appends = [
        'formatted_number',
    ];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'accountable_type',
        'accountable_id',
        'type',
        'number',
        'description',
        'amount',
        'debit',
        'credit',
        'balance',
        'created_at',
        'updated_at',
        'date',
        'document',
        'reference_id',
        'status',
        'voided_at',
        'void_reason',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => AccountStatementType::class,
        'number' => 'integer',
        'date' => 'datetime',
        'amount' => 'float',
        'debit' => 'float',
        'credit' => 'float',
        'balance' => 'float',
        'status' => AccountStatementStatus::class,
        'voided_at' => 'datetime',
    ];


    public function upcomingPayments(): BelongsToMany
    {
        return $this->belongsToMany(UpcomingPayment::class, 'account_statement_upcoming_payments');
    }

    public function accountable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reference(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reference_id');
    }

    public function creditNote(): HasOne
    {
        return $this->hasOne(self::class, 'reference_id')
            ->where('type', AccountStatementType::CREDIT_NOTE);
    }

    /**
     * Build PDF object for current statement using configured blade template.
     */
    public function toPdf(): mixed
    {
        return app(StatementPdfServiceInterface::class)->make($this);
    }

    public function createCreditNote(string $description, ?Carbon $date = null): AccountStatement
    {
        return app(AccountStatementServiceInterface::class)->createCreditNote($this, $description, $date);
    }

    public function void(string $reason): bool
    {
        return app(AccountStatementServiceInterface::class)->voidInvoice($this, $reason);
    }

    public function getFormattedNumberAttribute(): string
    {
        $prefix = match ($this->type) {
            AccountStatementType::INVOICE => (string) config('fee_collection.invoice_prefix', ''),
            AccountStatementType::CREDIT_NOTE => (string) config('fee_collection.credit_note_prefix', 'CN-'),
            AccountStatementType::RECEIPT => (string) config('fee_collection.receipt_prefix', ''),
        };
        $suffix = match ($this->type) {
            AccountStatementType::INVOICE => (string) config('fee_collection.invoice_suffix', ''),
            AccountStatementType::CREDIT_NOTE => (string) config('fee_collection.credit_note_suffix', ''),
            AccountStatementType::RECEIPT => (string) config('fee_collection.receipt_suffix', ''),
        };

        return $prefix . (string) ($this->attributes['number'] ?? $this->number) . $suffix;
    }

    public function setNumberAttribute(mixed $value): void
    {
        if (is_numeric($value)) {
            $this->attributes['number'] = (int) $value;
            return;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);
        $this->attributes['number'] = $digits !== '' ? (int) $digits : 0;
    }

}
