<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSmsTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
   public function rules(): array {
    $id = $this->route('sms_template');
    return [
        'name' => 'required|string|max:255|unique:sms_templates,name,' . $id,
        'description' => 'nullable|string',
        'is_default' => 'required|boolean',
        'sms_body' => 'required|string',
    ];
}
}
