<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = ['project_type_id', 'name', 'project_code', 'description', 'budget', 'status', 'created_by'];


    public function type()
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }

    public function donations()
    {
        return $this->hasMany(Donation::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
