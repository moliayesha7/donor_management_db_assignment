<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255|unique:email_templates,name',
            'description' => 'nullable|string',
            'is_default'  => 'required|boolean',
            'body'        => 'required|string',
        ];
    }
}