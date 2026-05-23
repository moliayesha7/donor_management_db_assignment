<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDonationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'project_id'           => 'sometimes|required|exists:projects,id',
            'student_id'           => 'nullable|exists:students,id',
            'campaign_id'          => 'nullable|exists:campaigns,id',
            'amount'               => 'sometimes|required|numeric|min:1',
            'payment_method'       => 'sometimes|required|string',
            'status'               => 'sometimes|required|in:pending,confirmed,failed',
            'is_recurring'         => 'sometimes|boolean',
            'recurrence_frequency' => 'nullable|in:weekly,monthly,yearly',
            'recurrence_next_at'   => 'nullable|date',
            'recurrence_ends_at'   => 'nullable|date|after_or_equal:recurrence_next_at',
        ];
    }
}