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
        $configured = trim((string) ($this->settings['whmcs_client_area_url'] ?? ''));
        $base = $configured !== ''
            ? $configured
            : env('WHMCS_URL', env('WHMCS_CLIENT_AREA_URL', 'https://planeticsolution.com/clientarea/'));

        return rtrim((string) $base, '/') . '/';
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
        $decoded = $this->callApi([
            'action' => 'DomainWhois',
            'domain' => $domain,
            'responsetype' => 'json',
        ]);
        if (($decoded['result'] ?? '') !== 'success') {
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

    public function tldPricing(int $currencyId = 1): array
    {
        $decoded = $this->callApi([
            'action' => 'GetTLDPricing',
            'currencyid' => $currencyId,
            'responsetype' => 'json',
        ]);

        if (($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => $decoded['message'] ?? 'Unable to fetch WHMCS TLD pricing.'];
        }

        return [
            'ok' => true,
            'pricing' => $decoded['pricing'] ?? [],
            'currency' => $decoded['currency'] ?? [],
        ];
    }

    public function priceForTld(array $pricing, string $tld): ?string
    {
        $keys = [$tld, ltrim($tld, '.')];
        foreach ($keys as $key) {
            if (!isset($pricing[$key]) || !is_array($pricing[$key])) {
                continue;
            }

            $register = $pricing[$key]['register'] ?? null;
            if (!is_array($register) || $register === []) {
                continue;
            }

            $oneYear = $register['1'] ?? reset($register);
            if (is_array($oneYear)) {
                $oneYear = $oneYear['price'] ?? $oneYear['msetupfee'] ?? reset($oneYear);
            }

            if ($oneYear !== null && $oneYear !== '') {
                return number_format((float) $oneYear, 2, '.', '');
            }
        }

        return null;
    }

    private function callApi(array $params): array
    {
        $apiUrl = $this->apiUrl();
        $identifier = trim((string) ($this->settings['whmcs_api_identifier'] ?? ''));
        $secret = trim((string) ($this->settings['whmcs_api_secret'] ?? ''));
        $identifier = $identifier !== '' ? $identifier : env('WHMCS_API_IDENTIFIER', '');
        $secret = $secret !== '' ? $secret : env('WHMCS_API_SECRET', '');

        if (!$apiUrl || !$identifier || !$secret) {
            return ['result' => 'error', 'message' => 'WHMCS API credentials are not configured.'];
        }

        $payload = http_build_query(array_merge($params, [
            'identifier' => $identifier,
            'secret' => $secret,
        ]));

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 12,
            ],
        ]);

        $response = @file_get_contents($apiUrl, false, $context);
        if (!$response) {
            return ['result' => 'error', 'message' => 'WHMCS API did not respond.'];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['result' => 'error', 'message' => 'WHMCS returned invalid JSON.'];
        }

        return $decoded;
    }

    private function apiUrl(): string
    {
        $configured = trim((string) ($this->settings['whmcs_api_url'] ?? ''));
        $configured = $configured !== '' ? $configured : env('WHMCS_API_URL', '');
        if ($configured) {
            return (string) $configured;
        }

        $base = env('WHMCS_URL', '');
        return $base ? rtrim((string) $base, '/') . '/includes/api.php' : '';
    }
}
