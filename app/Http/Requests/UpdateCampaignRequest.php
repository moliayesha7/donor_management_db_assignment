<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('campaign')?->id;

        return [
            'name'           => ['sometimes', 'required', 'string', 'max:120', Rule::unique('campaigns', 'name')->ignore($id)],
            'code'           => 'nullable|string|max:40',
            'description'    => 'nullable|string',
            'type'           => 'sometimes|required|in:one_off,recurring',
            'default_amount' => 'nullable|numeric|min:0',
            'is_active'      => 'sometimes|boolean',
        ];
    }
}
