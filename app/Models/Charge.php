<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['property_id', 'financial_period_id', 'kind', 'description', 'charged_on', 'due_on', 'principal_usd_cents', 'reserve_usd_cents', 'provision_usd_cents', 'created_by'])]
class Charge extends Model
{
    /** @use HasFactory<\Database\Factories\ChargeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'charged_on' => 'date:Y-m-d',
            'due_on' => 'date:Y-m-d',
            'principal_usd_cents' => 'integer',
            'reserve_usd_cents' => 'integer',
            'provision_usd_cents' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }
}
