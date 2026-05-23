<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        
        $id = $this->route('email_template'); 

        return [
            'name'        => 'required|string|max:255|unique:email_templates,name,' . $id,
            'description' => 'nullable|string',
            'is_default'  => 'required|boolean',
            'body'        => 'required|string',
        ];
    }
}