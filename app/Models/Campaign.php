<?php

namespace App\Models;

use App\Models\Concerns\HasEncryptedRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use SoftDeletes, HasEncryptedRouteKey;

    protected $fillable = ['name', 'code', 'description', 'type', 'default_amount', 'is_active'];

    protected $casts = [
        'is_active'      => 'boolean',
        'default_amount' => 'decimal:2',
    ];

    public function donations()
    {
        return $this->hasMany(Donation::class);
    }
}
