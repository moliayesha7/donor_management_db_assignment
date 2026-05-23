<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id'   => 'required|exists:projects,id',
            'category'     => ['required', Rule::in(Expense::CATEGORIES)],
            'amount'       => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'vendor'       => 'nullable|string|max:255',
            'description'  => 'nullable|string',
            'status'       => ['required', Rule::in(['pending', 'approved', 'paid'])],
        ];
    }
}
