<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_type_id' => 'required|exists:project_types,id',
            'name'            => 'required|string|max:255',
            'project_code'    => 'required|string|max:50|unique:projects,project_code',
            'description'     => 'nullable|string',
            'budget'          => 'required|numeric|min:0',
        ];
    }
}
