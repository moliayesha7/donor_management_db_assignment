<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWhatsappTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255|unique:whatsapp_templates,name',
            'description' => 'nullable|string|max:255',
            'body'        => 'required|string',
            'is_default'  => 'nullable|boolean',
        ];
    }
}
