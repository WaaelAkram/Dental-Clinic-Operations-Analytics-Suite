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

        $conversionWindowDays = config('marketing.conversion_window_days', 90);
        $checkSinceDate = now()->subDays($conversionWindowDays)->startOfDay();

        $prospects = SentMarketingMessage::where('status', 'sent')
            ->whereNull('converted_at')
            ->where('message_sent_at', '>=', $checkSinceDate)
            ->get();

        if ($prospects->isEmpty()) {
            $this->info("No prospects to check for conversion. Exiting.");
            return self::SUCCESS;
        }

        $this->info("Found {$prospects->count()} prospects to check.");

        $prospectIds = $prospects->pluck('patient_id')->all();
        $earliestMessageDate = $prospects->min('message_sent_at');
        
        try {
            $newAppointments = $gateway->getFirstAppointmentsForPatientsAfter($prospectIds, $earliestMessageDate);
        } catch (\Exception $e) {
            $this->error("Failed to query clinic database for conversions: " . $e->getMessage());
            Log::error("Conversion tracking failed: " . $e->getMessage());
            return self::FAILURE;
        }

        if ($newAppointments->isEmpty()) {
            $this->info("No new appointments found for any of the prospects. Exiting.");
            return self::SUCCESS;
        }
        
        $newAppointmentsByPatientId = $newAppointments->keyBy('pt_id');
        $conversionsFound = 0;

        foreach ($prospects as $prospect) {
            if ($newAppointmentsByPatientId->has($prospect->patient_id)) {
                $appointment = $newAppointmentsByPatientId->get($prospect->patient_id);
                
                if (carbon($appointment->new_appointment_date)->isAfter($prospect->message_sent_at)) {
                    $prospect->update([
                        'converted_at' => now(),
                        'new_appointment_id' => $appointment->new_appointment_id,
                        'new_appointment_date' => $appointment->new_appointment_date,
                    ]);
                    $conversionsFound++;
                    $this->line(" -> CONVERSION: Patient ID #{$prospect->patient_id} returned on {$appointment->new_appointment_date}.");
                }
            }
        }

        $this->info("Finished tracking. Found and recorded {$conversionsFound} new conversions.");
        Log::info("Marketing cron: Found and recorded {$conversionsFound} new conversions.");
        
        return self::SUCCESS;
    }
}