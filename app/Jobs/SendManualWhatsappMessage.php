<?php

namespace App\Jobs;

use App\Services\WhatsappService;
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

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $mobile,
        public string $message
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WhatsappService $whatsapp): void
    {
        $success = $whatsapp->sendMessage($this->mobile, $this->message);

        if ($success) {
            // Log the successful send to our manual log table
            DB::table('manual_message_log')->insert([
                'mobile' => $this->mobile,
                'message_content' => $this->message,
                'sent_at' => now()
            ]);
            Log::info("Manual Broadcast: Message successfully sent to {$this->mobile}.");
        } else {
            // Throw an exception to let the queue system handle the retry
            throw new \Exception("Manual Broadcast: Failed to send message to {$this->mobile}.");
        }
    }
}