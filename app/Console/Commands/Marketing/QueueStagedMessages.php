<?php

namespace App\Console\Commands\Marketing;

use App\Jobs\SendMarketingMessage;
use App\Models\SentMarketingMessage;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class QueueStagedMessages extends Command
{
    protected $signature = 'marketing:queue-staged-messages';
    protected $description = 'Finds staged marketing messages and dispatches them to the queue with a staggered delay.';

    public function handle(): int
    {
        // 1. Find the work that needs to be done for today.
        $stagedMessages = SentMarketingMessage::where('status', 'staging')
            ->where('process_date', today())
            ->get();

        if ($stagedMessages->isEmpty()) {
            $this->info("No staged messages found to queue for today.");
            return self::SUCCESS;
        }

        $this->info("Found {$stagedMessages->count()} staged messages. Preparing to queue them now...");

        // 2. Calculate the sending window and interval
        $startTime = Carbon::parse(config('marketing.send_window_start'));
        $endTime = Carbon::parse(config('marketing.send_window_end'));
        
        // Ensure start time is not in the past. If it is, start sending now.
        if ($startTime->isPast()) {
            $startTime = now();
        }

        $totalWindowSeconds = $endTime->diffInSeconds($startTime);
        $batchCount = $stagedMessages->count();
        
        // Calculate the interval between each message to spread them out evenly.
        // Avoid division by zero if there's only one message.
        $interval = $batchCount > 1 ? $totalWindowSeconds / ($batchCount - 1) : 0;

        // 3. Loop, dispatch, and update status
        foreach ($stagedMessages as $index => $message) {
            $prospect = (object)[
                'id' => $message->patient_id,
                'mobile' => $message->mobile,
                'full_name' => $message->patient->full_name ?? 'Valued Patient', // Fallback name
            ];

            // The delay for the current job is its index times the calculated interval.
            $delayInSeconds = floor($index * $interval);

            // Dispatch the job with the calculated delay from the start of the window.
            SendMarketingMessage::dispatch($prospect)->delay($startTime->copy()->addSeconds($delayInSeconds));

            // IMPORTANT: Immediately update the status to prevent re-queueing.
            $message->update(['status' => 'queued']);

            $this->line(" -> Queued job for Patient ID #{$prospect->id}. Dispatching at approximately: " . $startTime->copy()->addSeconds($delayInSeconds)->format('H:i:s'));
        }

        $this->info("Successfully queued {$batchCount} jobs.");
        Log::info("Marketing cron: Queued {$batchCount} jobs.");

        return self::SUCCESS;
    }
}