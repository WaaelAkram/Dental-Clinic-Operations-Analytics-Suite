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

    // Get the current time.
    $now = now();

    // Check if the entire sending window has already passed for today.
    if ($now->isAfter($endTime)) {
        $this->error("The sending window for today has already closed at {$endTime->format('H:i')}. No messages will be queued.");
        Log::error("Marketing cron: Attempted to queue messages after the sending window closed.");
        return self::FAILURE;
    }

    // THIS IS THE KEY FIX:
    // If the command is run before the start time, the effective start time for queuing
    // is the one from the config. If it's run *after* the start time, we begin queuing immediately.
    $effectiveStartTime = $now->isAfter($startTime) ? $now : $startTime;
    
    // =================== END OF NEW LOGIC ===================

    $totalWindowSeconds = $endTime->diffInSeconds($effectiveStartTime);
    $batchCount = $stagedMessages->count();

    // Prevent division by zero if there's only one message.
    $interval = $batchCount > 1 ? $totalWindowSeconds / ($batchCount - 1) : 0;

    // 3. Loop, dispatch with a calculated delay, and update status.
    foreach ($stagedMessages as $index => $message) {
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

        // ======================= THE KEY CHANGE =======================
        // Calculate the delay based on the *effective* start time.
        $delayInSeconds = floor($index * $interval);
        $dispatchTime = $effectiveStartTime->copy()->addSeconds($delayInSeconds);

        // Use the ->delay() method on the job. The queue will hold the job until this time.
        SendMarketingMessage::dispatch($prospect)->delay($dispatchTime);
        // =================== END OF KEY CHANGE ===================

        $message->update(['status' => 'queued']);

        $this->line(" -> Queued job for Patient ID #{$prospect->id}. Dispatching at approximately: " . $dispatchTime->format('H:i:s'));
    }

    $this->info("Successfully queued {$batchCount} jobs to be sent between {$effectiveStartTime->format('H:i')} and {$endTime->format('H:i')}.");
    Log::info("Marketing cron: Queued {$batchCount} jobs with staggered delays.");

    return self::SUCCESS;
}
}