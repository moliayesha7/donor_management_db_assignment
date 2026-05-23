<?php

namespace App\Providers;

use App\Events\DonorRegistered;
use App\Events\DonationConfirmed;
use App\Events\DonationFailed;
use App\Listeners\SendWelcomeNotification;
use App\Listeners\SendDonationFailedNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // 1. New Donor Registration Event
        DonorRegistered::class => [
            SendWelcomeNotification::class,
        ],

        // 2. Existing Donation Confirmed Event
        DonationConfirmed::class => [],

        // 3. New Donation Failed Event
        DonationFailed::class => [
            SendDonationFailedNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}