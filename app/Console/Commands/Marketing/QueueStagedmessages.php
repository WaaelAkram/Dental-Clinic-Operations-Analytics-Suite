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
    // 1. Find the work that needs to be done for today.
    $stagedMessages = SentMarketingMessage::where('status', 'staging')
        ->where('process_date', today())
        ->get();

    if ($stagedMessages->isEmpty()) {
        $this->info("No staged messages found to queue for today.");
        return self::SUCCESS;
    }

    $this->info("Found {$stagedMessages->count()} staged messages. Preparing to queue them with delays...");

    // ======================= THE NEW, MORE ROBUST LOGIC =======================
    // 2. Define the sending window with today's date explicitly.
    $startTime = Carbon::today()->setTimeFromTimeString(config('marketing.send_window_start', '15:00'));
    $endTime = Carbon::today()->setTimeFromTimeString(config('marketing.send_window_end', '21:30'));
    $now = now();

    // Check if the entire sending window has already passed for today.
    if ($now->isAfter($endTime)) {
        $this->error("The sending window for today has already closed at {$endTime->format('H:i')}. No messages will be queued.");
        Log::error("Marketing cron: Attempted to queue messages after the sending window closed.");
        return self::FAILURE;
    }

    // THIS IS THE KEY FIX:
    // Determine the effective start time for queuing.
    $effectiveStartTime = $now->isAfter($startTime) ? $now : $startTime;
    
    // Calculate the total remaining seconds in the window.
    $remainingWindowSeconds = $endTime->diffInSeconds($effectiveStartTime);
    $batchCount = $stagedMessages->count();

    // Prevent division by zero if there's only one message.
    $interval = $batchCount > 1 ? $remainingWindowSeconds / ($batchCount - 1) : 0;
    // =================== END OF NEW LOGIC ===================

    // 3. Loop, dispatch with a calculated delay, and update status.
    foreach ($stagedMessages as $index => $message) {
        $patientData = $message->patient;
        if (!$patientData || empty($patientData->full_name)) { // Added a check for empty name
            $this->error("Could not find patient details for ID: {$message->patient_id} or name is empty. Skipping.");
            $message->update(['status' => 'failed', 'reason' => 'Patient data missing']);
            continue;
        }

        $prospect = (object)[
            'id' => $message->patient_id,
            'mobile' => $message->mobile,
            'full_name' => $patientData->full_name,
        ];

        // The delay is now an offset in seconds from *this moment*.
        $delayInSeconds = floor($index * $interval);

        // Dispatch the job with the calculated delay in seconds.
        SendMarketingMessage::dispatch($prospect)->delay($delayInSeconds);

        $message->update(['status' => 'queued']);
        
        $dispatchTime = now()->addSeconds($delayInSeconds);
        $this->line(" -> Queued job for Patient ID #{$prospect->id}. Dispatching at approximately: " . $dispatchTime->format('H:i:s'));
    }

    $this->info("Successfully queued {$batchCount} jobs to be sent between {$effectiveStartTime->format('H:i')} and {$endTime->format('H:i')}.");
    Log::info("Marketing cron: Queued {$batchCount} jobs with staggered delays.");

    return self::SUCCESS;
}
}