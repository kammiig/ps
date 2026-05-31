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
            $message = (string) ($decoded['message'] ?? 'Client not found.');
            return [
                'ok' => false,
                'found' => false,
                'not_found' => str_contains(strtolower($message), 'not found'),
                'message' => $message,
            ];
        }

        $client = $decoded['client'] ?? $decoded;
        $clientId = (int) ($client['client_id'] ?? $client['userid'] ?? $client['id'] ?? 0);
        if ($clientId <= 0) {
            return [
                'ok' => false,
                'found' => false,
                'not_found' => true,
                'message' => 'Client not found.',
            ];
        }

        return [
            'ok' => true,
            'found' => true,
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
            'country-calling-code' => $this->countryCallingCode((string) ($client['country'] ?? '')),
            'password2' => $client['password2'],
            'skipvalidation' => true,
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

        if (!$existing['ok'] && empty($existing['not_found'])) {
            return [
                'ok' => false,
                'message' => $existing['message'] ?? 'Unable to check existing WHMCS client.',
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

    public function invoice(int $invoiceId): array
    {
        $decoded = $this->callApi([
            'action' => 'GetInvoice',
            'invoiceid' => $invoiceId,
            'responsetype' => 'json',
        ]);

        if (($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => $decoded['message'] ?? 'Unable to load invoice.'];
        }

        return ['ok' => true, 'invoice' => $decoded];
    }

    public function invoiceForClient(int $invoiceId, int $clientId, int $orderId = 0): array
    {
        $lastMessage = 'Unable to load invoice.';

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $invoice = $this->invoice($invoiceId);
            if ($invoice['ok']) {
                return $invoice;
            }
            $lastMessage = $invoice['message'] ?? $lastMessage;

            $invoices = $this->invoicesForClient($clientId);
            if ($invoices['ok']) {
                foreach ($invoices['invoices'] as $clientInvoice) {
                    $candidateId = (int) ($clientInvoice['id'] ?? $clientInvoice['invoiceid'] ?? 0);
                    if ($candidateId === $invoiceId) {
                        $clientInvoice['invoiceid'] = $clientInvoice['invoiceid'] ?? $candidateId;
                        $clientInvoice['userid'] = $clientInvoice['userid'] ?? $clientId;
                        return ['ok' => true, 'invoice' => $clientInvoice, 'fallback' => true];
                    }
                }
            } else {
                $lastMessage = $invoices['message'] ?? $lastMessage;
            }

            $bridgeInvoice = $this->bridgeInvoiceFallback($invoiceId, $clientId, $orderId);
            if ($bridgeInvoice['ok']) {
                return $bridgeInvoice;
            }
            $lastMessage = $bridgeInvoice['message'] ?? $lastMessage;

            if ($orderId > 0) {
                $order = $this->orderInvoiceFallback($orderId, $invoiceId, $clientId);
                if ($order['ok']) {
                    return $order;
                }
                $lastMessage = $order['message'] ?? $lastMessage;
            }

            usleep(350000);
        }

        $this->logError('WHMCS invoice could not be loaded after order creation.', [
            'invoice_id' => $invoiceId,
            'client_id' => $clientId,
            'message' => $lastMessage,
        ]);

        return [
            'ok' => false,
            'message' => $lastMessage,
        ];
    }

    public function invoiceForOrder(int $orderId, int $clientId): array
    {
        if ($orderId <= 0 || $clientId <= 0) {
            return ['ok' => false, 'message' => 'Order and client references are required.'];
        }

        $lastMessage = 'Unable to load invoice for this order.';

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $bridgeInvoice = $this->bridgeOrderInvoiceFallback($orderId, $clientId);
            if ($bridgeInvoice['ok']) {
                return $bridgeInvoice;
            }
            $lastMessage = $bridgeInvoice['message'] ?? $lastMessage;

            $orders = $this->order($orderId);
            if ($orders['ok']) {
                foreach ($orders['orders'] as $order) {
                    $orderClientId = (int) ($order['userid'] ?? $order['clientid'] ?? $order['client_id'] ?? 0);
                    if ($orderClientId > 0 && $orderClientId !== $clientId) {
                        continue;
                    }

                    $invoiceId = (int) ($order['invoiceid'] ?? $order['invoice_id'] ?? 0);
                    if ($invoiceId <= 0) {
                        continue;
                    }

                    $invoice = $this->invoiceForClient($invoiceId, $clientId, $orderId);
                    if ($invoice['ok']) {
                        return $invoice;
                    }

                    $lastMessage = $invoice['message'] ?? $lastMessage;
                }
            } else {
                $lastMessage = $orders['message'] ?? $lastMessage;
            }

            usleep(350000);
        }

        $this->logError('WHMCS invoice could not be resolved from order.', [
            'order_id' => $orderId,
            'client_id' => $clientId,
            'message' => $lastMessage,
        ]);

        return [
            'ok' => false,
            'message' => $lastMessage,
        ];
    }

    private function bridgeInvoiceFallback(int $invoiceId, int $clientId, int $orderId = 0): array
    {
        if (!$this->hasBridge()) {
            return ['ok' => false, 'message' => 'The WHMCS bridge is not configured.'];
        }

        $decoded = $this->callApi([
            'action' => 'PlaneticGetInvoice',
            'invoiceid' => $invoiceId,
            'clientid' => $clientId,
            'orderid' => $orderId,
            'responsetype' => 'json',
        ]);

        if (($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => $decoded['message'] ?? 'Unable to load invoice through the WHMCS bridge.'];
        }

        return [
            'ok' => true,
            'fallback' => 'bridge_invoice',
            'invoice' => $decoded['invoice'] ?? [],
        ];
    }

    private function bridgeOrderInvoiceFallback(int $orderId, int $clientId): array
    {
        if (!$this->hasBridge()) {
            return ['ok' => false, 'message' => 'The WHMCS bridge is not configured.'];
        }

        $decoded = $this->callApi([
            'action' => 'PlaneticGetOrderInvoice',
            'orderid' => $orderId,
            'clientid' => $clientId,
            'responsetype' => 'json',
        ]);

        if (($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => $decoded['message'] ?? 'Unable to load order invoice through the WHMCS bridge.'];
        }

        return [
            'ok' => true,
            'fallback' => 'bridge_order_invoice',
            'invoice' => $decoded['invoice'] ?? [],
        ];
    }

    public function invoicesForClient(int $clientId): array
    {
        $decoded = $this->callApi([
            'action' => 'GetInvoices',
            'userid' => $clientId,
            'responsetype' => 'json',
        ]);

        if (($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => $decoded['message'] ?? 'Unable to load invoices.'];
        }

        return [
            'ok' => true,
            'invoices' => $this->normaliseApiList($decoded['invoices']['invoice'] ?? []),
        ];
    }

    public function order(int $orderId): array
    {
        $decoded = $this->callApi([
            'action' => 'GetOrders',
            'id' => $orderId,
            'orderid' => $orderId,
            'responsetype' => 'json',
        ]);

        if (($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => $decoded['message'] ?? 'Unable to load order.'];
        }

        return [
            'ok' => true,
            'orders' => $this->normaliseApiList($decoded['orders']['order'] ?? []),
        ];
    }

    private function orderInvoiceFallback(int $orderId, int $invoiceId, int $clientId): array
    {
        $orders = $this->order($orderId);
        if (!$orders['ok']) {
            return $orders;
        }

        foreach ($orders['orders'] as $order) {
            $orderInvoiceId = (int) ($order['invoiceid'] ?? $order['invoice_id'] ?? 0);
            $orderClientId = (int) ($order['userid'] ?? $order['clientid'] ?? $order['client_id'] ?? 0);

            if ($orderInvoiceId !== $invoiceId) {
                continue;
            }

            if ($orderClientId > 0 && $orderClientId !== $clientId) {
                continue;
            }

            $amount = 0.0;
            foreach (['amount', 'total', 'subtotal'] as $key) {
                if (isset($order[$key]) && is_numeric($order[$key]) && (float) $order[$key] > 0) {
                    $amount = round((float) $order[$key], 2);
                    break;
                }
            }

            if ($amount <= 0) {
                return ['ok' => false, 'message' => 'The order was found, but no payable amount was returned.'];
            }

            return [
                'ok' => true,
                'fallback' => 'order',
                'invoice' => [
                    'invoiceid' => $invoiceId,
                    'id' => $invoiceId,
                    'userid' => $clientId,
                    'clientid' => $clientId,
                    'orderid' => $orderId,
                    'total' => number_format($amount, 2, '.', ''),
                    'balance' => number_format($amount, 2, '.', ''),
                    'status' => $order['paymentstatus'] ?? $order['status'] ?? 'Unpaid',
                    'currency' => $order['currency'] ?? [
                        'code' => $order['currencycode'] ?? 'GBP',
                        'prefix' => $order['currencyprefix'] ?? '',
                        'suffix' => $order['currencysuffix'] ?? '',
                    ],
                ],
            ];
        }

        return ['ok' => false, 'message' => 'Unable to load invoice amount from the WHMCS order.'];
    }

    public function productsForClient(int $clientId): array
    {
        $decoded = $this->callApi([
            'action' => 'GetClientsProducts',
            'clientid' => $clientId,
            'responsetype' => 'json',
        ]);

        if (($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => $decoded['message'] ?? 'Unable to load services.'];
        }

        return [
            'ok' => true,
            'products' => $this->normaliseApiList($decoded['products']['product'] ?? []),
        ];
    }

    public function domainsForClient(int $clientId): array
    {
        $decoded = $this->callApi([
            'action' => 'GetClientsDomains',
            'clientid' => $clientId,
            'responsetype' => 'json',
        ]);

        if (($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => $decoded['message'] ?? 'Unable to load domains.'];
        }

        return [
            'ok' => true,
            'domains' => $this->normaliseApiList($decoded['domains']['domain'] ?? []),
        ];
    }

    public function updateClient(int $clientId, array $client): array
    {
        $decoded = $this->callApi(array_merge([
            'action' => 'UpdateClient',
            'clientid' => $clientId,
            'responsetype' => 'json',
        ], $client));

        if (($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => $decoded['message'] ?? 'Unable to update account details.'];
        }

        return ['ok' => true];
    }

    public function addInvoicePayment(int $invoiceId, string $transactionId, float $amount, string $gateway): array
    {
        $decoded = $this->callApi([
            'action' => 'AddInvoicePayment',
            'invoiceid' => $invoiceId,
            'transid' => $transactionId,
            'amount' => number_format($amount, 2, '.', ''),
            'gateway' => $gateway,
            'responsetype' => 'json',
        ]);

        if (($decoded['result'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => $decoded['message'] ?? 'Unable to record invoice payment.'];
        }

        return ['ok' => true, 'raw' => $decoded];
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

    public function invoicePaymentGateway(): string
    {
        $configured = trim((string) ($this->settings['whmcs_payment_gateway_name'] ?? ''));
        return $configured !== '' ? $configured : trim((string) env('WHMCS_PAYMENT_GATEWAY_NAME', ''));
    }

    private function callApi(array $params, bool $logErrors = true): array
    {
        $bridgeUrl = $this->bridgeUrl();
        $bridgeToken = $this->bridgeToken();
        $useBridge = $bridgeUrl !== '' && $bridgeToken !== '';
        $apiUrl = $useBridge ? $bridgeUrl : $this->apiUrl();
        $identifier = trim((string) ($this->settings['whmcs_api_identifier'] ?? ''));
        $secret = trim((string) ($this->settings['whmcs_api_secret'] ?? ''));
        $accessKey = trim((string) ($this->settings['whmcs_api_access_key'] ?? ''));
        $identifier = $identifier !== '' ? $identifier : (string) ($this->config['api_identifier'] ?? env('WHMCS_API_IDENTIFIER', ''));
        $secret = $secret !== '' ? $secret : (string) ($this->config['api_secret'] ?? env('WHMCS_API_SECRET', ''));
        $accessKey = $accessKey !== '' ? $accessKey : (string) ($this->config['api_access_key'] ?? env('WHMCS_API_ACCESS_KEY', ''));

        if (!$apiUrl || (!$useBridge && (!$identifier || !$secret))) {
            return ['result' => 'error', 'message' => 'WHMCS API credentials are not configured.'];
        }

        $auth = [];
        if ($useBridge) {
            $auth['bridge_token'] = $bridgeToken;
        } else {
            $auth = [
                'identifier' => $identifier,
                'secret' => $secret,
            ];
            if ($accessKey !== '') {
                $auth['accesskey'] = $accessKey;
                $auth['access_key'] = $accessKey;
            }
        }

        $action = (string) ($params['action'] ?? 'unknown');
        $payload = http_build_query(array_merge($params, $auth));
        $transport = $this->postApiRequest($apiUrl, $payload, $action);

        if (!$transport['ok']) {
            $this->logError('WHMCS API did not respond.', [
                'action' => $action,
                'transport' => $transport['transport'] ?? 'unknown',
                'local_bridge' => $useBridge,
                'http_status' => $transport['http_status'] ?? 0,
                'diagnostic' => $transport['diagnostic'] ?? '',
            ]);

            return [
                'result' => 'error',
                'message' => 'WHMCS API did not respond. Check the API URL, credentials, WHMCS API IP access settings and cPanel outbound HTTPS/cURL support.',
            ];
        }

        $response = (string) ($transport['body'] ?? '');
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            $this->logError('WHMCS returned invalid JSON.', [
                'action' => $action,
                'transport' => $transport['transport'] ?? 'unknown',
                'local_bridge' => $useBridge,
                'http_status' => $transport['http_status'] ?? 0,
                'response_preview' => substr(strip_tags($response), 0, 220),
            ]);
            return ['result' => 'error', 'message' => 'WHMCS returned invalid JSON. Check the API URL and WHMCS error output.'];
        }

        if (($decoded['result'] ?? '') !== 'success') {
            $message = (string) ($decoded['message'] ?? 'Unknown WHMCS error.');
            $isExpectedNotFound = str_contains(strtolower($message), 'not found');
            if ($logErrors || !$isExpectedNotFound) {
                $this->logError('WHMCS API error.', [
                    'action' => $action,
                    'message' => $message,
                    'local_bridge' => $useBridge,
                    'access_key_configured' => $accessKey !== '',
                ]);
            }
            $decoded['message'] = $this->safeApiMessage($message, $accessKey !== '');
        }

        return $decoded;
    }

    private function safeApiMessage(string $message, bool $hasAccessKey): string
    {
        if (str_contains(strtolower($message), 'bridge action is not allowed')) {
            return 'The WHMCS local API bridge is outdated. Upload the latest planetic-local-api.php file to the WHMCS clientarea folder.';
        }

        if (str_contains(strtolower($message), 'invalid ip')) {
            if ($hasAccessKey) {
                return 'WHMCS is still rejecting the website server IP even though an API access key is configured. Check that the key in WHMCS configuration.php matches WHMCS_API_ACCESS_KEY exactly.';
            }

            return 'WHMCS API access is blocking this website server. Please contact support.';
        }

        return $message;
    }

    private function countryCallingCode(string $country): string
    {
        return [
            'GB' => '44',
            'US' => '1',
            'CA' => '1',
            'PK' => '92',
            'IE' => '353',
            'AU' => '61',
        ][strtoupper($country)] ?? '';
    }

    private function postApiRequest(string $apiUrl, string $payload, string $action): array
    {
        $verifySsl = $this->sslVerify();
        $connectTimeout = $this->apiConnectTimeout($action);
        $timeout = $this->apiTimeout($action);
        $curlFailure = [];
        if (function_exists('curl_init')) {
            $curl = curl_init($apiUrl);
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_USERAGENT => 'PlaneticSolutionsWebsite/1.0',
                CURLOPT_SSL_VERIFYPEER => $verifySsl,
                CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            ]);

            $body = curl_exec($curl);
            $error = curl_error($curl);
            $errno = curl_errno($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($body !== false && $body !== '') {
                return [
                    'ok' => true,
                    'body' => (string) $body,
                    'http_status' => $status,
                    'transport' => 'curl',
                ];
            }

            $curlFailure = [
                'action' => $action,
                'http_status' => $status,
                'curl_errno' => $errno,
                'curl_error' => $error,
            ];
            $this->logError('WHMCS API cURL transport failed.', $curlFailure);

            if (!$this->canRetryApiAction($action)) {
                return [
                    'ok' => false,
                    'transport' => 'curl',
                    'http_status' => $status,
                    'diagnostic' => $error,
                ];
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: PlaneticSolutionsWebsite/1.0\r\n",
                'content' => $payload,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => $verifySsl,
                'verify_peer_name' => $verifySsl,
            ],
        ]);

        $body = @file_get_contents($apiUrl, false, $context);
        if ($body !== false && $body !== '') {
            return [
                'ok' => true,
                'body' => (string) $body,
                'http_status' => $this->streamHttpStatus($http_response_header ?? []),
                'transport' => 'stream',
            ];
        }

        $error = error_get_last();
        return [
            'ok' => false,
            'transport' => function_exists('curl_init') ? 'curl+stream' : 'stream',
            'http_status' => $this->streamHttpStatus($http_response_header ?? []),
            'diagnostic' => is_array($error) ? (string) ($error['message'] ?? '') : (string) ($curlFailure['curl_error'] ?? ''),
        ];
    }

    private function apiConnectTimeout(string $action): int
    {
        return $this->isAccountReadAction($action) ? 4 : 10;
    }

    private function apiTimeout(string $action): int
    {
        return $this->isAccountReadAction($action) ? 8 : 20;
    }

    private function isAccountReadAction(string $action): bool
    {
        return in_array($action, ['GetClientsProducts', 'GetClientsDomains', 'GetInvoices'], true);
    }

    private function canRetryApiAction(string $action): bool
    {
        return in_array($action, ['DomainWhois', 'GetTLDPricing', 'GetProducts', 'GetClientsDetails', 'GetInvoice', 'GetInvoices', 'GetOrders', 'PlaneticGetInvoice', 'PlaneticGetOrderInvoice', 'GetClientsProducts', 'GetClientsDomains'], true);
    }

    private function normaliseApiList(mixed $items): array
    {
        if (!is_array($items) || $items === []) {
            return [];
        }

        if (array_keys($items) === range(0, count($items) - 1)) {
            return $items;
        }

        return [$items];
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

    private function sslVerify(): bool
    {
        $value = $this->config['api_ssl_verify'] ?? env('WHMCS_API_SSL_VERIFY', true);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
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

    private function bridgeUrl(): string
    {
        $configured = trim((string) ($this->settings['whmcs_local_api_bridge_url'] ?? ''));
        return $configured !== '' ? $configured : trim((string) ($this->config['local_bridge_url'] ?? env('WHMCS_LOCAL_API_BRIDGE_URL', '')));
    }

    private function bridgeToken(): string
    {
        $configured = trim((string) ($this->settings['whmcs_local_api_bridge_token'] ?? ''));
        return $configured !== '' ? $configured : trim((string) ($this->config['local_bridge_token'] ?? env('WHMCS_LOCAL_API_BRIDGE_TOKEN', '')));
    }

    private function hasBridge(): bool
    {
        return $this->bridgeUrl() !== '' && $this->bridgeToken() !== '';
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
