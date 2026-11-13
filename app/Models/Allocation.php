<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['property_credit_id', 'charge_id', 'principal_usd_cents', 'reserve_usd_cents', 'provision_usd_cents', 'applied_on', 'voided_at'])]
class Allocation extends Model
{
    /** @use HasFactory<\Database\Factories\AllocationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'principal_usd_cents' => 'integer',
            'reserve_usd_cents' => 'integer',
            'provision_usd_cents' => 'integer',
            'applied_on' => 'date:Y-m-d',
            'voided_at' => 'datetime',
        ];
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(PropertyCredit::class, 'property_credit_id');
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
