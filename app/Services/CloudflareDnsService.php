<?php

declare(strict_types=1);

namespace App\Services;

final class CloudflareDnsService
{
    private string $token;
    private string $accountId;

    public function __construct(private array $settings = [])
    {
        $this->token = trim((string) ($settings['cloudflare_api_token'] ?? env('CLOUDFLARE_API_TOKEN', '')));
        $this->accountId = trim((string) ($settings['cloudflare_account_id'] ?? env('CLOUDFLARE_ACCOUNT_ID', '')));
    }

    public function configuredForDomain(string $domain): bool
    {
        return $this->token !== '' && $this->zoneIdForDomain($domain) !== '';
    }

    public function providerLabel(string $domain): string
    {
        return $this->configuredForDomain($domain) ? 'Cloudflare connected' : 'DNS records unavailable';
    }

    public function testConnection(): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'message' => 'Cloudflare API token is not configured.'];
        }

        $result = $this->api('GET', '/user/tokens/verify');
        if (!$result['ok']) {
            return ['ok' => false, 'message' => (string) ($result['message'] ?? 'Cloudflare API token verification failed.')];
        }

        return [
            'ok' => true,
            'message' => $this->accountId !== '' ? 'Cloudflare API token verified.' : 'Cloudflare API token verified, but account ID is missing.',
        ];
    }

    public function testAccount(): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'message' => 'Cloudflare API token is not configured.'];
        }
        if ($this->accountId === '') {
            return ['ok' => false, 'message' => 'Cloudflare account ID is not configured.'];
        }

        $result = $this->api('GET', '/accounts/' . rawurlencode($this->accountId));
        if (!$result['ok']) {
            return ['ok' => false, 'message' => (string) ($result['message'] ?? 'Cloudflare account ID could not be verified.')];
        }

        $account = (array) ($result['data']['result'] ?? []);
        return [
            'ok' => true,
            'message' => 'Cloudflare account ID verified' . ((string) ($account['name'] ?? '') !== '' ? ' for ' . (string) $account['name'] : '') . '.',
        ];
    }

    public function listRecords(string $domain): array
    {
        $zoneId = $this->zoneIdForDomain($domain);
        if ($zoneId === '' || $this->token === '') {
            return [
                'ok' => false,
                'available' => false,
                'message' => 'DNS records are not available yet. Please connect this domain to Planetic DNS or Cloudflare to manage records here.',
                'records' => [],
            ];
        }

        $result = $this->api('GET', '/zones/' . rawurlencode($zoneId) . '/dns_records?per_page=200');
        if (!$result['ok']) {
            return ['ok' => false, 'available' => true, 'message' => 'DNS records could not be loaded right now.', 'records' => []];
        }

        return [
            'ok' => true,
            'available' => true,
            'records' => array_map([$this, 'normaliseRecord'], $result['data']['result'] ?? []),
        ];
    }

    public function saveRecord(string $domain, array $record, string $recordId = ''): array
    {
        $zoneId = $this->zoneIdForDomain($domain);
        if ($zoneId === '' || $this->token === '') {
            return ['ok' => false, 'message' => 'DNS records are not available yet. Please connect this domain to Planetic DNS or Cloudflare to manage records here.'];
        }

        [$payload, $errors] = $this->validateRecord($domain, $record);
        if ($errors) {
            return ['ok' => false, 'message' => implode(' ', $errors)];
        }

        $path = '/zones/' . rawurlencode($zoneId) . '/dns_records';
        $method = 'POST';
        if ($recordId !== '') {
            $path .= '/' . rawurlencode($recordId);
            $method = 'PATCH';
        }

        $result = $this->api($method, $path, $payload);
        if (!$result['ok']) {
            return ['ok' => false, 'message' => 'DNS record could not be saved right now.'];
        }

        return ['ok' => true, 'record' => $this->normaliseRecord($result['data']['result'] ?? [])];
    }

    public function deleteRecord(string $domain, string $recordId): array
    {
        $zoneId = $this->zoneIdForDomain($domain);
        if ($zoneId === '' || $this->token === '') {
            return ['ok' => false, 'message' => 'DNS records are not available for this domain yet.'];
        }

        if (!preg_match('/^[A-Za-z0-9_-]{10,80}$/', $recordId)) {
            return ['ok' => false, 'message' => 'DNS record could not be found.'];
        }

        $result = $this->api('DELETE', '/zones/' . rawurlencode($zoneId) . '/dns_records/' . rawurlencode($recordId));
        if (!$result['ok']) {
            return ['ok' => false, 'message' => 'DNS record could not be deleted right now.'];
        }

        return ['ok' => true];
    }

    public function provisionHostingZone(string $domain, string $serverIp): array
    {
        $domain = $this->normaliseDomain($domain);
        if ($domain === '') {
            return ['ok' => false, 'message' => 'A valid domain is required for DNS provisioning.'];
        }
        if (!filter_var($serverIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['ok' => false, 'message' => 'A valid hosting server IPv4 address is required for DNS provisioning.'];
        }
        if ($this->token === '') {
            return ['ok' => false, 'message' => 'Cloudflare API token is not configured.'];
        }

        $zone = $this->findZone($domain);
        if (empty($zone['ok'])) {
            return ['ok' => false, 'message' => $zone['message'] ?? 'Cloudflare zone lookup failed.'];
        }
        $zoneAction = !empty($zone['found']) ? 'found' : 'created';
        if (empty($zone['found'])) {
            $zone = $this->createZone($domain);
        }
        if (empty($zone['ok'])) {
            return ['ok' => false, 'message' => $zone['message'] ?? 'Cloudflare zone could not be created.'];
        }

        $zoneId = (string) ($zone['zone_id'] ?? '');
        if ($zoneId === '') {
            return ['ok' => false, 'message' => 'Cloudflare did not return a zone ID.'];
        }

        $recordResults = [];
        $rootRecord = $this->upsertRecord($zoneId, [
            'type' => 'A',
            'name' => $domain,
            'content' => $serverIp,
            'ttl' => 1,
            'proxied' => $this->defaultProxied(),
        ]);
        $recordResults[] = $rootRecord;

        $wwwRecord = $this->upsertRecord($zoneId, [
            'type' => 'CNAME',
            'name' => 'www.' . $domain,
            'content' => $domain,
            'ttl' => 1,
            'proxied' => $this->defaultProxied(),
        ]);
        $recordResults[] = $wwwRecord;

        foreach ($this->defaultMxRecords($domain) as $record) {
            $recordResults[] = $this->upsertRecord($zoneId, $record);
        }

        $spf = $this->defaultSpfRecord();
        if ($spf !== '') {
            $recordResults[] = $this->upsertTxtRecord($zoneId, $domain, $spf, true);
        }

        foreach ($this->defaultTxtRecords($domain) as $record) {
            $recordResults[] = $this->upsertTxtRecord($zoneId, $record['name'], $record['content'], false);
        }

        $this->applyHttpsSettings($zoneId);

        $failed = array_values(array_filter($recordResults, static fn (array $result): bool => empty($result['ok'])));
        if ($failed) {
            return [
                'ok' => false,
                'zone_id' => $zoneId,
                'nameservers' => $zone['nameservers'] ?? [],
                'records' => $recordResults,
                'message' => (string) ($failed[0]['message'] ?? 'One or more DNS records could not be created.'),
            ];
        }

        return [
            'ok' => true,
            'zone_id' => $zoneId,
            'zone_action' => $zoneAction,
            'nameservers' => $zone['nameservers'] ?? [],
            'records' => array_map(static fn (array $result): array => $result['record'] ?? [], $recordResults),
            'record_results' => $recordResults,
        ];
    }

    private function findZone(string $domain): array
    {
        $domain = $this->normaliseDomain($domain);
        if ($domain === '' || $this->token === '') {
            return ['ok' => false, 'found' => false, 'message' => 'Cloudflare zone lookup is not configured.'];
        }

        $result = $this->api('GET', '/zones?name=' . rawurlencode($domain) . '&per_page=1');
        if (!$result['ok']) {
            return ['ok' => false, 'found' => false, 'message' => (string) ($result['message'] ?? 'Cloudflare zone lookup failed.')];
        }

        $zones = $result['data']['result'] ?? [];
        if (!is_array($zones) || $zones === []) {
            return ['ok' => true, 'found' => false];
        }

        $zone = (array) $zones[0];
        return [
            'ok' => true,
            'found' => true,
            'zone_id' => (string) ($zone['id'] ?? ''),
            'nameservers' => array_values(array_filter(array_map('strval', $zone['name_servers'] ?? []))),
            'zone' => $zone,
        ];
    }

    private function createZone(string $domain): array
    {
        if ($this->accountId === '') {
            return ['ok' => false, 'found' => false, 'message' => 'Cloudflare account ID is required to create a new zone.'];
        }

        $result = $this->api('POST', '/zones', [
            'name' => $domain,
            'account' => ['id' => $this->accountId],
            'type' => 'full',
        ]);
        if (!$result['ok']) {
            return ['ok' => false, 'found' => false, 'message' => (string) ($result['message'] ?? 'Cloudflare zone could not be created.')];
        }

        $zone = (array) ($result['data']['result'] ?? []);
        return [
            'ok' => true,
            'found' => true,
            'created' => true,
            'zone_id' => (string) ($zone['id'] ?? ''),
            'nameservers' => array_values(array_filter(array_map('strval', $zone['name_servers'] ?? []))),
            'zone' => $zone,
        ];
    }

    private function upsertRecord(string $zoneId, array $payload): array
    {
        $name = (string) ($payload['name'] ?? '');
        $type = strtoupper((string) ($payload['type'] ?? ''));
        $existing = $this->api(
            'GET',
            '/zones/' . rawurlencode($zoneId) . '/dns_records?type=' . rawurlencode($type) . '&name=' . rawurlencode($name) . '&per_page=20'
        );
        if (!$existing['ok']) {
            return ['ok' => false, 'message' => (string) ($existing['message'] ?? 'Existing DNS records could not be checked.')];
        }

        $records = is_array($existing['data']['result'] ?? null) ? $existing['data']['result'] : [];
        $target = null;
        foreach ($records as $record) {
            $record = (array) $record;
            $sameContent = (string) ($record['content'] ?? '') === (string) ($payload['content'] ?? '');
            $samePriority = !isset($payload['priority']) || (int) ($record['priority'] ?? 0) === (int) $payload['priority'];
            if ($sameContent && $samePriority) {
                $target = $record;
                break;
            }
        }
        if ($target === null && $records) {
            $target = (array) $records[0];
        }
        if ($target !== null && $this->recordMatchesPayload($target, $payload)) {
            return ['ok' => true, 'action' => 'found', 'record' => $this->normaliseRecord($target)];
        }

        $method = 'POST';
        $action = 'created';
        $path = '/zones/' . rawurlencode($zoneId) . '/dns_records';
        if (!empty($target['id'])) {
            $method = 'PATCH';
            $action = 'updated';
            $path .= '/' . rawurlencode((string) $target['id']);
        }

        $saved = $this->api($method, $path, $payload);
        if (!$saved['ok']) {
            return ['ok' => false, 'message' => (string) ($saved['message'] ?? 'DNS record could not be saved.')];
        }

        return ['ok' => true, 'action' => $action, 'record' => $this->normaliseRecord((array) ($saved['data']['result'] ?? []))];
    }

    private function upsertTxtRecord(string $zoneId, string $name, string $content, bool $replaceExistingSpf): array
    {
        $existing = $this->api(
            'GET',
            '/zones/' . rawurlencode($zoneId) . '/dns_records?type=TXT&name=' . rawurlencode($name) . '&per_page=50'
        );
        if (!$existing['ok']) {
            return ['ok' => false, 'message' => (string) ($existing['message'] ?? 'Existing TXT records could not be checked.')];
        }

        $target = null;
        foreach ((array) ($existing['data']['result'] ?? []) as $record) {
            $record = (array) $record;
            $recordContent = trim((string) ($record['content'] ?? ''));
            if ($recordContent === $content || ($replaceExistingSpf && str_starts_with(strtolower($recordContent), 'v=spf1'))) {
                $target = $record;
                break;
            }
        }

        $payload = ['type' => 'TXT', 'name' => $name, 'content' => $content, 'ttl' => 1];
        if ($target !== null && $this->recordMatchesPayload($target, $payload)) {
            return ['ok' => true, 'action' => 'found', 'record' => $this->normaliseRecord($target)];
        }

        $method = 'POST';
        $action = 'created';
        $path = '/zones/' . rawurlencode($zoneId) . '/dns_records';
        if (!empty($target['id'])) {
            $method = 'PATCH';
            $action = 'updated';
            $path .= '/' . rawurlencode((string) $target['id']);
        }

        $saved = $this->api($method, $path, $payload);
        if (!$saved['ok']) {
            return ['ok' => false, 'message' => (string) ($saved['message'] ?? 'TXT record could not be saved.')];
        }

        return ['ok' => true, 'action' => $action, 'record' => $this->normaliseRecord((array) ($saved['data']['result'] ?? []))];
    }

    private function applyHttpsSettings(string $zoneId): void
    {
        $sslMode = strtolower(trim((string) ($this->settings['cloudflare_ssl_mode'] ?? env('CLOUDFLARE_SSL_MODE', 'full'))));
        if (in_array($sslMode, ['off', 'flexible', 'full', 'strict', 'origin_pull'], true)) {
            $this->api('PATCH', '/zones/' . rawurlencode($zoneId) . '/settings/ssl', ['value' => $sslMode]);
        }

        $alwaysHttps = filter_var(
            $this->settings['cloudflare_always_use_https'] ?? env('CLOUDFLARE_ALWAYS_USE_HTTPS', true),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );
        if ($alwaysHttps !== null) {
            $this->api('PATCH', '/zones/' . rawurlencode($zoneId) . '/settings/always_use_https', ['value' => $alwaysHttps ? 'on' : 'off']);
        }
    }

    private function recordMatchesPayload(array $record, array $payload): bool
    {
        foreach (['type', 'name', 'content'] as $field) {
            if (isset($payload[$field]) && (string) ($record[$field] ?? '') !== (string) $payload[$field]) {
                return false;
            }
        }

        if (isset($payload['priority']) && (int) ($record['priority'] ?? 0) !== (int) $payload['priority']) {
            return false;
        }

        if (array_key_exists('proxied', $payload) && (bool) ($record['proxied'] ?? false) !== (bool) $payload['proxied']) {
            return false;
        }

        if (isset($payload['ttl']) && (int) ($record['ttl'] ?? 1) !== (int) $payload['ttl']) {
            return false;
        }

        return true;
    }

    private function defaultMxRecords(string $domain): array
    {
        $raw = trim((string) (
            $this->settings['default_mx_records']
            ?? env('DEFAULT_MX_RECORDS', env('MAIL_MX_RECORDS', ''))
        ));
        if ($raw === '') {
            return [];
        }

        $records = [];
        foreach (preg_split('/[\r\n,]+/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/[\s|]+/', $line) ?: [];
            $priority = 10;
            $target = '';
            if (isset($parts[0]) && ctype_digit($parts[0])) {
                $priority = (int) $parts[0];
                $target = (string) ($parts[1] ?? '');
            } else {
                $target = (string) ($parts[0] ?? '');
                $priority = (int) ($parts[1] ?? 10);
            }
            $target = rtrim(str_replace('{domain}', $domain, strtolower($target)), '.');
            if (!$this->validHostname($target)) {
                continue;
            }

            $records[] = [
                'type' => 'MX',
                'name' => $domain,
                'content' => $target,
                'priority' => max(0, min(65535, $priority)),
                'ttl' => 1,
            ];
        }

        return $records;
    }

    private function defaultSpfRecord(): string
    {
        return trim((string) (
            $this->settings['default_spf_record']
            ?? env('DEFAULT_SPF_RECORD', env('MAIL_SPF_RECORD', ''))
        ));
    }

    private function defaultTxtRecords(string $domain): array
    {
        $raw = trim((string) (
            $this->settings['default_txt_records']
            ?? env('DEFAULT_TXT_RECORDS', env('CLOUDFLARE_DEFAULT_TXT_RECORDS', ''))
        ));
        $dkim = trim((string) (
            $this->settings['default_dkim_records']
            ?? env('DEFAULT_DKIM_RECORDS', env('MAIL_DKIM_RECORDS', ''))
        ));
        $raw = trim($raw . "\n" . $dkim);
        if ($raw === '') {
            return [];
        }

        $records = [];
        foreach (preg_split('/\R+/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, '|')) {
                continue;
            }

            [$name, $content] = array_map('trim', explode('|', $line, 2));
            $name = str_replace('{domain}', $domain, strtolower($name));
            if ($name === '@') {
                $name = $domain;
            } elseif (!str_ends_with($name, '.' . $domain) && $name !== $domain) {
                $name .= '.' . $domain;
            }
            $content = str_replace('{domain}', $domain, $content);
            if ($name !== '' && $content !== '') {
                $records[] = ['name' => $name, 'content' => $content];
            }
        }

        return $records;
    }

    private function defaultProxied(): bool
    {
        return filter_var(
            $this->settings['cloudflare_proxy_default'] ?? env('CLOUDFLARE_PROXY_DEFAULT', false),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? false;
    }

    private function validateRecord(string $domain, array $record): array
    {
        $type = strtoupper(trim((string) ($record['type'] ?? '')));
        $allowed = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'SRV', 'CAA'];
        $errors = [];
        if (!in_array($type, $allowed, true)) {
            $errors[] = 'Choose a supported DNS record type.';
        }

        $name = $this->recordName($domain, (string) ($record['name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Enter a valid record name.';
        }

        $ttl = max(60, min(86400, (int) ($record['ttl'] ?? 3600)));
        if ((int) ($record['ttl'] ?? 1) === 1) {
            $ttl = 1;
        }

        $content = trim((string) ($record['content'] ?? ''));
        $payload = ['type' => $type, 'name' => $name, 'ttl' => $ttl];
        if (in_array($type, ['A', 'AAAA', 'CNAME'], true)) {
            $payload['proxied'] = !empty($record['proxied']);
        }

        if ($type === 'A' && !filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $errors[] = 'Enter a valid IPv4 address.';
        }
        if ($type === 'AAAA' && !filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $errors[] = 'Enter a valid IPv6 address.';
        }
        if ($type === 'CNAME' && !$this->validHostname($content)) {
            $errors[] = 'Enter a valid CNAME target.';
        }
        if ($type === 'MX') {
            if (!$this->validHostname($content)) {
                $errors[] = 'Enter a valid mail server.';
            }
            $payload['priority'] = max(0, min(65535, (int) ($record['priority'] ?? 10)));
        }
        if ($type === 'TXT' && $content === '') {
            $errors[] = 'Enter a TXT value.';
        }
        if ($type === 'SRV') {
            $target = trim((string) ($record['target'] ?? $content));
            if (!$this->validHostname($target)) {
                $errors[] = 'Enter a valid SRV target.';
            }
            $payload['data'] = [
                'service' => trim((string) ($record['service'] ?? $record['name'] ?? '')),
                'proto' => str_starts_with((string) ($record['proto'] ?? ''), '_') ? (string) $record['proto'] : '_tcp',
                'name' => $name,
                'target' => $target,
                'priority' => max(0, min(65535, (int) ($record['priority'] ?? 10))),
                'weight' => max(0, min(65535, (int) ($record['weight'] ?? 0))),
                'port' => max(1, min(65535, (int) ($record['port'] ?? 443))),
            ];
            $content = $target;
        }
        if ($type === 'CAA') {
            $tag = strtolower(trim((string) ($record['tag'] ?? 'issue')));
            if (!in_array($tag, ['issue', 'issuewild', 'iodef'], true)) {
                $errors[] = 'Choose a valid CAA tag.';
            }
            if ($content === '') {
                $errors[] = 'Enter a CAA value.';
            }
            $payload['data'] = [
                'flags' => max(0, min(255, (int) ($record['flag'] ?? 0))),
                'tag' => $tag,
                'value' => $content,
            ];
        }

        if (!isset($payload['data'])) {
            $payload['content'] = $content;
        }

        return [$payload, array_values(array_unique($errors))];
    }

    private function recordName(string $domain, string $name): string
    {
        $domain = strtolower(trim($domain));
        $name = strtolower(trim($name));
        if ($name === '' || $name === '@') {
            return $domain;
        }
        $name = trim($name, '.');
        if ($name === $domain || str_ends_with($name, '.' . $domain)) {
            return $name;
        }

        return $name . '.' . $domain;
    }

    private function normaliseDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?: $domain;
        $domain = preg_replace('#/.*$#', '', $domain) ?: $domain;
        $domain = trim($domain, ". \t\n\r\0\x0B");
        return $this->validHostname($domain) ? $domain : '';
    }

    private function validHostname(string $hostname): bool
    {
        $hostname = strtolower(trim($hostname, ". \t\n\r\0\x0B"));
        return (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $hostname);
    }

    private function normaliseRecord(array $record): array
    {
        $data = is_array($record['data'] ?? null) ? $record['data'] : [];
        return [
            'id' => (string) ($record['id'] ?? ''),
            'type' => (string) ($record['type'] ?? ''),
            'name' => (string) ($record['name'] ?? ''),
            'content' => (string) ($record['content'] ?? $data['target'] ?? $data['value'] ?? ''),
            'priority' => $record['priority'] ?? $data['priority'] ?? null,
            'ttl' => (int) ($record['ttl'] ?? 1),
            'proxied' => (bool) ($record['proxied'] ?? false),
            'data' => $data,
            'updated_at' => (string) ($record['modified_on'] ?? ''),
        ];
    }

    private function zoneIdForDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $map = (string) ($this->settings['cloudflare_zone_map'] ?? env('CLOUDFLARE_ZONE_MAP', ''));
        foreach (preg_split('/[\r\n,]+/', $map) ?: [] as $entry) {
            [$mappedDomain, $zoneId] = array_pad(preg_split('/[:=|]/', trim($entry), 2) ?: [], 2, '');
            $mappedDomain = strtolower(trim($mappedDomain));
            if ($mappedDomain !== '' && $zoneId !== '' && ($domain === $mappedDomain || str_ends_with($domain, '.' . $mappedDomain))) {
                return trim($zoneId);
            }
        }

        $configured = trim((string) ($this->settings['cloudflare_zone_id'] ?? env('CLOUDFLARE_ZONE_ID', '')));
        if ($configured !== '') {
            return $configured;
        }

        $zone = $this->findZone($domain);
        return !empty($zone['ok']) && !empty($zone['found']) ? (string) ($zone['zone_id'] ?? '') : '';
    }

    private function api(string $method, string $path, array $payload = []): array
    {
        $url = 'https://api.cloudflare.com/client/v4' . $path;
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
        ];
        $body = '';
        $status = 0;
        $diagnostic = '';

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 25,
            ]);
            if ($payload) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
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
                    'content' => $payload ? json_encode($payload, JSON_UNESCAPED_SLASHES) : '',
                    'timeout' => 25,
                    'ignore_errors' => true,
                ],
            ]);
            $body = (string) @file_get_contents($url, false, $context);
            $status = $this->streamHttpStatus($http_response_header ?? []);
            $diagnostic = (string) (error_get_last()['message'] ?? '');
        }

        $decoded = json_decode($body, true);
        if ($body === '' || !is_array($decoded) || $status < 200 || $status >= 300 || empty($decoded['success'])) {
            $errors = is_array($decoded['errors'] ?? null) ? $decoded['errors'] : [];
            $message = 'Cloudflare DNS request failed.';
            if (isset($errors[0]) && is_array($errors[0]) && (string) ($errors[0]['message'] ?? '') !== '') {
                $message = (string) $errors[0]['message'];
            }
            $this->log('Cloudflare DNS API request failed.', [
                'method' => $method,
                'path' => $path,
                'http_status' => $status,
                'diagnostic' => $diagnostic,
                'errors' => $errors,
            ]);
            return ['ok' => false, 'message' => $message];
        }

        return ['ok' => true, 'data' => $decoded];
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

        unset($context['api_token'], $context['token'], $context['Authorization']);
        @file_put_contents(
            $dir . '/dns.log',
            '[' . date('c') . '] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }
}
