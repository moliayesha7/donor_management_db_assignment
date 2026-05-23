<?php

namespace App\Mail;

use App\Models\Donation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DonationThankYou extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Donation $donation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Thank you for your donation — receipt ' . $this->donation->receipt_number);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.donation_thank_you',
            with: [
                'donor'   => $this->donation->donor,
                'project' => $this->donation->project,
                'amount'  => $this->donation->amount,
                'receipt' => $this->donation->receipt_number,
                'date'    => $this->donation->transaction_date,
            ],
        );
    }
}
