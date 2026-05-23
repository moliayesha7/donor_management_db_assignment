<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasEncryptedRouteKey;

class Expense extends Model
{
    use HasEncryptedRouteKey;
    use SoftDeletes;

    protected $fillable = [
        'project_id', 'category', 'amount', 'expense_date',
        'vendor', 'description', 'receipt_path', 'status', 'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    /**
     * Allowed category values. Surfaced to the FE via API + used by validation.
     * Keep in sync with the dropdown options on ExpensesPage.
     */
    public const CATEGORIES = [
        'Supplies', 'Salaries', 'Logistics', 'Equipment',
        'Travel', 'Utilities', 'Rent', 'Marketing', 'Other',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
