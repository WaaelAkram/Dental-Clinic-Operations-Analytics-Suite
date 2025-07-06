<?php
namespace App\Jobs;

use App\Services\WhatsappManager; // <-- KEY CHANGE #1: Import the Manager
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendManualWhatsappMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $mobile, public string $message) {}

    public function handle(WhatsappManager $whatsappManager): void // <-- KEY CHANGE #2: Inject the Manager
    {
        // <-- KEY CHANGE #3: Use the manager to select the correct channel
        $success = $whatsappManager->channel('service')->sendMessage($this->mobile, $this->message);

        if ($success) {
            DB::table('manual_message_log')->insert(['mobile' => $this->mobile, 'message_content' => $this->message, 'sent_at' => now()]);
            Log::info("Manual Broadcast: Message sent via [service] channel to {$this->mobile}.");
        } else {
            throw new \Exception("Manual Broadcast: Failed to send message to {$this->mobile}.");
        }
    }
}