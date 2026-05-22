<?php

declare(strict_types=1);

namespace App\Services;

final class StripeService
{
    private array $config;

    public function __construct()
    {
        $this->config = is_file(APP_PATH . '/config/stripe.php') ? require APP_PATH . '/config/stripe.php' : [];
    }

    public function publishableKey(): string
    {
        return trim((string) ($this->config['publishable_key'] ?? env('STRIPE_PUBLISHABLE_KEY', '')));
    }

    public function configured(): bool
    {
        return $this->publishableKey() !== '' && $this->secretKey() !== '';
    }

    public function paymentIntentForOrder(array $order): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'message' => 'Stripe payment is not configured yet.'];
        }

        $amount = $this->amountToMinorUnits((float) $order['invoice_amount'], (string) $order['currency']);
        $currency = strtolower((string) $order['currency']);
        $existingId = (string) ($order['stripe_payment_intent_id'] ?? '');

        if ($existingId !== '') {
            $existing = $this->api('GET', '/v1/payment_intents/' . rawurlencode($existingId));
            if (($existing['ok'] ?? false) && isset($existing['data'])) {
                $intent = $existing['data'];
                $status = (string) ($intent['status'] ?? '');
                $sameAmount = (int) ($intent['amount'] ?? 0) === $amount;
                $sameCurrency = strtolower((string) ($intent['currency'] ?? '')) === $currency;
                if ($sameAmount && $sameCurrency && in_array($status, ['requires_payment_method', 'requires_confirmation', 'requires_action', 'processing'], true)) {
                    return [
                        'ok' => true,
                        'payment_intent_id' => (string) $intent['id'],
                        'client_secret' => (string) ($intent['client_secret'] ?? ''),
                        'status' => $status,
                    ];
                }
            }
        }

        $created = $this->api('POST', '/v1/payment_intents', [
            'amount' => $amount,
            'currency' => $currency,
            'automatic_payment_methods' => ['enabled' => 'true'],
            'metadata' => [
                'local_order_id' => (string) $order['id'],
                'whmcs_client_id' => (string) $order['whmcs_client_id'],
                'whmcs_order_id' => (string) ($order['whmcs_order_id'] ?? ''),
                'whmcs_invoice_id' => (string) $order['whmcs_invoice_id'],
            ],
        ], 'planetic-order-' . (int) $order['id'] . '-' . $amount . '-' . $currency);

        if (!($created['ok'] ?? false)) {
            return ['ok' => false, 'message' => $created['message'] ?? 'Stripe could not create the secure payment form.'];
        }

        $intent = $created['data'];
        return [
            'ok' => true,
            'payment_intent_id' => (string) $intent['id'],
            'client_secret' => (string) ($intent['client_secret'] ?? ''),
            'status' => (string) ($intent['status'] ?? ''),
        ];
    }

    public function verifyWebhook(string $payload, string $signatureHeader): array
    {
        $secret = trim((string) ($this->config['webhook_secret'] ?? env('STRIPE_WEBHOOK_SECRET', '')));
        if ($secret === '') {
            return ['ok' => false, 'message' => 'Stripe webhook secret is not configured.'];
        }

        $timestamp = '';
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamp = $value;
            }
            if ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === '' || !$signatures) {
            return ['ok' => false, 'message' => 'Stripe webhook signature is invalid.'];
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return ['ok' => false, 'message' => 'Stripe webhook signature is too old.'];
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        $valid = false;
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            return ['ok' => false, 'message' => 'Stripe webhook signature verification failed.'];
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return ['ok' => false, 'message' => 'Stripe webhook payload was invalid.'];
        }

        return ['ok' => true, 'event' => $event];
    }

    public function amountToMinorUnits(float $amount, string $currency): int
    {
        $zeroDecimal = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];
        $currency = strtolower($currency);
        return in_array($currency, $zeroDecimal, true)
            ? (int) round($amount)
            : (int) round($amount * 100);
    }

    public function minorUnitsToAmount(int $amount, string $currency): float
    {
        $zeroDecimal = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];
        return in_array(strtolower($currency), $zeroDecimal, true) ? (float) $amount : round($amount / 100, 2);
    }

    private function api(string $method, string $path, array $params = [], string $idempotencyKey = ''): array
    {
        $url = 'https://api.stripe.com' . $path;
        $headers = [
            'Authorization: Bearer ' . $this->secretKey(),
            'Content-Type: application/x-www-form-urlencoded',
        ];
        if ($idempotencyKey !== '') {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        $payload = http_build_query($params);
        $body = false;
        $status = 0;
        $diagnostic = '';

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
            ]);
            if ($method !== 'GET') {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
            }
            $body = curl_exec($curl);
            $diagnostic = curl_error($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => $method,
                    'header' => implode("\r\n", $headers) . "\r\n",
                    'content' => $method === 'GET' ? '' : $payload,
                    'timeout' => 30,
                    'ignore_errors' => true,
                ],
            ]);
            $body = @file_get_contents($url, false, $context);
            $status = $this->streamHttpStatus($http_response_header ?? []);
            $diagnostic = (string) (error_get_last()['message'] ?? '');
        }

        if ($body === false || $body === '') {
            $this->log('Stripe API did not respond.', ['path' => $path, 'http_status' => $status, 'diagnostic' => $diagnostic]);
            return ['ok' => false, 'message' => 'Stripe did not respond. Please try again shortly.'];
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            $this->log('Stripe returned invalid JSON.', ['path' => $path, 'http_status' => $status]);
            return ['ok' => false, 'message' => 'Stripe returned an invalid response.'];
        }

        if ($status < 200 || $status >= 300 || isset($decoded['error'])) {
            $this->log('Stripe API error.', [
                'path' => $path,
                'http_status' => $status,
                'type' => $decoded['error']['type'] ?? '',
                'code' => $decoded['error']['code'] ?? '',
            ]);
            return ['ok' => false, 'message' => 'Stripe could not prepare this payment. Please try again.'];
        }

        return ['ok' => true, 'data' => $decoded];
    }

    private function secretKey(): string
    {
        return trim((string) ($this->config['secret_key'] ?? env('STRIPE_SECRET_KEY', '')));
    }

    private function streamHttpStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $header, $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    private function log(string $message, array $context = []): void
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        unset($context['secret_key'], $context['webhook_secret'], $context['client_secret']);
        @file_put_contents($dir . '/stripe.log', '[' . date('c') . '] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
    }
}
