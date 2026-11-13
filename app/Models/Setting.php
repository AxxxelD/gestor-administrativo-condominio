<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function values(): array
    {
        return array_replace([
            'condominium_name' => 'Terrazas del Atlántico · Sector C',
            'rif' => 'J-29497590-9',
            'contact_email' => '',
            'bank_instructions' => '',
            'notes' => 'El pago se realiza en bolívares a la tasa BCV de la fecha de la transferencia.',
            'demo' => false,
        ], static::whereIn('key', ['condominium_name', 'rif', 'contact_email', 'bank_instructions', 'notes', 'demo'])->get()->pluck('value', 'key')->all());
    }
}
