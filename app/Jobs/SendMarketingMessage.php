<?php

namespace App\Jobs;

use App\Models\SentMarketingMessage;
use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendMarketingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param object $patient An object containing patient details (id, full_name, mobile)
     */
    public function __construct(public object $patient)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsappService $whatsapp): void
    {
        $messageTemplate = config('marketing.lapsed_patient_template');
        $message = str_replace('{patient_name}', $this->patient->full_name, $messageTemplate);
    $imageUrl = config('marketing.lapsed_patient_image_url'); // Get the image URL from the config
        $success = $whatsapp->sendMessage($this->patient->mobile, $message);

        if ($success) {
            // Update the status in the database to 'sent'
            SentMarketingMessage::where('patient_id', $this->patient->id)->update([
                'status' => 'sent',
                'message_sent_at' => now(),
            ]);
            Log::info("Marketing message successfully dispatched to {$this->patient->mobile} (Patient ID: {$this->patient->id}).");
        } else {
            // Throwing an exception will cause the job to fail and be retried by the queue worker.
            throw new \Exception("Failed to send marketing message via WhatsApp Service to patient ID {$this->patient->id}.");
        }
    }

    /**
     * Handle a job failure after all retry attempts have been exhausted.
     */
    public function failed(Throwable $exception): void
    {
        // Update the status to 'failed' for permanent failures.
        SentMarketingMessage::where('patient_id', $this->patient->id)->update(['status' => 'failed']);
        Log::critical("PERMANENT_FAILURE: Marketing message for patient ID {$this->patient->id} failed after all retries.", [
            'error' => $exception->getMessage()
        ]);
    }
}