<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = ['project_type_id', 'name', 'project_code', 'description', 'budget', 'status', 'created_by'];

    // প্রজেক্টটি কোন টাইপের আন্ডারে তা জানার জন্য
    public function type()
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }

    // এই প্রজেক্টে আসা সব ডোনেশন দেখতে
    public function donations()
    {
        return $this->hasMany(Donation::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
