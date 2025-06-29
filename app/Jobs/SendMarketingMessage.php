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

    public function __construct(public object $patient)
    {
        //
    }

    public function handle(WhatsappService $whatsapp): void
    {
        $messageTemplate = config('marketing.lapsed_patient_template');
        $message = str_replace('{patient_name}', $this->patient->full_name, $messageTemplate);

        $success = $whatsapp->sendMessage($this->patient->mobile, $message);

        if ($success) {
            SentMarketingMessage::where('patient_id', $this->patient->id)->update([
                'status' => 'sent',
                'message_sent_at' => now(),
            ]);
            Log::info("Marketing message successfully dispatched to {$this->patient->mobile} (Patient ID: {$this->patient->id}).");
        } else {
            // Let the queue handle the failure and retry.
            // Throwing an exception signals the job failed.
            throw new \Exception("Failed to send marketing message via WhatsApp Service to patient ID {$this->patient->id}.");
        }
    }

    public function failed(Throwable $exception): void
    {
        // This runs if the job fails all of its retry attempts.
        SentMarketingMessage::where('patient_id', $this->patient->id)->update(['status' => 'failed']);
        Log::critical("PERMANENT_FAILURE: Marketing message for patient ID {$this->patient->id} failed to send after all retries.", [
            'error' => $exception->getMessage()
        ]);
    }
}