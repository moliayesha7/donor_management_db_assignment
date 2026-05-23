<?php

namespace App\Providers;

use App\Console\Commands\DispatchScheduledEmails;
use App\Console\Commands\ProcessRecurringDonations;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Events\DonorRegistered;
use App\Events\DonationFailed;
use App\Listeners\SendWhatsAppTemplateNotification;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
   public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DispatchScheduledEmails::class,
                ProcessRecurringDonations::class,
            ]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule->command('emails:dispatch-scheduled')
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->runInBackground();

                $schedule->command('donations:process-recurring')
                    ->dailyAt('02:00')
                    ->withoutOverlapping();
            });
        }


        // ৩. হোয়াটসঅ্যাপ অটো-নোটিফিকেশনের জন্য ইভেন্ট এবং লিসেনার রেজিস্টার (boot মেথডের ভেতরে)
        Event::listen(DonorRegistered::class, SendWhatsAppTemplateNotification::class);
        Event::listen(DonationFailed::class, SendWhatsAppTemplateNotification::class);
    }
}
