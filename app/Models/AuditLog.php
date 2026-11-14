<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public static function record(string $action, Model $model, array $details = []): void
    {
        static::create(['user_id' => auth()->id(), 'action' => $action, 'entity' => $model->getTable(), 'entity_id' => $model->getKey(), 'details' => $details]);
    }
}
