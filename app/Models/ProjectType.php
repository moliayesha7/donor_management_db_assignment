<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectType extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'status'];

    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
