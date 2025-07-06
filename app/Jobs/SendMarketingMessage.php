<?php
namespace App\Jobs;

use App\Models\SentMarketingMessage;
use App\Services\WhatsappManager; // <-- KEY CHANGE #1: Import the Manager
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

    public function __construct(public object $patient) {}

    public function handle(WhatsappManager $whatsappManager): void // <-- KEY CHANGE #2: Inject the Manager
    {
        $messageTemplate = config('marketing.lapsed_patient_template');
        $message = str_replace('{patient_name}', $this->patient->full_name, $messageTemplate);
        
        // <-- KEY CHANGE #3: Use the manager to select the correct channel
        $success = $whatsappManager->channel('service')->sendMessage($this->patient->mobile, $message);

        if ($success) {
            SentMarketingMessage::where('patient_id', $this->patient->id)->update(['status' => 'sent', 'message_sent_at' => now()]);
            Log::info("Marketing message dispatched via [service] channel to patient ID {$this->patient->id}.");
        } else {
            throw new \Exception("Failed to send marketing message via WhatsApp to patient ID {$this->patient->id}.");
        }
    }

    public function failed(Throwable $exception): void
    {
        SentMarketingMessage::where('patient_id', $this->patient->id)->update(['status' => 'failed']);
        Log::critical("PERMANENT_FAILURE: Marketing message for patient ID {$this->patient->id} failed after all retries.", ['error' => $exception->getMessage()]);
    }
}