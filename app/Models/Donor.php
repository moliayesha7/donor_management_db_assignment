<?php

namespace App\Models;

use App\Models\Concerns\HasEncryptedRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Donor extends Model
{
    use HasEncryptedRouteKey;
    use SoftDeletes;

    protected $fillable = [
        'donor_id_code',
        'name',
        'address_lookup',
        'address_line_1',
        'address_line_2',
        'address_line_3',
        'city',
        'county',
        'post_code',
        'phone_number',
        'email',
        'country',
        'notify_email',
        'notify_sms',
        'notify_whatsapp',
        'donor_source_id',
        'preferred_project_id',
        'created_by',
    ];

    protected $casts = [
        'notify_email'    => 'boolean',
        'notify_sms'      => 'boolean',
        'notify_whatsapp' => 'boolean',
    ];

    public function donations()
    {
        return $this->hasMany(Donation::class);
    }

    public function preferredProject()
    {
        return $this->belongsTo(Project::class, 'preferred_project_id');
    }

    public function donorSource()
    {
        return $this->belongsTo(DonorSource::class, 'donor_source_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
