<?php

declare(strict_types=1);

namespace App\Services;

final class CloudflareDnsService
{
    private string $token;

    public function __construct(private array $settings = [])
    {
        $this->token = trim((string) ($settings['cloudflare_api_token'] ?? env('CLOUDFLARE_API_TOKEN', '')));
    }

    public function configuredForDomain(string $domain): bool
    {
        return $this->token !== '' && $this->zoneIdForDomain($domain) !== '';
    }

    public function providerLabel(string $domain): string
    {
        return $this->configuredForDomain($domain) ? 'Cloudflare connected' : 'DNS records unavailable';
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

        return trim((string) ($this->settings['cloudflare_zone_id'] ?? env('CLOUDFLARE_ZONE_ID', '')));
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
            $this->log('Cloudflare DNS API request failed.', [
                'method' => $method,
                'path' => $path,
                'http_status' => $status,
                'diagnostic' => $diagnostic,
                'errors' => is_array($decoded['errors'] ?? null) ? $decoded['errors'] : [],
            ]);
            return ['ok' => false, 'message' => 'Cloudflare DNS request failed.'];
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
