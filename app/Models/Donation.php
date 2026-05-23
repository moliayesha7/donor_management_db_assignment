<?php

namespace App\Models;

use App\Casts\EncryptedDecimal;
use App\Models\Concerns\HasEncryptedRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Donation extends Model
{
    use LogsActivity;
    use SoftDeletes, HasEncryptedRouteKey;

    protected $fillable = [
        'donor_id', 'project_id', 'student_id', 'campaign_id', 'amount',
        'payment_method', 'transaction_date', 'receipt_number',
        'gift_aid', 'gift_aid_at', 'consent_given', 'consent_at', 'status',
        'is_recurring', 'recurrence_frequency', 'recurrence_next_at',
        'recurrence_ends_at', 'recurrence_parent_id','stripe_session_id'
    ];

    protected $casts = [
        'gift_aid'           => 'boolean',
        'consent_given'      => 'boolean',
        'is_recurring'       => 'boolean',
        'transaction_date'   => 'datetime',
        'gift_aid_at'        => 'datetime',
        'consent_at'         => 'datetime',
        'recurrence_next_at' => 'datetime',
        'recurrence_ends_at' => 'datetime',
        'amount'             => EncryptedDecimal::class,
    ];

    public function donor()
    {
        return $this->belongsTo(Donor::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class)->withDefault([
            'name' => 'General Project Funding'
        ]);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function recurrenceParent()
    {
        return $this->belongsTo(Donation::class, 'recurrence_parent_id');
    }

    public function recurringChildren()
    {
        return $this->hasMany(Donation::class, 'recurrence_parent_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['donor_id', 'amount', 'status'])
            ->logAll(); // only log the specified fields, not all
    }
}
