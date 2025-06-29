<?php

namespace App\Console\Commands\Marketing;

use App\Gateways\ClinicPatientGateway;
use App\Models\SentMarketingMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TrackConversions extends Command
{
    protected $signature = 'marketing:track-conversions';
    protected $description = 'Checks for returning patients from marketing campaigns and updates their conversion status.';

    public function handle(ClinicPatientGateway $gateway): int
    {
        $this->info("Starting marketing conversion tracking...");

        // 1. Get a list of patients we need to check.
        $conversionWindowDays = config('marketing.conversion_window_days', 60);
        $checkSinceDate = now()->subDays($conversionWindowDays)->startOfDay();

        $prospects = SentMarketingMessage::where('status', 'sent')
            ->whereNull('converted_at') // Only check those who haven't converted yet.
            ->where('message_sent_at', '>=', $checkSinceDate) // Only check within the conversion window.
            ->get();

        if ($prospects->isEmpty()) {
            $this->info("No prospects to check for conversion. Exiting.");
            return self::SUCCESS;
        }

        $this->info("Found {$prospects->count()} prospects to check for new appointments since {$checkSinceDate->toDateString()}.");

        // 2. Get the patient IDs and find their earliest new appointment in a single batch query.
        $prospectIds = $prospects->pluck('patient_id')->all();
        
        // Note: We use the earliest message date as the start date for our appointment search.
        $earliestMessageDate = $prospects->min('message_sent_at');
        
        $newAppointments = $gateway->getFirstAppointmentsForPatientsAfter($prospectIds, $earliestMessageDate);

        if ($newAppointments->isEmpty()) {
            $this->info("No new appointments found for any of the prospects. Exiting.");
            return self::SUCCESS;
        }
        
        // For faster lookups, key the appointments by patient_id.
        $newAppointmentsByPatientId = $newAppointments->keyBy('pt_id');
        $conversionsFound = 0;

        // 3. Loop through our prospects and check for conversions.
        foreach ($prospects as $prospect) {
            // Check if this prospect has a new appointment recorded.
            if ($newAppointmentsByPatientId->has($prospect->patient_id)) {
                $appointment = $newAppointmentsByPatientId->get($prospect->patient_id);
                
                // Final check: Ensure the new appointment is actually AFTER the message was sent.
                if (carbon_parse($appointment->new_appointment_date)->isAfter($prospect->message_sent_at)) {
                    $prospect->update([
                        'converted_at' => now(),
                        'new_appointment_id' => $appointment->new_appointment_id,
                        'new_appointment_date' => $appointment->new_appointment_date,
                    ]);
                    $conversionsFound++;
                    $this->line(" -> Conversion Found! Patient ID #{$prospect->patient_id} returned for an appointment on {$appointment->new_appointment_date}.");
                }
            }
        }

        $this->info("Finished tracking. Found and recorded {$conversionsFound} new conversions.");
        Log::info("Marketing cron: Found and recorded {$conversionsFound} new conversions.");
        
        return self::SUCCESS;
    }
}