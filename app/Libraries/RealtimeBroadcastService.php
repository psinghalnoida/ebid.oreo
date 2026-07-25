<?php

namespace App\Libraries;

class RealtimeBroadcastService
{
    private string $sidecarBaseUrl;
    private string $secret;

    public function __construct()
    {
        $configuredUrl = getenv('EBIDHUB_WS_INTERNAL_URL') ?: 'http://127.0.0.1:8081/broadcast';
        // Derive the base host:port once, so both endpoints (/broadcast
        // and /broadcast-to-buyer) build cleanly from it regardless of
        // exactly how EBIDHUB_WS_INTERNAL_URL is configured — fragile
        // string-replacement was the first draft here, fixed before
        // this ever shipped.
        $this->sidecarBaseUrl = rtrim(preg_replace('#/broadcast.*$#', '', $configuredUrl), '/');
        $this->secret = getenv('EBIDHUB_BROADCAST_SECRET') ?: 'dev-only-change-in-production';
    }

    public function broadcast(string $saleEventId, string $event, array $data = []): bool
    {
        return $this->send($this->sidecarBaseUrl . '/broadcast', ['saleEventId' => $saleEventId, 'event' => $event, 'data' => $data]);
    }

    public function broadcastToBuyer(string $buyerId, string $event, array $data = []): bool
    {
        return $this->send($this->sidecarBaseUrl . '/broadcast-to-buyer', ['buyerId' => $buyerId, 'event' => $event, 'data' => $data]);
    }

    private function send(string $url, array $payload): bool
    {
        $payload['secret'] = $this->secret;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
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
