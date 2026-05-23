<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    protected $fillable = [
        'upload_id', 'row_number',
        'source_id', 'order_id',
        'transaction_date', 'transaction_time', 'transaction_status',
        'amount', 'payment_method', 'reference',
        'first_name', 'last_name', 'phone', 'email', 'address_line_1',
        'project_code',
        'match_status', 'matched_donor_id', 'created_donation_id',
        'notes', 'raw_payload',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount'           => 'decimal:2',
        'raw_payload'      => 'array',
    ];

    public function upload()
    {
        return $this->belongsTo(BankStatementUpload::class, 'upload_id');
    }

    public function matchedDonor()
    {
        return $this->belongsTo(Donor::class, 'matched_donor_id');
    }

    public function createdDonation()
    {
        return $this->belongsTo(Donation::class, 'created_donation_id');
    }
}
