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

   // ... (keep the other parts of the file)

    public function handle(): int
    {
        $stagedMessages = SentMarketingMessage::where('status', 'staging')
            ->where('process_date', today())
            ->get();

        if ($stagedMessages->isEmpty()) {
            $this->info("No staged messages found to queue for today.");
            return self::SUCCESS;
        }

        $this->info("Found {$stagedMessages->count()} staged messages. Preparing to queue them with simple second-based delays...");

        // Define a simple random delay range (in seconds) to add between each message.
        $minDelay = 480; // 8 minutes in seconds
        $maxDelay = 720; // 12 minutes in seconds
        
        // This will be the cumulative delay for each job.
        $totalDelayForNextJob = 0;

        foreach ($stagedMessages as $message) {
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

            // Dispatch the job with the current total delay.
            SendMarketingMessage::dispatch($prospect)->delay($totalDelayForNextJob);

            $message->update(['status' => 'queued']);
            
            // Provide clear feedback on when the job is scheduled to run.
            $dispatchTime = now()->addSeconds($totalDelayForNextJob);
            $this->line(" -> Queued job for Patient ID #{$prospect->id}. Dispatching at approximately: " . $dispatchTime->format('H:i:s'));

            // Add a random delay for the *next* job.
            $totalDelayForNextJob += rand($minDelay, $maxDelay);
        }

        $this->info("Successfully queued {$stagedMessages->count()} jobs with randomized delays.");
        Log::info("Marketing cron: Queued {$stagedMessages->count()} jobs with simple delays.");

        return self::SUCCESS;
    }
}