<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'active', 'permissions', 'property_ids'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const PERMISSIONS = [
        'community.view', 'community.manage', 'finance.view', 'finance.manage',
        'payments.view', 'payments.manage', 'treasury.view', 'treasury.manage',
        'reports.view', 'communications.manage', 'users.manage', 'settings.manage',
    ];

    protected $attributes = [
        'role' => 'user',
        'active' => true,
        'permissions' => '[]',
        'property_ids' => '[]',
    ];

    protected $appends = ['capabilities'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'permissions' => 'array',
            'property_ids' => 'array',
        ];
    }

    public function allows(string $permission): bool
    {
        if (! $this->active || ! in_array($permission, self::PERMISSIONS, true)) {
            return false;
        }

        if (in_array($this->role, ['superuser', 'admin'], true)) {
            return true;
        }

        if (in_array($permission, $this->permissions ?? [], true)) {
            return true;
        }

        return str_ends_with($permission, '.view')
            && in_array(str_replace('.view', '.manage', $permission), $this->permissions ?? [], true);
    }

    /** @return list<string> */
    public function capabilities(): array
    {
        return array_values(array_filter(self::PERMISSIONS, fn (string $permission): bool => $this->allows($permission)));
    }

    /** @return list<string> */
    public function getCapabilitiesAttribute(): array
    {
        return $this->capabilities();
    }

    public function hasGlobalPropertyAccess(): bool
    {
        return $this->allows('community.manage') || $this->allows('finance.manage');
    }

    public function canAccessProperty(int $propertyId): bool
    {
        return $this->active && ($this->hasGlobalPropertyAccess()
            || in_array($propertyId, array_map('intval', $this->property_ids ?? []), true));
    }
}
