<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'donor_id'             => 'required|exists:donors,id',
            'project_id'           => 'required|exists:projects,id',
            'student_id'           => 'nullable|exists:students,id',
            'campaign_id'          => 'nullable|exists:campaigns,id',
            'amount'               => 'required|numeric|min:1',
            'payment_method'       => 'required|string|max:255',
            'transaction_date'     => 'required|date',
            'gift_aid'             => 'required|boolean',
            'status'               => 'nullable|in:pending,confirmed,failed',
            'is_recurring'         => 'sometimes|boolean',
            'recurrence_frequency' => 'nullable|required_if:is_recurring,true|in:weekly,monthly,yearly',
            'recurrence_next_at'   => 'nullable|required_if:is_recurring,true|date',
            'recurrence_ends_at'   => 'nullable|date|after_or_equal:recurrence_next_at',
        ];
    }
}
