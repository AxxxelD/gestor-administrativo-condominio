<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['financial_period_id', 'category', 'description', 'amount_usd_cents', 'is_provision'])]
class PeriodItem extends Model
{
    /** @use HasFactory<\Database\Factories\PeriodItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        $checkPeriod = function (PeriodItem $item): void {
            $periodIds = array_filter([$item->financial_period_id, $item->getOriginal('financial_period_id')]);
            if (FinancialPeriod::query()->whereIn('id', $periodIds)->where('status', 'issued')->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Los conceptos de un período emitido son inmutables.']);
            }
        };
        static::saving($checkPeriod);
        static::deleting($checkPeriod);
    }

    protected function casts(): array
    {
        return [
            'amount_usd_cents' => 'integer',
            'is_provision' => 'boolean',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
