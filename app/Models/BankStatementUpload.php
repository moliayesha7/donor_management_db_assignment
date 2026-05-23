<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankStatementUpload extends Model
{
    protected $fillable = [
        'original_name', 'stored_path', 'format', 'default_project_id',
        'total_rows', 'matched_rows', 'unmatched_rows', 'donor_created_rows',
        'duplicate_rows', 'error_rows', 'total_amount', 'status', 'notes', 'uploaded_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function transactions()
    {
        return $this->hasMany(BankTransaction::class, 'upload_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function defaultProject()
    {
        return $this->belongsTo(Project::class, 'default_project_id');
    }
}
