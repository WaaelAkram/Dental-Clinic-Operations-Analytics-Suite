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
        
        if ($startTime->isPast()) {
            $startTime = now();
        }

        $totalWindowSeconds = $endTime->diffInSeconds($startTime);
        $batchCount = $stagedMessages->count();
        
        $interval = $batchCount > 1 ? $totalWindowSeconds / ($batchCount - 1) : 0;

        // 3. Loop, dispatch, and update status
        foreach ($stagedMessages as $index => $message) {
            // Eager load patient data or use the accessor if necessary
            $patientData = $message->patient;
            if (!$patientData) {
                $this->error("Could not find patient details for ID: {$message->patient_id}. Skipping.");
                continue;
            }

            $prospect = (object)[
                'id' => $message->patient_id,
                'mobile' => $message->mobile,
                'full_name' => $patientData->full_name ?? 'Valued Patient',
            ];

            $delayInSeconds = floor($index * $interval);

            SendMarketingMessage::dispatch($prospect)->delay($startTime->copy()->addSeconds($delayInSeconds));

            $message->update(['status' => 'queued']);

            $this->line(" -> Queued job for Patient ID #{$prospect->id}. Dispatching at approximately: " . $startTime->copy()->addSeconds($delayInSeconds)->format('H:i:s'));
        }

        $this->info("Successfully queued {$batchCount} jobs.");
        Log::info("Marketing cron: Queued {$batchCount} jobs.");

        return self::SUCCESS;
    }
}