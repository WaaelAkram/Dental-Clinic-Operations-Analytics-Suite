<?php

namespace App\Console\Commands\Marketing;

use App\Jobs\SendMarketingMessage;
use App\Models\SentMarketingMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class QueueStagedMessages extends Command
{
    protected $signature = 'marketing:queue-staged-messages';
    protected $description = 'Finds staged messages and dispatches them evenly before the daily deadline.';

    public function handle(): int
    {
        $stagedMessages = SentMarketingMessage::where('status', 'staging')
            ->where('process_date', today())
            ->get();

        $messageCount = $stagedMessages->count();

        if ($messageCount === 0) {
            $this->info("No staged messages found to queue for today.");
            return self::SUCCESS;
        }

        // --- NEW, ROBUST SPREAD LOGIC ---
        $deadline = today()->setTime(21, 30, 0); // 9:30 PM today
        $now = now();

        // Safety check: If it's already past the deadline, do nothing.
        if ($now->isAfter($deadline)) {
            $this->warn("It's after the 9:30 PM deadline. No messages will be queued.");
            return self::SUCCESS;
        }

        // Calculate the total seconds remaining until the deadline.
        $secondsRemaining = $now->diffInSeconds($deadline);

        // Calculate the average delay to spread messages evenly.
        // Add 1 to messageCount to create a buffer and avoid sending the last message exactly at the deadline.
        $averageDelay = (int)floor($secondsRemaining / ($messageCount + 1));

        $this->info("Found {$messageCount} messages. Spreading them out until {$deadline->format('H:i')}.");
        $this->info("Average delay between messages will be approximately {$averageDelay} seconds.");

        $cumulativeDelay = 0;
        foreach ($stagedMessages as $key => $message) {
            
            // The first message has a delay, the rest are cumulative.
            $cumulativeDelay += $averageDelay;

            $patientData = $message->patient;
            if (!$patientData || empty(trim($patientData->full_name))) {
                $this->error("Patient data missing for ID: {$message->patient_id}. Skipping.");
                $message->update(['status' => 'failed', 'reason' => 'Patient data missing or empty name']);
                continue;
            }

            $prospect = (object)[
                'id' => $message->patient_id,
                'mobile' => $message->mobile,
                'full_name' => $patientData->full_name,
            ];

            SendMarketingMessage::dispatch($prospect)->delay($cumulativeDelay);
            $message->update(['status' => 'queued']);
            
            $dispatchTime = now()->addSeconds($cumulativeDelay);
            $this->line(" -> Queued job for Patient ID #{$prospect->id}. Dispatching at approximately: " . $dispatchTime->format('H:i:s'));
        }

        $this->info("Successfully queued {$messageCount} jobs.");
        Log::info("Marketing cron: Queued {$messageCount} jobs to be sent before {$deadline->format('H:i')}.");

        return self::SUCCESS;
    }
}