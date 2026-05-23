<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Email extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipients',
        'template_id',
        'subject',
        'body',
        'selected_projects',
        'send_timing',
        'scheduled_at',
        'status',
        'created_by'
    ];

    // JSON ফিল্ডটিকে অটোমেটিক অ্যারেতে রূপান্তর করার কাস্টিং
    protected $casts = [
        'selected_projects' => 'array',
        'scheduled_at' => 'datetime',
    ];

    /**
     * এই ইমেইলটি কোন ইউজার তৈরি করেছে তার রিলেশন
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}