<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:120|unique:campaigns,name',
            'code'           => 'nullable|string|max:40',
            'description'    => 'nullable|string',
            'type'           => 'required|in:one_off,recurring',
            'default_amount' => 'nullable|numeric|min:0',
            'is_active'      => 'sometimes|boolean',
        ];
    }
}
