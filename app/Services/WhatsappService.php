<?php
// app/Services/WhatsappService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    protected string $apiUrl;

    public function __construct()
    {
        $host = config('whatsapp.api_host');
        $port = config('whatsapp.api_port');
        $this->apiUrl = "{$host}:{$port}";
    }

    /**
     * Formats a local phone number into the international E.164 format.
     * Assumes Saudi Arabia country code (966). This is the key function.
     *
     * @param string $number
     * @return string|null Null if the number is invalid.
     */
    private function formatNumber(string $number): ?string
    {
        // 1. Remove any non-numeric characters (+, -, spaces)
        $number = preg_replace('/[^0-9]/', '', $number);

        // 2. Handle numbers that start with '05' (local format)
        // Example: 0538265504 -> 966538265504
        if (substr($number, 0, 2) === '05' && strlen($number) === 10) {
            return '966' . substr($number, 1);
        }
        
        // 3. Handle numbers that start with '5' (missing leading zero)
        // Example: 538265504 -> 966538265504
        if (substr($number, 0, 1) === '5' && strlen($number) === 9) {
            return '966' . $number;
        }

        // 4. Handle numbers that already have the country code
        // Example: 966538265504 -> 966538265504
        if (substr($number, 0, 3) === '966' && strlen($number) === 12) {
            return $number;
        }

        // 5. If none of the above match, the number is in an unknown or invalid format.
        Log::warning("Invalid or unformattable phone number provided: {$number}");
        return null;
    }

    /**
     * Checks if the WhatsApp client is ready to send messages.
     */
    private function isClientReady(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->apiUrl}/status");
            return $response->successful() && $response->json('status') === 'ready';
        } catch (\Exception $e) {
            Log::error("Could not connect to WhatsApp API to check status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sends a message via the WhatsApp API service.
     */
    public function sendMessage(string $to, string $message): bool
    {
        // === FIX #1: FORMAT THE NUMBER ===
        $formattedNumber = $this->formatNumber($to);

        if (is_null($formattedNumber)) {
            // The number was invalid, do not proceed.
            return false;
        }
        
        // === FIX #2: CHECK IF CLIENT IS READY ===
        if (!$this->isClientReady()) {
            Log::warning("WhatsApp API is not ready. Aborting message send to {$formattedNumber}. The job will be retried.");
            return false;
        }

        try {
            $response = Http::timeout(30)->post("{$this->apiUrl}/send-message", [
                'to' => $formattedNumber, // Use the correctly formatted number
                'message' => $message,
            ]);

            if ($response->successful()) {
                Log::info("WhatsApp message successfully sent to {$to} (formatted as {$formattedNumber}). Response: " . $response->body());
                return true;
            }

            Log::error("Failed to send WhatsApp message to {$to}. Status: {$response->status()}. Body: {$response->body()}");
            return false;

        } catch (\Exception $e) {
            Log::critical("Exception while trying to send WhatsApp message to {$to}: " . $e->getMessage());
            return false;
        }
    }
}