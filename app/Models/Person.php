<?php

namespace App\Models;

use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'identity_number', 'email', 'phone'])]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    public function occupancies(): HasMany
    {
        return $this->hasMany(Occupancy::class);
    }
}
