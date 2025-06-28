<?php
// app/Console/Commands/SendAppointmentReminders.php

namespace App\Console\Commands;

use App\Gateways\ClinicPatientGateway;
use App\Jobs\SendSingleReminder;
use App\Models\SentReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendAppointmentReminders extends Command
{
    protected $signature = 'reminders:send';
    protected $description = 'Finds all upcoming appointments within the reminder window and sends notifications.';

    public function handle(ClinicPatientGateway $gateway): int
    {
        $reminderWindows = config('reminders.windows');
        $now = now();

        $maxWindowMinutes = max($reminderWindows);
        $this->info("Max reminder window is {$maxWindowMinutes} minutes.");

        $startTime = $now->copy()->format('H:i:s');
        $endTime = $now->copy()->addMinutes($maxWindowMinutes)->format('H:i:s');
        $appointmentsInMaxWindow = $gateway->getAppointmentsInWindow($startTime, $endTime);

        if ($appointmentsInMaxWindow->isEmpty()) {
            $this->info("No appointments found in the upcoming {$maxWindowMinutes} minute window.");
            return self::SUCCESS;
        }

        $appointmentIdsToCheck = $appointmentsInMaxWindow->pluck('appointment_id')->all();
        $sentIds = SentReminder::whereIn('appointment_id', $appointmentIdsToCheck)
            ->pluck('appointment_id')
            ->all();
        
        $unsentAppointments = $appointmentsInMaxWindow->whereNotIn('appointment_id', $sentIds);

        if ($unsentAppointments->isEmpty()) {
            $this->info("All upcoming appointments have already been reminded.");
            return self::SUCCESS;
        }

        $dispatchedCount = 0;
        foreach ($unsentAppointments as $appointment) {
            
            if (empty($appointment->mobile)) {
                Log::warning("Skipping reminder for appointment #{$appointment->appointment_id} due to missing mobile number.");
                continue;
            }
            
            // Parse both timestamps using Carbon for accurate comparison
            $appointmentTime = Carbon::parse($appointment->appointment_date . ' ' . $appointment->appointment_time);
            $creationTime = Carbon::parse($appointment->created_at);

            // --- THIS IS THE NEW LOGIC ---
            // Check if the appointment was created less than an hour before its start time
            if ($creationTime->diffInMinutes($appointmentTime) < 120) {
                $this->line(".. Skipping last-minute appointment #{$appointment->appointment_id}. Booked too close to appointment time.");
                continue; // Skip this appointment and go to the next one
            }
            // --- END OF NEW LOGIC ---

            if ($dispatchedCount > 0) {
                $delaySeconds = rand(40, 120);
                $this->info("... waiting for {$delaySeconds} seconds before next message...");
                sleep($delaySeconds);
            }

            $status = $appointment->app_status;
            if (!isset($reminderWindows[$status])) {
                continue;
            }
            $specificWindow = $reminderWindows[$status];

            if ($appointmentTime->isBetween($now, $now->copy()->addMinutes($specificWindow))) {
                SendSingleReminder::dispatch($appointment);
                $dispatchedCount++;
                $this->line(" -> Queued reminder for appointment #{$appointment->appointment_id} (Status: {$status}, Window: {$specificWindow} mins)");
            }
        }

        $logMessage = "Checked {$appointmentsInMaxWindow->count()} appointments. Dispatched {$dispatchedCount} new reminder jobs.";
        $this->info($logMessage);
        Log::info($logMessage);

        return self::SUCCESS;
    }
}