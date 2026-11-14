<?php

namespace App\Models;

use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bank_account_id', 'period_item_id', 'description', 'paid_on', 'amount_bs_cents', 'rate_micros', 'rate_source', 'rate_reference_date', 'reference', 'amount_usd_cents', 'fund', 'idempotency_key', 'request_hash', 'status', 'created_by', 'voided_by', 'voided_at', 'void_reason'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'paid_on' => 'date:Y-m-d',
            'amount_bs_cents' => 'integer',
            'rate_micros' => 'integer',
            'rate_reference_date' => 'date:Y-m-d',
            'amount_usd_cents' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function periodItem(): BelongsTo
    {
        return $this->belongsTo(PeriodItem::class);
    }
}
