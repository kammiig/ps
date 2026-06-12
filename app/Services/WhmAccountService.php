<?php

declare(strict_types=1);

namespace App\Services;

final class WhmAccountService
{
    public function __construct(private array $settings = [])
    {
    }

    public function configured(): bool
    {
        return $this->hostname() !== '' && $this->username() !== '' && $this->apiToken() !== '';
    }

    public function serverIp(): string
    {
        return trim((string) (
            $this->settings['default_hosting_server_ip']
            ?? env('DEFAULT_HOSTING_SERVER_IP', env('WHM_SERVER_IP', env('HOSTING_SERVER_IP', '')))
        ));
    }

    public function cpanelUrl(string $domain = ''): string
    {
        $configured = trim((string) ($this->settings['cpanel_login_url'] ?? env('CPANEL_LOGIN_URL', '')));
        if ($configured !== '') {
            return $configured;
        }

        $host = trim((string) env('CPANEL_HOSTNAME', ''));
        if ($host === '') {
            $host = $this->hostname();
        }

        return $host !== '' ? 'https://' . preg_replace('#^https?://#', '', $host) . ':2083' : '';
    }

    public function testConnection(): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'message' => 'WHM API credentials are not configured.'];
        }

        $result = $this->listPackages();
        if (empty($result['ok'])) {
            return ['ok' => false, 'message' => (string) ($result['message'] ?? 'WHM API connection failed.')];
        }

        return ['ok' => true, 'message' => 'WHM API listpkgs succeeded with ' . count($result['packages']) . ' package(s).'];
    }

    public function listPackages(): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'message' => 'WHM API credentials are not configured.', 'packages' => []];
        }

        $result = $this->api('listpkgs');
        if (empty($result['ok'])) {
            return ['ok' => false, 'message' => (string) ($result['message'] ?? 'WHM package list could not be loaded.'), 'packages' => []];
        }

        $packages = $result['data']['pkg'] ?? [];
        if (is_array($packages) && isset($packages['name'])) {
            $packages = [$packages];
        }

        $names = [];
        foreach (is_array($packages) ? $packages : [] as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return ['ok' => true, 'message' => 'WHM package list loaded.', 'packages' => array_values(array_unique($names))];
    }

    public function packageExists(string $package): bool
    {
        $package = trim($package);
        if ($package === '' || !$this->configured()) {
            return false;
        }

        $result = $this->listPackages();
        if (empty($result['ok'])) {
            return false;
        }

        return in_array($package, $result['packages'] ?? [], true);
    }

    public function createOrFindAccount(string $domain, string $package, string $contactEmail = '', string $preferredUsername = ''): array
    {
        $domain = $this->normaliseDomain($domain);
        $package = trim($package);
        if ($domain === '') {
            return ['ok' => false, 'message' => 'A valid domain is required for cPanel account creation.'];
        }
        if (!$this->packageAllowed($package)) {
            return ['ok' => false, 'message' => 'The requested WHM package is not allowed for automatic provisioning.'];
        }
        if (!$this->configured()) {
            return ['ok' => false, 'message' => 'WHM API credentials are not configured.'];
        }

        $existing = $this->accountSummary($domain);
        if (!empty($existing['ok']) && !empty($existing['found'])) {
            return [
                'ok' => true,
                'existing' => true,
                'domain' => $domain,
                'username' => (string) ($existing['username'] ?? ''),
                'server_ip' => (string) ($existing['server_ip'] ?? $this->serverIp()),
                'package' => (string) ($existing['package'] ?? $package),
                'cpanel_url' => $this->cpanelUrl($domain),
                'password' => '',
                'encrypted_password' => '',
            ];
        }

        $password = $this->generatePassword();
        $baseUsername = $preferredUsername !== '' ? $preferredUsername : $this->usernameFromDomain($domain);
        $lastMessage = 'Unable to create the cPanel account.';

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $username = $this->candidateUsername($baseUsername, $attempt);
            $created = $this->api('createacct', [
                'username' => $username,
                'domain' => $domain,
                'plan' => $package,
                'password' => $password,
                'contactemail' => $contactEmail,
                'cgi' => 1,
                'useregns' => 0,
            ], 'POST');

            if (!empty($created['ok'])) {
                $data = is_array($created['data'] ?? null) ? $created['data'] : [];
                $account = is_array($data['acct'] ?? null) ? $data['acct'] : [];
                return [
                    'ok' => true,
                    'existing' => false,
                    'domain' => $domain,
                    'username' => (string) ($data['username'] ?? $account['user'] ?? $username),
                    'server_ip' => (string) ($data['ip'] ?? $account['ip'] ?? $this->serverIp()),
                    'package' => $package,
                    'cpanel_url' => $this->cpanelUrl($domain),
                    'password' => $password,
                    'encrypted_password' => $this->encryptPassword($password),
                    'raw' => $created['raw'] ?? [],
                ];
            }

            $lastMessage = (string) ($created['message'] ?? $lastMessage);
            if ($this->messageLooksLikeExistingAccount($lastMessage)) {
                $existing = $this->accountSummary($domain);
                if (!empty($existing['ok']) && !empty($existing['found'])) {
                    return [
                        'ok' => true,
                        'existing' => true,
                        'domain' => $domain,
                        'username' => (string) ($existing['username'] ?? ''),
                        'server_ip' => (string) ($existing['server_ip'] ?? $this->serverIp()),
                        'package' => (string) ($existing['package'] ?? $package),
                        'cpanel_url' => $this->cpanelUrl($domain),
                        'password' => '',
                        'encrypted_password' => '',
                    ];
                }
            }
            if (!str_contains(strtolower($lastMessage), 'user') && !str_contains(strtolower($lastMessage), 'username')) {
                break;
            }
        }

        return ['ok' => false, 'message' => $lastMessage];
    }

    public function accountSummary(string $domain): array
    {
        $domain = $this->normaliseDomain($domain);
        if ($domain === '') {
            return ['ok' => false, 'found' => false, 'message' => 'A valid domain is required.'];
        }
        if (!$this->configured()) {
            return ['ok' => false, 'found' => false, 'message' => 'WHM API credentials are not configured.'];
        }

        $summary = $this->api('accountsummary', ['domain' => $domain]);
        if (empty($summary['ok'])) {
            $message = strtolower((string) ($summary['message'] ?? ''));
            if (str_contains($message, 'not found') || str_contains($message, 'no account')) {
                return $this->accountSummaryFromList($domain);
            }

            $fallback = $this->accountSummaryFromList($domain);
            if (!empty($fallback['ok']) && !empty($fallback['found'])) {
                return $fallback;
            }

            return ['ok' => false, 'found' => false, 'message' => $summary['message'] ?? 'Unable to load WHM account summary.'];
        }

        $data = is_array($summary['data'] ?? null) ? $summary['data'] : [];
        $accounts = $data['acct'] ?? [];
        if (is_array($accounts) && isset($accounts['user'])) {
            $accounts = [$accounts];
        }
        if (!is_array($accounts) || $accounts === []) {
            return ['ok' => true, 'found' => false];
        }

        $account = (array) $accounts[0];
        return [
            'ok' => true,
            'found' => true,
            'username' => (string) ($account['user'] ?? $account['username'] ?? ''),
            'domain' => (string) ($account['domain'] ?? $domain),
            'server_ip' => (string) ($account['ip'] ?? $account['ipv4'] ?? $this->serverIp()),
            'package' => (string) ($account['plan'] ?? $account['package'] ?? ''),
            'raw' => $account,
        ];
    }

    private function accountSummaryFromList(string $domain): array
    {
        $result = $this->api('listaccts', [
            'searchtype' => 'domain',
            'search' => $domain,
        ]);
        if (empty($result['ok'])) {
            return ['ok' => true, 'found' => false];
        }

        $accounts = $result['data']['acct'] ?? [];
        if (is_array($accounts) && isset($accounts['user'])) {
            $accounts = [$accounts];
        }

        foreach (is_array($accounts) ? $accounts : [] as $account) {
            $account = (array) $account;
            if (strtolower((string) ($account['domain'] ?? '')) !== $domain) {
                continue;
            }

            return [
                'ok' => true,
                'found' => true,
                'username' => (string) ($account['user'] ?? $account['username'] ?? ''),
                'domain' => (string) ($account['domain'] ?? $domain),
                'server_ip' => (string) ($account['ip'] ?? $account['ipv4'] ?? $this->serverIp()),
                'package' => (string) ($account['plan'] ?? $account['package'] ?? ''),
                'raw' => $account,
            ];
        }

        return ['ok' => true, 'found' => false];
    }

    private function messageLooksLikeExistingAccount(string $message): bool
    {
        $message = strtolower($message);
        foreach (['already exists', 'dns entry', 'owned by another user', 'domain exists', 'user exists'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function api(string $function, array $params = [], string $method = 'GET'): array
    {
        $url = $this->apiBaseUrl() . '/json-api/' . rawurlencode($function);
        $params = array_filter(array_merge(['api.version' => 1], $params), static fn (mixed $value): bool => $value !== null && $value !== '');
        $query = http_build_query($params);
        $headers = [
            'Authorization: whm ' . $this->username() . ':' . $this->apiToken(),
            'Content-Type: application/x-www-form-urlencoded',
        ];
        $body = '';
        $status = 0;
        $diagnostic = '';

        if ($method === 'GET') {
            $url .= '?' . $query;
        }

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 35,
                CURLOPT_SSL_VERIFYPEER => $this->sslVerify(),
                CURLOPT_SSL_VERIFYHOST => $this->sslVerify() ? 2 : 0,
            ]);
            if ($method !== 'GET') {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
            }
            $body = (string) curl_exec($curl);
            $diagnostic = curl_error($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => $method,
                    'header' => implode("\r\n", $headers) . "\r\n",
                    'content' => $method === 'GET' ? '' : $query,
                    'timeout' => 35,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => $this->sslVerify(),
                    'verify_peer_name' => $this->sslVerify(),
                ],
            ]);
            $body = (string) @file_get_contents($url, false, $context);
            $status = $this->streamHttpStatus($http_response_header ?? []);
            $diagnostic = (string) (error_get_last()['message'] ?? '');
        }

        $decoded = json_decode($body, true);
        $metadata = is_array($decoded['metadata'] ?? null) ? $decoded['metadata'] : [];
        $ok = $status >= 200 && $status < 300 && is_array($decoded) && (int) ($metadata['result'] ?? 0) === 1;
        if (!$ok) {
            $message = (string) ($metadata['reason'] ?? $decoded['cpanelresult']['error'] ?? 'WHM API request failed.');
            $this->log('WHM API request failed.', [
                'function' => $function,
                'http_status' => $status,
                'message' => $message,
                'diagnostic' => $diagnostic,
            ]);

            return ['ok' => false, 'message' => $message, 'raw' => is_array($decoded) ? $decoded : []];
        }

        return [
            'ok' => true,
            'data' => is_array($decoded['data'] ?? null) ? $decoded['data'] : [],
            'raw' => $decoded,
        ];
    }

    private function apiBaseUrl(): string
    {
        $host = $this->hostname();
        $host = preg_replace('#/+$#', '', $host) ?: $host;
        if (preg_match('#^https?://#', $host)) {
            return $host;
        }

        return 'https://' . $host . ':' . (int) env('WHM_PORT', 2087);
    }

    private function hostname(): string
    {
        return trim((string) (
            $this->settings['whm_hostname']
            ?? env('WHM_HOSTNAME', env('WHM_HOST', env('WHM_API_HOST', '')))
        ));
    }

    private function username(): string
    {
        return trim((string) (
            $this->settings['whm_username']
            ?? env('WHM_USERNAME', env('WHM_RESELLER_USERNAME', env('WHM_API_USERNAME', '')))
        ));
    }

    private function apiToken(): string
    {
        return trim((string) ($this->settings['whm_api_token'] ?? env('WHM_API_TOKEN', '')));
    }

    private function packageAllowed(string $package): bool
    {
        $allowed = ['planetic_starter', 'planetic_business', 'planetic_pro', 'planetic_agency'];
        $extra = (string) env('WHM_ALLOWED_PACKAGES', '');
        foreach (preg_split('/[,\s]+/', $extra) ?: [] as $value) {
            $value = trim($value);
            if ($value !== '') {
                $allowed[] = $value;
            }
        }

        return in_array($package, array_values(array_unique($allowed)), true);
    }

    private function usernameFromDomain(string $domain): string
    {
        $label = explode('.', $domain)[0] ?? 'site';
        $label = preg_replace('/[^a-z0-9]/', '', strtolower($label)) ?: 'site';
        if (!preg_match('/^[a-z]/', $label)) {
            $label = 'p' . $label;
        }

        return substr($label, 0, 12);
    }

    private function candidateUsername(string $base, int $attempt): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($base)) ?: 'site';
        if (!preg_match('/^[a-z]/', $base)) {
            $base = 'p' . $base;
        }

        if ($attempt === 0) {
            return substr($base, 0, 16);
        }

        $suffix = substr((string) random_int(1000, 9999), 0, 4);
        return substr($base, 0, 12) . $suffix;
    }

    private function generatePassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $password = '';
        for ($i = 0; $i < 22; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password . '9a!';
    }

    private function encryptPassword(string $password): string
    {
        $keyMaterial = trim((string) env('CPANEL_CREDENTIAL_KEY', env('APP_KEY', '')));
        if ($keyMaterial === '' || !function_exists('openssl_encrypt')) {
            return '';
        }

        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($password, 'AES-256-CBC', hash('sha256', $keyMaterial, true), OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            return '';
        }

        return 'v1:' . base64_encode($iv . $ciphertext);
    }

    private function normaliseDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?: $domain;
        $domain = preg_replace('#/.*$#', '', $domain) ?: $domain;
        $domain = trim($domain, ". \t\n\r\0\x0B");
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            return '';
        }

        return $domain;
    }

    private function sslVerify(): bool
    {
        return filter_var(env('WHM_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
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

        unset($context['token'], $context['api_token'], $context['password']);
        @file_put_contents(
            $dir . '/whm-provisioning.log',
            '[' . date('c') . '] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }
}
