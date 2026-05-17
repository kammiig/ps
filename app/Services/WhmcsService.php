<?php

declare(strict_types=1);

namespace App\Services;

final class WhmcsService
{
    private array $config;

    public function __construct(private array $settings)
    {
        $this->config = is_file(APP_PATH . '/config/whmcs.php') ? require APP_PATH . '/config/whmcs.php' : [];
    }

    public function clientAreaUrl(): string
    {
        $configured = trim((string) ($this->settings['whmcs_client_area_url'] ?? ''));
        $base = $configured !== ''
            ? $configured
            : ($this->config['client_area_url'] ?? env('WHMCS_URL', env('WHMCS_CLIENT_AREA_URL', 'https://planeticsolution.com/clientarea/')));

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

    public function checkoutConfig(): array
    {
        return $this->config;
    }

    public function products(array $pids = []): array
    {
        $params = [
            'action' => 'GetProducts',
            'responsetype' => 'json',
        ];

        if ($pids) {
            $params['pid'] = implode(',', array_map('intval', $pids));
        }

        $decoded = $this->callApi($params);
        if (($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => $decoded['message'] ?? 'Unable to fetch WHMCS products.'];
        }

        return [
            'ok' => true,
            'products' => $decoded['products']['product'] ?? [],
        ];
    }

    public function findClientByEmail(string $email): array
    {
        $decoded = $this->callApi([
            'action' => 'GetClientsDetails',
            'email' => $email,
            'stats' => false,
            'responsetype' => 'json',
        ], false);

        if (($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'found' => false, 'message' => $decoded['message'] ?? 'Client not found.'];
        }

        $client = $decoded['client'] ?? $decoded;
        $clientId = (int) ($client['client_id'] ?? $client['userid'] ?? $client['id'] ?? 0);

        return [
            'ok' => $clientId > 0,
            'found' => $clientId > 0,
            'client_id' => $clientId,
            'client' => $client,
        ];
    }

    public function addClient(array $client): array
    {
        $decoded = $this->callApi([
            'action' => 'AddClient',
            'firstname' => $client['firstname'],
            'lastname' => $client['lastname'],
            'email' => $client['email'],
            'address1' => $client['address1'],
            'city' => $client['city'],
            'state' => $client['state'],
            'postcode' => $client['postcode'],
            'country' => $client['country'],
            'phonenumber' => $client['phonenumber'],
            'password2' => $client['password2'],
            'clientip' => $client['clientip'] ?? '',
            'responsetype' => 'json',
        ]);

        if (($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => $decoded['message'] ?? 'Unable to create WHMCS client.'];
        }

        return [
            'ok' => true,
            'client_id' => (int) ($decoded['clientid'] ?? $decoded['client_id'] ?? 0),
            'raw' => $decoded,
        ];
    }

    public function createOrFindClient(array $client): array
    {
        $existing = $this->findClientByEmail($client['email']);
        if (!empty($existing['found'])) {
            return [
                'ok' => true,
                'client_id' => (int) $existing['client_id'],
                'created' => false,
            ];
        }

        $created = $this->addClient($client);
        if (!$created['ok']) {
            return $created;
        }

        return [
            'ok' => true,
            'client_id' => (int) $created['client_id'],
            'created' => true,
        ];
    }

    public function addOrder(array $order): array
    {
        $decoded = $this->callApi(array_merge([
            'action' => 'AddOrder',
            'responsetype' => 'json',
            'paymentmethod' => $this->paymentMethod(),
            'clientip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ], $order));

        if (($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => $decoded['message'] ?? 'Unable to create WHMCS order.'];
        }

        return [
            'ok' => true,
            'order_id' => (int) ($decoded['orderid'] ?? 0),
            'invoice_id' => (int) ($decoded['invoiceid'] ?? 0),
            'service_ids' => (string) ($decoded['serviceids'] ?? ''),
            'domain_ids' => (string) ($decoded['domainids'] ?? ''),
            'raw' => $decoded,
        ];
    }

    public function acceptOrder(int $orderId): array
    {
        $decoded = $this->callApi([
            'action' => 'AcceptOrder',
            'orderid' => $orderId,
            'autosetup' => true,
            'sendemail' => true,
            'responsetype' => 'json',
        ]);

        if (($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => $decoded['message'] ?? 'Unable to accept WHMCS order.'];
        }

        return ['ok' => true];
    }

    public function createSsoToken(int $clientId, string $destination = 'clientarea:invoices'): array
    {
        $decoded = $this->callApi([
            'action' => 'CreateSsoToken',
            'client_id' => $clientId,
            'destination' => $destination,
            'responsetype' => 'json',
        ]);

        if (($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => $decoded['message'] ?? 'Unable to create WHMCS SSO token.'];
        }

        return [
            'ok' => true,
            'redirect_url' => (string) ($decoded['redirect_url'] ?? ''),
        ];
    }

    public function invoiceUrl(int $invoiceId): string
    {
        return $this->clientAreaUrl() . 'viewinvoice.php?id=' . $invoiceId;
    }

    public function paymentMethod(): string
    {
        return (string) ($this->settings['whmcs_payment_method'] ?? $this->config['payment_method'] ?? env('WHMCS_PAYMENT_METHOD', 'stripe'));
    }

    private function callApi(array $params, bool $logErrors = true): array
    {
        $apiUrl = $this->apiUrl();
        $identifier = trim((string) ($this->settings['whmcs_api_identifier'] ?? ''));
        $secret = trim((string) ($this->settings['whmcs_api_secret'] ?? ''));
        $identifier = $identifier !== '' ? $identifier : (string) ($this->config['api_identifier'] ?? env('WHMCS_API_IDENTIFIER', ''));
        $secret = $secret !== '' ? $secret : (string) ($this->config['api_secret'] ?? env('WHMCS_API_SECRET', ''));

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
            $this->logError('WHMCS API did not respond.', ['action' => $params['action'] ?? 'unknown']);
            return ['result' => 'error', 'message' => 'WHMCS API did not respond.'];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            $this->logError('WHMCS returned invalid JSON.', ['action' => $params['action'] ?? 'unknown']);
            return ['result' => 'error', 'message' => 'WHMCS returned invalid JSON.'];
        }

        if ($logErrors && ($decoded['result'] ?? '') !== 'success') {
            $this->logError('WHMCS API error.', [
                'action' => $params['action'] ?? 'unknown',
                'message' => $decoded['message'] ?? 'Unknown WHMCS error.',
            ]);
        }

        return $decoded;
    }

    private function apiUrl(): string
    {
        $configured = trim((string) ($this->settings['whmcs_api_url'] ?? ''));
        $configured = $configured !== '' ? $configured : (string) ($this->config['api_url'] ?? env('WHMCS_API_URL', ''));
        if ($configured) {
            return (string) $configured;
        }

        $base = env('WHMCS_URL', '');
        return $base ? rtrim((string) $base, '/') . '/includes/api.php' : '';
    }

    private function logError(string $message, array $context = []): void
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        unset($context['identifier'], $context['secret'], $context['password'], $context['password2']);
        $line = '[' . date('c') . '] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        @file_put_contents($dir . '/whmcs-api.log', $line, FILE_APPEND);
    }
}
