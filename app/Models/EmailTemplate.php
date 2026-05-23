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
        'is_default' => 'boolean', // database 0/1 frontend true/false 
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}