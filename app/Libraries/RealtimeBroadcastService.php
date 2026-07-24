<?php

namespace App\Libraries;

class RealtimeBroadcastService
{
    private string $sidecarUrl;
    private string $secret;

    public function __construct()
    {
        $this->sidecarUrl = getenv('EBIDHUB_WS_INTERNAL_URL') ?: 'http://127.0.0.1:8081/broadcast';
        $this->secret = getenv('EBIDHUB_BROADCAST_SECRET') ?: 'dev-only-change-in-production';
    }

    public function broadcast(string $saleEventId, string $event, array $data = []): bool
    {
        $payload = json_encode([
            'secret' => $this->secret,
            'saleEventId' => $saleEventId,
            'event' => $event,
            'data' => $data,
        ]);

        $ch = curl_init($this->sidecarUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => 500,
            CURLOPT_CONNECTTIMEOUT_MS => 300,
        ]);
        $result = curl_exec($ch);
        $success = $result !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);

        return $success;
    }
}
