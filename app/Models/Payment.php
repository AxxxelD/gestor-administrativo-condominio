<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['bank_account_id', 'paid_on', 'amount_bs_cents', 'rate_micros', 'amount_usd_cents', 'reference', 'idempotency_key', 'request_hash', 'status', 'created_by', 'voided_by', 'voided_at', 'void_reason'])]
class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'paid_on' => 'date:Y-m-d',
            'amount_bs_cents' => 'integer',
            'rate_micros' => 'integer',
            'amount_usd_cents' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(PropertyCredit::class);
    }
}
