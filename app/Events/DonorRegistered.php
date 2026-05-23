<?php

namespace App\Events;

use App\Models\Donor;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DonorRegistered
{
    use Dispatchable, SerializesModels;

    public $donor;

    public function __construct(Donor $donor)
    {
        $this->donor = $donor;
    }
}