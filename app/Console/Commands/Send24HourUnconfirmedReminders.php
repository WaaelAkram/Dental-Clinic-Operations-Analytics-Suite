<?php
namespace App\Console\Commands;

use App\Gateways\ClinicPatientGateway;
use App\Jobs\SendDailyUnconfirmedReminder;
use App\Models\SentReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Send24HourUnconfirmedReminders extends Command
{
    protected $signature = 'reminders:send-24hr-unconfirmed';
    protected $description = 'Finds unconfirmed appointments for tomorrow in the next 5-minute window and queues a reminder.';

    public function handle(ClinicPatientGateway $gateway): int
    {
        $now = now();
        $startTime = $now->copy()->format('H:i:s');
        $endTime = $now->copy()->addMinutes(5)->format('H:i:s');

        $this->info("Checking for unconfirmed appointments for tomorrow between {$startTime} and {$endTime}.");
        $appointments = $gateway->getTomorrowsAppointmentsInWindow($startTime, $endTime);

        if ($appointments->isEmpty()) {
            return self::SUCCESS;
        }

        $appointmentIdsToCheck = $appointments->pluck('appointment_id')->all();
        $sentIds = SentReminder::whereIn('appointment_id', $appointmentIdsToCheck)->pluck('appointment_id')->all();
        $unsentAppointments = $appointments->whereNotIn('appointment_id', $sentIds);

        if ($unsentAppointments->isEmpty()) {
            return self::SUCCESS;
        }

        $dispatchedCount = 0;
        foreach ($unsentAppointments as $appointment) {
            if (empty($appointment->mobile)) { continue; }
            SendDailyUnconfirmedReminder::dispatch($appointment);
            $dispatchedCount++;
        }

        Log::info("24hr Unconfirmed Check: Dispatched {$dispatchedCount} jobs.");
        return self::SUCCESS;
    }
}