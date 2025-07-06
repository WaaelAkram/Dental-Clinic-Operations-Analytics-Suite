<?php
namespace App\Jobs;

use App\Models\SentReminder;
use App\Services\WhatsappManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use Carbon\Carbon;

class SendDailyUnconfirmedReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $appointment;

    public function __construct(\stdClass $appointment) { $this->appointment = $appointment; }

    public function handle(WhatsappManager $whatsappManager): void
    {
        if (SentReminder::where('appointment_id', $this->appointment->appointment_id)->exists()) { return; }

        $messageTemplate = config('reminders.template_unconfirmed');
        $patientName = $this->appointment->full_name;
        $appointmentTime = Carbon::parse($this->appointment->appointment_time)->format('g:i A');
        $doctorName = $this->appointment->doctor_name ?? 'العيادة';
        App::setLocale('ar');
        $appointmentDate = Carbon::parse($this->appointment->appointment_date)->translatedFormat('l، j F Y');
        App::setLocale('en');
        $message = str_replace(['{patient_name}', '{appointment_time}', '{doctor_name}', '{appointment_date}'], [$patientName, $appointmentTime, $doctorName, $appointmentDate], $messageTemplate);

        $success = $whatsappManager->channel('operations')->sendMessage($this->appointment->mobile, $message);

        if ($success) {
            SentReminder::create(['appointment_id' => $this->appointment->appointment_id, 'sent_at' => now()]);
            Log::info("24hr unconfirmed reminder dispatched via [operations] channel for appt #{$this->appointment->appointment_id}");
        }
    }
}