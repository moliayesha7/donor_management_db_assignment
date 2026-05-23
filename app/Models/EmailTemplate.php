<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'name',
        'description',
        'is_default',
        'body',
    ];

    protected $casts = [
        'is_default' => 'boolean', // ডাটাবেজের 0/1 কে ফ্রন্টএ্যান্ডের জন্য true/false এ কাস্ট করবে
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}