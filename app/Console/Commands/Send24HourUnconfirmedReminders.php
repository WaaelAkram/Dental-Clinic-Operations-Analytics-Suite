<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyUnconfirmedReminder;
use App\Gateways\ClinicPatientGateway;
use App\Models\SentReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Send24HourUnconfirmedReminders extends Command
{
    protected $signature = 'reminders:send-24hr-unconfirmed';
    protected $description = 'Finds unconfirmed appointments for tomorrow in the next 5-minute window and queues reminders.';

    public function handle(ClinicPatientGateway $gateway): int
    {
        $now = now();
        $startTime = $now->copy()->format('H:i:s');
        $endTime = $now->copy()->addMinutes(5)->format('H:i:s');

        $this->info("Checking for unconfirmed appointments for tomorrow between {$startTime} and {$endTime}.");

        $appointments = $gateway->getTomorrowsAppointmentsInWindow($startTime, $endTime);

        if ($appointments->isEmpty()) {
            $this->info("No unconfirmed appointments found in the target window.");
            return self::SUCCESS;
        }

        $appointmentIdsToCheck = $appointments->pluck('appointment_id')->all();
        $sentIds = SentReminder::whereIn('appointment_id', $appointmentIdsToCheck)->pluck('appointment_id')->all();
        $unsentAppointments = $appointments->whereNotIn('appointment_id', $sentIds);

        if ($unsentAppointments->isEmpty()) {
            $this->info("All appointments in this window have already been reminded.");
            return self::SUCCESS;
        }

        $this->info("Found {$unsentAppointments->count()} new appointments to remind.");
        $dispatchedCount = 0;

        foreach ($unsentAppointments as $appointment) {
            if (empty($appointment->mobile)) {
                Log::warning("Skipping 24hr reminder for appt #{$appointment->appointment_id} due to missing mobile.");
                continue;
            }

            // --- THIS IS THE NEW LOGIC, MIRRORING THE CONFIRMED REMINDER ---
            // If we have already dispatched at least one job in this run,
            // we will pause for a random interval before dispatching the next one.
            if ($dispatchedCount > 0) {
                $delaySeconds = rand(20, 60); // A shorter delay is fine here
                $this->info("... waiting for {$delaySeconds} seconds before next message...");
                sleep($delaySeconds);
            }
            // --- END OF NEW LOGIC ---

            // We dispatch the job immediately (no ->delay() needed).
            // The `sleep()` in the command itself creates the stagger.
            SendDailyUnconfirmedReminder::dispatch($appointment);
            $dispatchedCount++;
            
            $this->line(" -> Queued reminder for appt #{$appointment->appointment_id}.");
        }

        $logMessage = "24hr Unconfirmed Check: Dispatched {$dispatchedCount} jobs.";
        $this->info($logMessage);
        Log::info($logMessage);

        return self::SUCCESS;
    }
}