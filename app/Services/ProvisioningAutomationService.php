<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomerRepository;
use App\Models\ProvisioningRepository;

final class ProvisioningAutomationService
{
    private WhmAccountService $whm;
    private CloudflareDnsService $cloudflare;

    public function __construct(
        private WhmcsService $whmcs,
        private ProvisioningRepository $provisioning,
        private array $settings = []
    ) {
        $this->whm = new WhmAccountService($settings);
        $this->cloudflare = new CloudflareDnsService($settings);
    }

    public function ensureHostingProvisioned(array $order, array $service, string $whmPackage, string $source, bool $allowDirectWhmFallback = false): array
    {
        $orderId = (int) ($order['id'] ?? 0);
        $serviceId = $this->serviceId($service);
        $domain = $this->normaliseDomain((string) ($service['domain'] ?? $order['selected_domain'] ?? ''));
        $whmPackage = trim($whmPackage);

        if ($orderId <= 0 || $domain === '') {
            return ['ok' => false, 'message' => 'A paid hosting order with a valid domain is required.'];
        }
        if ($whmPackage === '') {
            return ['ok' => false, 'message' => 'No WHM package is mapped to this hosting order.'];
        }

        $this->provisioning->logStep($orderId, null, 'hosting', 'hosting_account_checked', 'processing', 'Checking hosting account provisioning state.', [
            'source' => $source,
            'domain' => $domain,
            'service_id' => $serviceId,
            'whm_package' => $whmPackage,
        ]);

        $username = trim((string) ($service['username'] ?? ''));
        $serverIp = trim((string) ($service['dedicatedip'] ?? $service['serverip'] ?? ''));
        $status = strtolower((string) ($service['status'] ?? ''));

        if ($this->whm->configured()) {
            $summary = $this->whm->accountSummary($domain);
            if (!empty($summary['ok']) && !empty($summary['found'])) {
                $username = (string) ($summary['username'] ?? $username);
                $serverIp = (string) ($summary['server_ip'] ?? $serverIp);
                $this->markHostingReady($order, $serviceId, $whmPackage, $username, $serverIp, '');
                return $this->finishDns($order, $serviceId, $domain, $serverIp, $source);
            }
        }

        if ($status === 'active' || $username !== '') {
            $serverIp = $serverIp !== '' ? $serverIp : $this->whm->serverIp();
            $this->markHostingReady($order, $serviceId, $whmPackage, $username, $serverIp, '');
            return $this->finishDns($order, $serviceId, $domain, $serverIp, $source);
        }

        if (!$allowDirectWhmFallback) {
            return ['ok' => true, 'pending' => true, 'message' => 'Hosting service is still waiting for WHMCS module setup.'];
        }

        $customer = $this->customerForOrder($order);
        $this->provisioning->logStep($orderId, null, 'hosting', 'whm_create_requested', 'processing', 'Requesting cPanel account creation through WHM.', [
            'source' => $source,
            'domain' => $domain,
            'service_id' => $serviceId,
            'whm_package' => $whmPackage,
        ]);

        $created = $this->whm->createOrFindAccount($domain, $whmPackage, (string) ($customer['email'] ?? ''));
        if (empty($created['ok'])) {
            $message = (string) ($created['message'] ?? 'WHM could not create the cPanel account.');
            $this->provisioning->logStep($orderId, null, 'hosting', 'whm_create_failed', 'failed', $message, [
                'source' => $source,
                'domain' => $domain,
                'service_id' => $serviceId,
                'whm_package' => $whmPackage,
            ]);

            return ['ok' => false, 'message' => $message];
        }

        $username = (string) ($created['username'] ?? '');
        $serverIp = (string) ($created['server_ip'] ?? $this->whm->serverIp());
        $encryptedPassword = (string) ($created['encrypted_password'] ?? '');
        if ((string) ($created['password'] ?? '') !== '' && $encryptedPassword === '') {
            $this->provisioning->logStep($orderId, null, 'hosting', 'cpanel_password_storage', 'failed', 'cPanel password was generated but could not be encrypted for storage. Set CPANEL_CREDENTIAL_KEY or APP_KEY.', [
                'source' => $source,
                'domain' => $domain,
                'service_id' => $serviceId,
            ]);
        }
        $this->markHostingReady($order, $serviceId, $whmPackage, $username, $serverIp, $encryptedPassword);
        $this->updateWhmcsServiceCredentials($serviceId, $domain, $username, (string) ($created['password'] ?? ''), $serverIp);
        $this->emailCpanelCredentials($order, $customer, $domain, $username, (string) ($created['password'] ?? ''), $serverIp, (string) ($created['cpanel_url'] ?? ''));

        $this->provisioning->logStep($orderId, null, 'hosting', 'whm_create_completed', 'completed', 'cPanel account is ready.', [
            'source' => $source,
            'domain' => $domain,
            'service_id' => $serviceId,
            'username' => $username,
            'server_ip' => $serverIp,
            'existing' => !empty($created['existing']),
            'whm_package' => $whmPackage,
        ]);

        return $this->finishDns($order, $serviceId, $domain, $serverIp, $source);
    }

    public function retryHostingProvisioning(array $item, string $source = 'admin_retry'): array
    {
        $order = $this->orderFromItem($item);
        $serviceId = (int) ($item['whmcs_service_id'] ?? 0);
        $service = [
            'id' => $serviceId,
            'serviceid' => $serviceId,
            'domain' => (string) ($item['domain_name'] ?? $item['selected_domain'] ?? ''),
            'username' => (string) ($item['cpanel_username'] ?? ''),
            'status' => (string) ($item['hosting_setup_status'] ?? ''),
        ];

        if ($serviceId > 0) {
            $module = $this->whmcs->moduleCreate($serviceId);
            $this->provisioning->logStep((int) ($order['id'] ?? 0), (int) ($item['id'] ?? 0), 'hosting', 'whmcs_module_create_retry', !empty($module['ok']) ? 'completed' : 'failed', (string) ($module['message'] ?? 'WHMCS ModuleCreate retry requested.'), [
                'source' => $source,
                'service_id' => $serviceId,
            ]);
        }

        return $this->ensureHostingProvisioned(
            $order,
            $service,
            (string) ($item['whm_package'] ?? ''),
            $source,
            true
        );
    }

    private function finishDns(array $order, int $serviceId, string $domain, string $serverIp, string $source): array
    {
        $orderId = (int) ($order['id'] ?? 0);
        $serverIp = $serverIp !== '' ? $serverIp : $this->whm->serverIp();
        if ($serverIp === '') {
            $message = 'Hosting is active, but no server IP is configured for DNS automation.';
            $this->provisioning->markDnsIssue($order, $domain, $message);
            $this->provisioning->logStep($orderId, null, 'dns', 'cloudflare_skipped', 'failed', $message, [
                'source' => $source,
                'domain' => $domain,
                'service_id' => $serviceId,
            ]);

            return ['ok' => true, 'dns_ok' => false, 'message' => $message];
        }

        $this->provisioning->logStep($orderId, null, 'dns', 'cloudflare_zone_requested', 'processing', 'Creating or loading Cloudflare zone and default DNS records.', [
            'source' => $source,
            'domain' => $domain,
            'server_ip' => $serverIp,
        ]);

        $dns = $this->cloudflare->provisionHostingZone($domain, $serverIp);
        if (empty($dns['ok'])) {
            $message = (string) ($dns['message'] ?? 'Cloudflare DNS provisioning failed.');
            $this->provisioning->markDnsIssue($order, $domain, $message);
            $this->provisioning->logStep($orderId, null, 'dns', 'cloudflare_zone_failed', 'failed', $message, [
                'source' => $source,
                'domain' => $domain,
                'zone_id' => (string) ($dns['zone_id'] ?? ''),
            ]);

            return ['ok' => true, 'dns_ok' => false, 'message' => $message];
        }

        $nameservers = array_values(array_filter(array_map('strval', $dns['nameservers'] ?? [])));
        $records = is_array($dns['records'] ?? null) ? $dns['records'] : [];
        $this->provisioning->markCloudflareProvisioned($order, $domain, (string) ($dns['zone_id'] ?? ''), $nameservers, $records);
        $this->updateRegistrarNameservers($order, $domain, $nameservers, $source);

        $this->provisioning->logStep($orderId, null, 'dns', 'cloudflare_zone_completed', 'completed', 'Cloudflare zone and default DNS records are ready.', [
            'source' => $source,
            'domain' => $domain,
            'zone_id' => (string) ($dns['zone_id'] ?? ''),
            'nameservers' => $nameservers,
        ]);

        return ['ok' => true, 'dns_ok' => true, 'zone_id' => (string) ($dns['zone_id'] ?? ''), 'nameservers' => $nameservers];
    }

    private function markHostingReady(array $order, int $serviceId, string $whmPackage, string $username, string $serverIp, string $encryptedPassword): void
    {
        $this->provisioning->markHostingAccountProvisioned($order, $serviceId, $whmPackage, $username, $serverIp, $encryptedPassword);
    }

    private function updateWhmcsServiceCredentials(int $serviceId, string $domain, string $username, string $password, string $serverIp): void
    {
        if ($serviceId <= 0 || $username === '') {
            return;
        }

        $payload = [
            'username' => $username,
            'domain' => $domain,
            'dedicatedip' => $serverIp,
            'status' => 'Active',
        ];
        if ($password !== '') {
            $payload['password2'] = $password;
        }

        $updated = $this->whmcs->updateClientProduct($serviceId, $payload);
        $this->provisioning->logStep(null, null, 'whmcs', 'whmcs_service_credentials_update', !empty($updated['ok']) ? 'completed' : 'failed', (string) ($updated['message'] ?? 'WHMCS service credentials updated.'), [
            'service_id' => $serviceId,
            'domain' => $domain,
            'username' => $username,
        ]);
    }

    private function emailCpanelCredentials(array $order, array $customer, string $domain, string $username, string $password, string $serverIp, string $cpanelUrl): void
    {
        if (empty($customer['email']) || $username === '' || $password === '') {
            return;
        }

        $sent = Mailer::hostingProvisioned($this->settings, $customer, [
            'domain' => $domain,
            'username' => $username,
            'password' => $password,
            'server_ip' => $serverIp,
            'cpanel_url' => $cpanelUrl !== '' ? $cpanelUrl : $this->whm->cpanelUrl($domain),
        ]);
        if ($sent) {
            $this->provisioning->markCpanelPasswordSent((int) ($order['id'] ?? 0), $domain);
        }

        $this->provisioning->logStep((int) ($order['id'] ?? 0), null, 'hosting', 'cpanel_credentials_email', $sent ? 'completed' : 'failed', $sent ? 'cPanel credentials email was sent.' : 'cPanel credentials email could not be sent.', [
            'domain' => $domain,
            'username' => $username,
            'email' => (string) ($customer['email'] ?? ''),
        ]);
    }

    private function updateRegistrarNameservers(array $order, string $domain, array $nameservers, string $source): void
    {
        if (count($nameservers) < 2) {
            return;
        }

        $domainId = $this->whmcsDomainId($order, $domain);
        if ($domainId <= 0) {
            return;
        }

        $indexed = [];
        foreach (array_values($nameservers) as $index => $nameserver) {
            $indexed[$index + 1] = $nameserver;
        }
        $updated = $this->whmcs->updateDomainNameservers($domainId, $indexed);
        $this->provisioning->logStep((int) ($order['id'] ?? 0), null, 'domain', 'registrar_nameservers_update', !empty($updated['ok']) ? 'completed' : 'failed', (string) ($updated['message'] ?? 'Registrar nameservers were updated.'), [
            'source' => $source,
            'domain' => $domain,
            'domain_id' => $domainId,
            'nameservers' => $nameservers,
        ]);
    }

    private function whmcsDomainId(array $order, string $domain): int
    {
        $clientId = (int) ($order['whmcs_client_id'] ?? 0);
        if ($clientId <= 0) {
            return 0;
        }

        $domains = $this->whmcs->domainsForClient($clientId);
        if (empty($domains['ok'])) {
            return 0;
        }

        foreach (($domains['domains'] ?? []) as $row) {
            $candidate = strtolower(trim((string) ($row['domainname'] ?? $row['domain'] ?? '')));
            if ($candidate === $domain) {
                return (int) ($row['id'] ?? $row['domainid'] ?? 0);
            }
        }

        return 0;
    }

    private function customerForOrder(array $order): array
    {
        $customerId = (int) ($order['customer_user_id'] ?? 0);
        if ($customerId <= 0) {
            return [];
        }

        return (new CustomerRepository())->find($customerId) ?: [];
    }

    private function orderFromItem(array $item): array
    {
        return [
            'id' => (int) ($item['customer_order_id'] ?? 0),
            'customer_user_id' => (int) ($item['customer_user_id'] ?? 0),
            'whmcs_client_id' => (int) ($item['whmcs_client_id'] ?? 0),
            'whmcs_order_id' => (int) ($item['whmcs_order_id'] ?? 0),
            'whmcs_invoice_id' => (int) ($item['whmcs_invoice_id'] ?? 0),
            'order_type' => (string) ($item['order_type'] ?? ''),
            'selected_domain' => (string) ($item['selected_domain'] ?? $item['domain_name'] ?? ''),
            'hosting_plan_slug' => (string) ($item['hosting_plan_slug'] ?? $item['order_hosting_plan_slug'] ?? ''),
            'package_label' => (string) ($item['display_name'] ?? $item['order_package_label'] ?? ''),
            'billing_cycle' => (string) ($item['billing_cycle'] ?? $item['order_billing_cycle'] ?? ''),
            'whm_package' => (string) ($item['whm_package'] ?? ''),
        ];
    }

    private function serviceId(array $service): int
    {
        foreach (['id', 'serviceid', 'service_id', 'hostingid', 'relid'] as $key) {
            $id = (int) ($service[$key] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
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
}
