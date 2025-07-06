<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    protected string $apiUrl;
    protected string $channelName;

    /**
     * The constructor now accepts the host, port, and channel name.
     * This allows the WhatsappManager to create correctly configured instances.
     */
    public function __construct(string $host, string $port, string $channelName)
    {
        $this->apiUrl = "{$host}:{$port}";
        $this->channelName = $channelName;
    }

    private function formatNumber(string $number): ?string
    {
        // ... (this method does not need to change)
        $number = preg_replace('/[^0-9]/', '', $number);
        if (substr($number, 0, 2) === '05' && strlen($number) === 10) { return '966' . substr($number, 1); }
        if (substr($number, 0, 1) === '5' && strlen($number) === 9) { return '966' . $number; }
        if (substr($number, 0, 3) === '966' && strlen($number) === 12) { return $number; }
        Log::warning("Invalid or unformattable phone number provided: {$number}", ['channel' => $this->channelName]);
        return null;
    }

    private function isClientReady(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->apiUrl}/status");
            return $response->successful() && $response->json('status') === 'ready';
        } catch (\Exception $e) {
            Log::error("Could not connect to WhatsApp API [{$this->channelName}] to check status: " . $e->getMessage());
            return false;
        }
    }

    public function sendMessage(string $to, string $message): bool
    {
        $formattedNumber = $this->formatNumber($to);
        if (is_null($formattedNumber)) {
            return false;
        }

        if (!$this->isClientReady()) {
            Log::warning("WhatsApp API [{$this->channelName}] is not ready. Aborting message to {$formattedNumber}.");
            return false;
        }

        try {
            $response = Http::timeout(30)->post("{$this->apiUrl}/send-message", [
                'to' => $formattedNumber,
                'message' => $message,
            ]);

            if ($response->successful()) {
                Log::info("WhatsApp message via [{$this->channelName}] sent to {$to}.");
                return true;
            }

            Log::error("Failed to send WhatsApp message via [{$this->channelName}] to {$to}. Status: {$response->status()}.");
            return false;
        } catch (\Exception $e) {
            Log::critical("Exception on channel [{$this->channelName}] sending to {$to}: " . $e->getMessage());
            return false;
        }
    }
}