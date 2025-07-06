<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappManager
{
    protected array $channels = [];
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get a specific WhatsApp channel instance.
     *
     * @param string|null $name The name of the channel (e.g., 'service', 'operations')
     * @return \App\Services\WhatsappService
     */
    public function channel(string $name = null): WhatsappService
    {
        // If no name is provided, use the default from the config file.
        $name = $name ?: $this->config['default'];

        // If we haven't created an instance for this channel yet, create it now.
        if (!isset($this->channels[$name])) {
            $this->channels[$name] = $this->resolve($name);
        }

        // Return the stored channel instance.
        return $this->channels[$name];
    }

    /**
     * Create a new WhatsappService instance for the given channel name.
     */
    protected function resolve(string $name): WhatsappService
    {
        $config = $this->config['channels'][$name] ?? null;

        if (is_null($config)) {
            throw new \InvalidArgumentException("WhatsApp channel [{$name}] is not configured in config/whatsapp.php.");
        }

        // Create a new WhatsappService, passing it the specific host, port, and name for this channel.
        return new WhatsappService($config['host'], $config['port'], $name);
    }
}