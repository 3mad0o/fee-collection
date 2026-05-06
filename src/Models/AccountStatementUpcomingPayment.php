<?php

namespace Emad\FeeCollection\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountStatementUpcomingPayment extends Model
{
    protected $fillable = [
        'account_statement_id',
        'upcoming_payment_id',
    ];

    public function accountStatement(): BelongsTo
    {
        return $this->belongsTo(AccountStatement::class);
    }

    public function upcomingPayment(): BelongsTo
    {
        return $this->belongsTo(UpcomingPayment::class);
    }
}
