<?php
// app/Jobs/SendSingleReminder.php

namespace App\Jobs;

use App\Models\SentReminder;
use App\Services\WhatsappService; // <-- IMPORT THE SERVICE
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;

class SendSingleReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $appointment;

    public function __construct(\stdClass $appointment)
    {
        $this->appointment = $appointment;
    }

    public function handle(WhatsappService $whatsapp): void // <-- INJECT THE SERVICE
    {  if (SentReminder::where('appointment_id', $this->appointment->appointment_id)->exists()) {
        Log::info("Skipping reminder for appointment #{$this->appointment->appointment_id} because it was already processed by a concurrent job.");
        return; // Exit the job gracefully.
    }
        // Determine which template to use based on appointment status
        if ($this->appointment->app_status == 1) { // Confirmed
            $messageTemplate = config('reminders.template_confirmed');
        } elseif ($this->appointment->app_status == 0) { // Unconfirmed
            $messageTemplate = config('reminders.template_unconfirmed');
        } else {
            Log::warning("Tried to send reminder for an appointment with an invalid status.", [
                'appointment_id' => $this->appointment->appointment_id,
                'status' => $this->appointment->app_status
            ]);
            return;
        }

        // Prepare the placeholder values
        $patientName = $this->appointment->full_name;
        $appointmentTime = Carbon::parse($this->appointment->appointment_time)->format('g:i A');
        $doctorName = $this->appointment->doctor_name ?? 'العيادة'; 

        $originalLocale = App::getLocale();
        App::setLocale('ar');
        $appointmentDate = Carbon::parse($this->appointment->appointment_date)->translatedFormat('l، j F Y');
        App::setLocale($originalLocale);

        $message = str_replace(
            ['{patient_name}', '{appointment_time}', '{doctor_name}', '{appointment_date}'],
            [$patientName, $appointmentTime, $doctorName, $appointmentDate],
            $messageTemplate
        );

        // --- THIS IS THE KEY CHANGE ---
        // Send the message via our WhatsApp service
        $success = $whatsapp->sendMessage($this->appointment->mobile, $message);
        // --- END OF CHANGE ---
        
        if ($success) {
            // Log that the reminder was sent
            SentReminder::create([
                'appointment_id' => $this->appointment->appointment_id,
                'sent_at' => now(),
            ]);
            Log::info("Reminder successfully dispatched to {$this->appointment->mobile} for appointment #{$this->appointment->appointment_id}");
        } else {
            Log::error("Failed to dispatch reminder via WhatsApp API for appointment #{$this->appointment->appointment_id}");
        }
    }
}