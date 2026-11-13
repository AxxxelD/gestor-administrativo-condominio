<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['financial_period_id', 'period_item_id', 'type', 'amount_usd_cents', 'used_on', 'actor_id'])]
class FundUse extends Model
{
    /** @use HasFactory<\Database\Factories\FundUseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_usd_cents' => 'integer',
            'used_on' => 'date:Y-m-d',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function periodItem(): BelongsTo
    {
        return $this->belongsTo(PeriodItem::class);
    }
}
