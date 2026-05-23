<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDonorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                 => 'required|string|max:255',
            'address_lookup'       => 'nullable|string|max:255',
            'address_line_1'       => 'required|string|max:255',
            'address_line_2'       => 'nullable|string|max:255',
            'address_line_3'       => 'nullable|string|max:255',
            'city'                 => 'required|string|max:255',
            'county'               => 'nullable|string|max:255',
            'post_code'            => 'required|string|max:20',
            'phone_number'         => 'required|string|max:32',
            'email'                => 'nullable|email|max:255',
            'country'              => 'nullable|string|max:100',
            'notify_email'         => 'nullable|boolean',
            'notify_sms'           => 'nullable|boolean',
            'notify_whatsapp'      => 'nullable|boolean',
            'donor_source_id'      => 'nullable|exists:donor_sources,id',
            'preferred_project_id' => 'nullable|exists:projects,id',
        ];
    }
}
