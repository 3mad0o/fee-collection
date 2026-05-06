<?php

namespace Emad\FeeCollection\Models;

use Carbon\Carbon;
use Emad\FeeCollection\Contracts\StatementPdfServiceInterface;
use Emad\FeeCollection\Enums\AccountStatementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
    ];


    public function upcomingPayments(): BelongsToMany
    {
        return $this->belongsToMany(UpcomingPayment::class, 'account_statement_upcoming_payments');
    }

    public function accountable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Build PDF object for current statement using configured blade template.
     */
    public function toPdf(): mixed
    {
        return app(StatementPdfServiceInterface::class)->make($this);
    }

    public function getFormattedNumberAttribute(): string
    {
        $prefix = $this->type === AccountStatementType::INVOICE
            ? (string) config('fee_collection.invoice_prefix', '')
            : (string) config('fee_collection.receipt_prefix', '');
        $suffix = $this->type === AccountStatementType::INVOICE
            ? (string) config('fee_collection.invoice_suffix', '')
            : (string) config('fee_collection.receipt_suffix', '');

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
