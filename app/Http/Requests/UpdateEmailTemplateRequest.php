<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        // রাউটের আইডি রিড করে কারেন্ট টেমপ্লেটকে ইউনিক চেক থেকে এক্সক্লুড করবে
        $id = $this->route('email_template'); 

        return [
            'name'        => 'required|string|max:255|unique:email_templates,name,' . $id,
            'description' => 'nullable|string',
            'is_default'  => 'required|boolean',
            'body'        => 'required|string',
        ];
    }
}