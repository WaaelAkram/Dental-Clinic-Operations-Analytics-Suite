<?php
// app/Jobs/SendSingleReminder.php

namespace App\Jobs;

use App\Models\SentReminder;
use App\Services\WhatsappManager; // <-- KEY CHANGE #1: Import the Manager
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

    public function handle(WhatsappManager $whatsappManager): void // <-- KEY CHANGE #2: Inject the Manager
    {
        if (SentReminder::where('appointment_id', $this->appointment->appointment_id)->exists()) {
            return;
        }
        
        // This job now ONLY handles confirmed appointments.
        $messageTemplate = config('reminders.template_confirmed');
        $patientName = $this->appointment->full_name;
        $appointmentTime = Carbon::parse($this->appointment->appointment_time)->format('g:i A');
        $doctorName = $this->appointment->doctor_name ?? 'العيادة';
        App::setLocale('ar');
        $appointmentDate = Carbon::parse($this->appointment->appointment_date)->translatedFormat('l، j F Y');
        App::setLocale('en');
        $message = str_replace(['{patient_name}', '{appointment_time}', '{doctor_name}', '{appointment_date}'], [$patientName, $appointmentTime, $doctorName, $appointmentDate], $messageTemplate);
        
        // <-- KEY CHANGE #3: Use the manager to select the correct channel
        $success = $whatsappManager->channel('service')->sendMessage($this->appointment->mobile, $message);
        
        if ($success) {
            SentReminder::create(['appointment_id' => $this->appointment->appointment_id, 'sent_at' => now()]);
            Log::info("Confirmed reminder dispatched via [service] channel for appt #{$this->appointment->appointment_id}");
        }
    }
}