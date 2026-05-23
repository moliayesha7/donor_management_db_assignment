<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(string $name): bool
    {
        return $this->role?->name === $name;
    }

    public function hasAnyRole(array $names): bool
    {
        return in_array($this->role?->name, $names, true);
    }

    public function hasPermission(string $name): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        $role = $this->role;
        if (!$role) {
            return false;
        }

        if (!$role->relationLoaded('permissions')) {
            $role->load('permissions');
        }

        return $role->permissions->contains('name', $name);
    }
}
