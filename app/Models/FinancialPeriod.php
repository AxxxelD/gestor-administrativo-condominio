<?php

namespace App\Models;

use Database\Factories\FinancialPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['month', 'status', 'reserve_basis_points', 'due_on', 'net_usd_cents', 'reserve_usd_cents', 'total_usd_cents', 'unit_charge_usd_cents', 'rounding_adjustment_usd_cents', 'units_count', 'items_snapshot', 'issued_at', 'issued_by'])]
class FinancialPeriod extends Model
{
    /** @use HasFactory<FinancialPeriodFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (FinancialPeriod $period): void {
            if ($period->getOriginal('status') === 'issued' && $period->isDirty()) {
                throw ValidationException::withMessages(['period' => 'Un período emitido conserva sus importes y conceptos originales.']);
            }
        });
        static::deleting(function (FinancialPeriod $period): void {
            if ($period->status === 'issued') {
                throw ValidationException::withMessages(['period' => 'Un período emitido conserva su historial.']);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'reserve_basis_points' => 'integer',
            'due_on' => 'date:Y-m-d',
            'net_usd_cents' => 'integer',
            'reserve_usd_cents' => 'integer',
            'total_usd_cents' => 'integer',
            'unit_charge_usd_cents' => 'integer',
            'rounding_adjustment_usd_cents' => 'integer',
            'units_count' => 'integer',
            'items_snapshot' => 'array',
            'issued_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PeriodItem::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function fundUses(): HasMany
    {
        return $this->hasMany(FundUse::class);
    }
}
