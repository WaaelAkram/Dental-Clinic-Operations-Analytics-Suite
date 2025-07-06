<?php
namespace App\Jobs;

use App\Models\SentFeedbackRequest;
use App\Services\WhatsappManager; // <-- KEY CHANGE #1: Import the Manager
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFeedbackRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $appointment;

    public function __construct(\stdClass $appointment)
    {
        $this->appointment = $appointment;
    }

    public function handle(WhatsappManager $whatsappManager): void // <-- KEY CHANGE #2: Inject the Manager
    {
        $messageTemplate = config('feedback.template');
        $feedbackLink = config('feedback.feedback_url');
        $patientName = $this->appointment->full_name;
        $doctorName = $this->appointment->doctor_name ?? 'المركز';
        $message = str_replace(['{patient_name}', '{doctor_name}', '{feedback_link}'], [$patientName, $doctorName, $feedbackLink], $messageTemplate);

        try {
            // <-- KEY CHANGE #3: Use the manager to select the correct channel
            $success = $whatsappManager->channel('service')->sendMessage($this->appointment->mobile, $message);
            
            if ($success) {
                SentFeedbackRequest::create([
                    'appointment_id' => $this->appointment->appointment_id,
                    'sent_at' => now(),
                    'mobile' => $this->appointment->mobile,
                ]);
                Log::info("Feedback request successfully dispatched via [service] channel to {$this->appointment->mobile} for appt #{$this->appointment->appointment_id}");
            }
        } catch (\Exception $e) {
            Log::critical("FEEDBACK_SYSTEM_ERROR: Could not process job for appointment ID {$this->appointment->appointment_id}: " . $e->getMessage());
        }
    }
}