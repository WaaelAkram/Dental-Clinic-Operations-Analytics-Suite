<?php
namespace App\Jobs;

use App\Models\SentFeedbackRequest;
use App\Services\WhatsappService;
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

    public function handle(WhatsappService $whatsapp): void
    {
        // ... (message templating logic remains the same) ...
        $messageTemplate = config('feedback.template');
        $feedbackLink = config('feedback.feedback_url');

        $patientName = $this->appointment->full_name;
        $doctorName = $this->appointment->doctor_name ?? 'المركز';

        $message = str_replace(
            ['{patient_name}', '{doctor_name}', '{feedback_link}'],
            [$patientName, $doctorName, $feedbackLink],
            $messageTemplate
        );

        try {
            $success = $whatsapp->sendMessage($this->appointment->mobile, $message);
            
            if ($success) {
                // --- UPDATE THIS BLOCK ---
                SentFeedbackRequest::create([
                    'appointment_id' => $this->appointment->appointment_id,
                    'sent_at' => now(),
                    'mobile' => $this->appointment->mobile, // Save the mobile number
                ]);
                Log::info("Feedback request successfully dispatched to {$this->appointment->mobile} for appointment #{$this->appointment->appointment_id}");
            }
        } catch (\Exception $e) {
            Log::critical("FEEDBACK_SYSTEM_ERROR: Could not process job for appointment ID {$this->appointment->appointment_id}: " . $e->getMessage());
        }
    }
}