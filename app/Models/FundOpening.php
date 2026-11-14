<?php

namespace App\Models;

use Database\Factories\FundOpeningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'amount_usd_cents', 'date', 'actor_id'])]
class FundOpening extends Model
{
    /** @use HasFactory<FundOpeningFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_usd_cents' => 'integer',
            'date' => 'date:Y-m-d',
        ];
    }
}
