<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_name'      => 'sometimes|required|string|max:255',
            'guardian_name'     => 'nullable|string|max:255',
            'guardian_phone'    => 'nullable|string|max:50',
            'address'           => 'nullable|string',
            'post_code'         => 'nullable|string|max:20',
            'educational_level' => 'nullable|string|max:100',
            'institution_name'  => 'nullable|string|max:255',
            'funding_status'    => 'nullable|in:unfunded,partially_funded,fully_funded',
        ];
    }
}
