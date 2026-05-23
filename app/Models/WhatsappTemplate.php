<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappTemplate extends Model
{
    protected $fillable = ['campaign_id', 'name',
    'trigger_event',
    'description',
    'body',
    'status',
    'language',
    'is_default',];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}
