<?php

namespace App\Console\Commands;

use App\Gateways\ClinicPatientGateway;
use App\Jobs\SendFeedbackRequest;
use App\Models\SentFeedbackRequest as SentFeedback;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendFeedbackRequests extends Command
{
    protected $signature = 'feedback:send';
    protected $description = 'Finds appointments that recently finished and sends feedback requests.';

    // --- REPLACE THE ENTIRE HANDLE METHOD WITH THIS ---
    public function handle(ClinicPatientGateway $gateway): int
    {
        $delayHoursStart = config('feedback.delay_hours_start', 2);
        $delayHoursEnd = config('feedback.delay_hours_end', 3);
        $now = Carbon::now();
        $startWindow = $now->copy()->subHours($delayHoursEnd)->format('H:i:s');
        $endWindow = $now->copy()->subHours($delayHoursStart)->format('H:i:s');
        $this->info("Looking for appointments that ended between {$startWindow} and {$endWindow}.");

        // Step 1: Get eligible appointments from the clinic DB
        try {
            $eligibleAppointments = $gateway->getAppointmentsFinishedInWindow($startWindow, $endWindow);
        } catch (\Exception $e) {
            $this->error("Failed to query the clinic database. Check the logs.");
            Log::error("Feedback command failed when calling getAppointmentsFinishedInWindow: " . $e->getMessage());
            return self::FAILURE;
        }

        if ($eligibleAppointments->isEmpty()) {
            $this->info('No appointments ended within the target window.');
            return self::SUCCESS;
        }

        // Step 2: Filter out appointments that have already been processed (by specific appointment_id)
        $appointmentIdsToCheck = $eligibleAppointments->pluck('appointment_id')->all();
        $sentAppointmentIds = SentFeedback::whereIn('appointment_id', $appointmentIdsToCheck)->pluck('appointment_id')->all();
        $unprocessedAppointments = $eligibleAppointments->whereNotIn('appointment_id', $sentAppointmentIds);

        if ($unprocessedAppointments->isEmpty()) {
            $this->info("All eligible appointments in this window have already been processed.");
            return self::SUCCESS;
        }

        // Step 3: Filter out patients who have been contacted recently (by mobile number)
        $cooldownMonths = config('feedback.resend_cooldown_months', 4);
        $finalAppointmentsToSend = $unprocessedAppointments; // Start with the unprocessed list

        if ($cooldownMonths > 0 && $unprocessedAppointments->isNotEmpty()) {
            $mobileNumbers = $unprocessedAppointments->pluck('mobile')->unique()->filter();
            
            if ($mobileNumbers->isNotEmpty()) {
                $exclusionPeriod = now()->subMonths($cooldownMonths);
                $this->info("Applying a {$cooldownMonths}-month cooldown. Checking for patients contacted since {$exclusionPeriod->format('Y-m-d')}.");

                $recentlyContactedMobiles = SentFeedback::whereIn('mobile', $mobileNumbers->all())
                    ->where('sent_at', '>=', $exclusionPeriod)
                    ->pluck('mobile')
                    ->unique()
                    ->all();

                if (!empty($recentlyContactedMobiles)) {
                    $finalAppointmentsToSend = $unprocessedAppointments->whereNotIn('mobile', $recentlyContactedMobiles);
                    $excludedCount = $unprocessedAppointments->count() - $finalAppointmentsToSend->count();
                    if ($excludedCount > 0) {
                       $this->line(".. Cooldown active: {$excludedCount} patient(s) excluded.");
                    }
                }
            }
        }

        if ($finalAppointmentsToSend->isEmpty()) {
            $this->info("No new appointments to message after applying filters.");
            return self::SUCCESS;
        }
        
        // Step 4: Loop over the final list and dispatch jobs
        $dispatchedCount = 0;
        foreach ($finalAppointmentsToSend as $appointment) {
            if (empty($appointment->mobile)) {
                Log::warning("Skipping feedback request for appointment #{$appointment->appointment_id} due to missing mobile number.");
                continue;
            }

            if ($dispatchedCount > 0) {
                $delaySeconds = rand(40, 120);
                $this->info("... waiting for {$delaySeconds} seconds before next message...");
                sleep($delaySeconds);
            }
            
            SendFeedbackRequest::dispatch($appointment);
            $dispatchedCount++;
        }

        // Step 5: Log the final results
        $logMessage = "Feedback Check: Found {$eligibleAppointments->count()} eligible. " .
                      "Filtered to {$unprocessedAppointments->count()} unprocessed appointments. " .
                      "After cooldown, {$finalAppointmentsToSend->count()} are ready to message. " .
                      "Dispatched {$dispatchedCount} new jobs.";
        $this->info($logMessage);
        Log::info($logMessage);

        return self::SUCCESS;
    }
}