<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseTemplate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_usd_cents' => 'integer', 'recurring' => 'boolean', 'active' => 'boolean', 'is_provision' => 'boolean'];
    }
}
