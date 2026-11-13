<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['property_id', 'payment_id', 'amount_usd_cents', 'credited_on', 'kind', 'status', 'created_by'])]
class PropertyCredit extends Model
{
    /** @use HasFactory<\Database\Factories\PropertyCreditFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_usd_cents' => 'integer',
            'credited_on' => 'date:Y-m-d',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }
}
