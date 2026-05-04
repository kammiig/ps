<?php

declare(strict_types=1);

namespace App\Services;

final class WhmcsService
{
    public function __construct(private array $settings)
    {
    }

    public function clientAreaUrl(): string
    {
        return rtrim((string) ($this->settings['whmcs_client_area_url'] ?? env('WHMCS_CLIENT_AREA_URL', 'https://planeticsolution.com/clientarea/')), '/') . '/';
    }

    public function cartUrl(?string $path = null): string
    {
        $base = $this->clientAreaUrl();
        return $path ? $base . ltrim($path, '/') : $base . 'cart.php';
    }

    public function domainSearchUrl(string $domain = ''): string
    {
        $query = $domain !== '' ? '&query=' . rawurlencode($domain) : '';
        return $this->cartUrl('cart.php?a=add&domain=register' . $query);
    }

    public function domainTransferUrl(string $domain = ''): string
    {
        $query = $domain !== '' ? '&query=' . rawurlencode($domain) : '';
        return $this->cartUrl('cart.php?a=add&domain=transfer' . $query);
    }

    public function checkDomain(string $domain): array
    {
        $apiUrl = $this->settings['whmcs_api_url'] ?? env('WHMCS_API_URL', '');
        $identifier = $this->settings['whmcs_api_identifier'] ?? env('WHMCS_API_IDENTIFIER', '');
        $secret = $this->settings['whmcs_api_secret'] ?? env('WHMCS_API_SECRET', '');

        if (!$apiUrl || !$identifier || !$secret) {
            return ['ok' => false, 'message' => 'WHMCS API credentials are not configured.'];
        }

        $payload = http_build_query([
            'identifier' => $identifier,
            'secret' => $secret,
            'action' => 'DomainWhois',
            'domain' => $domain,
            'responsetype' => 'json',
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 8,
            ],
        ]);

        $response = @file_get_contents($apiUrl, false, $context);
        if (!$response) {
            return ['ok' => false, 'message' => 'WHMCS API did not respond.'];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || ($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => $decoded['message'] ?? 'WHMCS returned an unavailable response.'];
        }

        $status = strtolower((string) ($decoded['status'] ?? ''));
        $available = in_array($status, ['available', 'free'], true)
            || (str_contains($status, 'available') && !str_contains($status, 'unavailable') && !str_contains($status, 'not available'));

        return [
            'ok' => true,
            'available' => $available,
            'status' => $decoded['status'] ?? 'unknown',
            'message' => $decoded['whois'] ?? null,
        ];
    }
}
