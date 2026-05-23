<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'student_name', 'student_id', 'guardian_name', 
        'guardian_phone', 'address', 'post_code', 
        'educational_level', 'institution_name', 'funding_status','created_by'
    ];

    // এই স্টুডেন্ট কোন কোন ডোনার থেকে ফান্ড পেয়েছে তা ট্র্যাক করতে
    public function donations()
    {
        return $this->hasMany(Donation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
