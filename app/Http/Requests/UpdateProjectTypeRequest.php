<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('project_type');

        return [
            'name'        => ['required', 'string', 'max:255', Rule::unique('project_types', 'name')->ignore($id)],
            'description' => 'nullable|string',
            'status'      => 'required|string|in:active,inactive',
        ];
    }
}
