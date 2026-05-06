<?php

namespace Emad\FeeCollection\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'walletable_type',
        'walletable_id',
        'balance',
    ];

    protected $casts = [
        'balance' => 'float',
    ];

    public function walletable(): MorphTo
    {
        return $this->morphTo();
    }
}
