<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonorSource extends Model
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function donors()
    {
        return $this->hasMany(Donor::class, 'donor_source_id');
    }
}
