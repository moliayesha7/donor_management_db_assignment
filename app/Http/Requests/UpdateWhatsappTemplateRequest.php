<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWhatsappTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('whatsapp_template');

        return [
            'name'        => ['required', 'string', 'max:255', Rule::unique('whatsapp_templates', 'name')->ignore($id)],
            'description' => 'nullable|string|max:255',
            'body'        => 'required|string',
            'is_default'  => 'nullable|boolean',
        ];
    }
}
