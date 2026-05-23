<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function hasPermission(string $name): bool
    {
        return $this->permissions->contains('name', $name);
    }

    public function syncPermissionsByName(array $names): void
    {
        $ids = Permission::whereIn('name', $names)->pluck('id')->all();
        $this->permissions()->sync($ids);
    }
}
