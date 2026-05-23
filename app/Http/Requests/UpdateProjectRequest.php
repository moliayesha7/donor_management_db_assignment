<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('project');

        return [
            'project_type_id' => 'required|exists:project_types,id',
            'name'            => 'required|string|max:255',
            'project_code'    => ['required', 'string', 'max:50', Rule::unique('projects', 'project_code')->ignore($id)],
            'description'     => 'nullable|string',
            'budget'          => 'required|numeric|min:0',
            'status'          => 'required|string|in:pending,active,completed,suspended',
        ];
    }
}
