<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['rate_micros' => 'integer', 'fetched_at' => 'datetime'];
    }
}
