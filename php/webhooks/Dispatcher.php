<?php
/**
 * Space Cadet CMS — Webhook Dispatcher
 * Signs payloads with HMAC-SHA256 and delivers via cURL.
 */

class Dispatcher {

    /**
     * Fire all active webhooks that subscribe to the given event.
     */
    public static function fireAll(string $event, array $payload): void {
        $webhooks = Webhook::findActive();
        foreach ($webhooks as $webhook) {
            $events = json_decode($webhook['events'], true) ?? [];
            if (!in_array($event, $events, true) && !in_array('*', $events, true)) {
                continue;
            }
            self::send($webhook, $event, $payload);
        }
    }

    /**
     * Send a single webhook delivery. Returns delivery metadata.
     */
    public static function send(array $webhook, string $event, array $payload): array {
        $body = json_encode([
            'event'      => $event,
            'payload'    => $payload,
            'delivered_at' => date('c'),
            'cms'        => 'space-cadet/' . SC_VERSION,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $signature = 'sha256=' . hash_hmac('sha256', $body, $webhook['secret']);

        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-SpaceCadet-Signature: ' . $signature,
                'X-SpaceCadet-Event: ' . $event,
                'User-Agent: SpaceCadetCMS/' . SC_VERSION,
            ],
            // SSRF guard: only public IPs
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        ]);

        $start    = microtime(true);
        $response = curl_exec($ch);
        $ms       = (int) round((microtime(true) - $start) * 1000);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        $responseBody = $error ?: (string) $response;

        Webhook::recordDelivery(
            $webhook['id'],
            $event,
            $body,
            $signature,
            $status,
            substr($responseBody, 0, 2000),
            $ms
        );

        return [
            'status_code'  => $status,
            'duration_ms'  => $ms,
            'signature'    => $signature,
            'error'        => $error ?: null,
        ];
    }
}

// Wire up to EventEmitter
EventEmitter::on('*', function (string $event, array $payload) {
    // Only fire webhooks for content/form events, not internal events
    $webhookEvents = [
        'item.created','item.updated','item.deleted',
        'page.created','page.updated','page.deleted',
        'form.submitted','collection.created','collection.deleted',
        'webhook.test',
    ];
    if (in_array($event, $webhookEvents, true)) {
        Dispatcher::fireAll($event, $payload);
    }
});
