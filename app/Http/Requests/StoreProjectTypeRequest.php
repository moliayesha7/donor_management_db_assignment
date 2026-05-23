<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255|unique:project_types,name',
            'description' => 'nullable|string',
            'status'      => 'nullable|string|in:active,inactive',
        ];
    }
}
