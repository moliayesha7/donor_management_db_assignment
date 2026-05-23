<?php

namespace App\Console\Commands;

use App\Jobs\SendBulkEmailJob;
use App\Models\Email;
use Illuminate\Console\Command;

class DispatchScheduledEmails extends Command
{
    protected $signature = 'emails:dispatch-scheduled';

    protected $description = 'Dispatch queued jobs for "Later" emails whose scheduled_at has passed.';

    public function handle(): int
    {
        $due = Email::where('send_timing', 'Later')
            ->where('status', 'draft')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No scheduled emails due.');
            return self::SUCCESS;
        }

        $totalDispatched = 0;
        foreach ($due as $email) {
            $recipients = collect(preg_split('/[\s,;]+/', $email->recipients))
                ->map(fn ($r) => trim($r))
                ->filter(fn ($r) => filter_var($r, FILTER_VALIDATE_EMAIL))
                ->unique()
                ->values();

            $email->update(['status' => 'pending']);

            foreach ($recipients as $recipient) {
                SendBulkEmailJob::dispatch($email->id, $recipient);
                $totalDispatched++;
            }

            $this->line("Email #{$email->id}: dispatched to {$recipients->count()} recipient(s).");
        }

        $this->info("Done — {$due->count()} email(s), {$totalDispatched} job(s) dispatched.");
        return self::SUCCESS;
    }
}
