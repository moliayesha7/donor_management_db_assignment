<?php

namespace App\Console\Commands;

use App\Events\DonationConfirmed;
use App\Models\Donation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessRecurringDonations extends Command
{
    protected $signature = 'donations:process-recurring';
    protected $description = 'Spawn child donations for recurring donations whose next-due date has arrived';

    public function handle(): int
    {
        $now = now();

        $due = Donation::query()
            ->whereNull('recurrence_parent_id')
            ->where('is_recurring', true)
            ->whereNotNull('recurrence_next_at')
            ->where('recurrence_next_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('recurrence_ends_at')->orWhere('recurrence_ends_at', '>=', $now);
            })
            ->get();

        $spawned = 0;

        foreach ($due as $parent) {
            DB::transaction(function () use ($parent, &$spawned) {
                $child = $parent->replicate([
                    'receipt_number', 'is_recurring', 'recurrence_frequency',
                    'recurrence_next_at', 'recurrence_ends_at', 'recurrence_parent_id',
                ]);
                $child->recurrence_parent_id = $parent->id;
                $child->status                = 'confirmed';
                $child->transaction_date      = now();
                $child->receipt_number        = $this->nextReceiptNumber();
                $child->save();

                $parent->recurrence_next_at = $this->advance($parent->recurrence_next_at, $parent->recurrence_frequency);
                $parent->save();

                DonationConfirmed::dispatch($child);
                $spawned++;
            });
        }

        $this->info("Recurring donations processed: {$spawned}");
        return self::SUCCESS;
    }

    protected function advance(Carbon|string|null $from, ?string $frequency): ?Carbon
    {
        if (!$from || !$frequency) return null;
        $next = Carbon::parse($from);
        return match ($frequency) {
            'weekly'  => $next->addWeek(),
            'monthly' => $next->addMonthNoOverflow(),
            'yearly'  => $next->addYear(),
            default   => null,
        };
    }

    protected function nextReceiptNumber(): string
    {
        $last = Donation::orderByDesc('id')->value('receipt_number');
        $n = 100000;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $n = max($n, (int) $m[1]);
        }
        return 'REC-' . ($n + 1);
    }
}
