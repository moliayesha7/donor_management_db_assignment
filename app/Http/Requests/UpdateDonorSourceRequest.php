<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDonorSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('donor_source');

        return [
            'name'        => ['required', 'string', 'max:255', Rule::unique('donor_sources', 'name')->ignore($id)],
            'description' => 'nullable|string',
            'is_active'   => 'required|boolean',
        ];
    }
}
