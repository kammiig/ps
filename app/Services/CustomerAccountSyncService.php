<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProvisioningRepository;

final class CustomerAccountSyncService
{
    public function __construct(
        private WhmcsService $whmcs,
        private ProvisioningRepository $provisioning,
        private array $settings = []
    ) {
    }

    public function sync(array $account): array
    {
        $clientId = (int) ($account['whmcs_client_id'] ?? 0);
        if ($clientId <= 0) {
            return ['ok' => false, 'message' => 'No billing account is linked yet.'];
        }

        $summary = ['ok' => true, 'hosting' => 0, 'website_projects' => 0, 'domains' => 0, 'invoices' => 0];

        $domains = $this->whmcs->domainsForClient($clientId);
        if (!empty($domains['ok'])) {
            $summary['domains'] = count($domains['domains'] ?? []);
        } else {
            $this->log('Domain sync read failed.', ['client_id' => $clientId, 'message' => $domains['message'] ?? '']);
        }

        $invoices = $this->whmcs->invoicesForClient($clientId);
        if (!empty($invoices['ok'])) {
            $summary['invoices'] = count($invoices['invoices'] ?? []);
        } else {
            $this->log('Invoice sync read failed.', ['client_id' => $clientId, 'message' => $invoices['message'] ?? '']);
        }

        $products = $this->whmcs->productsForClient($clientId);
        if (empty($products['ok'])) {
            $this->log('Service sync read failed.', ['client_id' => $clientId, 'message' => $products['message'] ?? '']);
            return $summary;
        }

        foreach (($products['products'] ?? []) as $service) {
            if ($this->isWebsiteDevelopmentService($service)) {
                try {
                    $this->provisioning->upsertWebsiteProjectForAccount($account, $service);
                    $summary['website_projects']++;
                } catch (\Throwable $exception) {
                    $this->log('Website project sync failed.', [
                        'client_id' => $clientId,
                        'service_id' => $this->serviceId($service),
                        'type' => get_class($exception),
                        'message' => $exception->getMessage(),
                    ]);
                }
                continue;
            }

            if (!$this->looksLikeHostingService($service)) {
                continue;
            }

            try {
                $this->provisioning->upsertHostingServiceForAccount($account, $service, $this->whmPackageForService($service));
                $summary['hosting']++;
            } catch (\Throwable $exception) {
                $this->log('Hosting service sync failed.', [
                    'client_id' => $clientId,
                    'service_id' => $this->serviceId($service),
                    'type' => get_class($exception),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    public function isWebsiteDevelopmentService(array $service): bool
    {
        $productId = $this->productId($service);
        $ids = $this->websiteProductIds();
        if ($productId > 0 && in_array($productId, $ids, true)) {
            return true;
        }

        $name = strtolower((string) ($service['name'] ?? $service['productname'] ?? $service['groupname'] ?? ''));
        return str_contains($name, 'website development')
            || str_contains($name, 'bespoke website')
            || str_contains($name, 'complete website');
    }

    public function whmPackageForService(array $service): string
    {
        $text = strtolower(
            (string) ($service['name'] ?? '') . ' '
            . (string) ($service['productname'] ?? '') . ' '
            . (string) ($service['groupname'] ?? '')
        );

        if (str_contains($text, 'starter')) {
            return 'planetic_starter';
        }
        if (str_contains($text, 'business')) {
            return 'planetic_business';
        }
        if (str_contains($text, 'agency') || str_contains($text, 'ecommerce') || str_contains($text, 'commerce') || str_contains($text, 'reseller')) {
            return 'planetic_agency';
        }
        if (str_contains($text, 'pro') || str_contains($text, 'wordpress')) {
            return 'planetic_pro';
        }

        return '';
    }

    private function looksLikeHostingService(array $service): bool
    {
        $type = strtolower((string) ($service['type'] ?? $service['producttype'] ?? ''));
        if ($type !== '' && str_contains($type, 'hosting')) {
            return true;
        }

        $name = strtolower((string) ($service['name'] ?? $service['productname'] ?? $service['groupname'] ?? ''));
        if ($name === '') {
            return false;
        }

        return str_contains($name, 'hosting')
            || str_contains($name, 'cpanel')
            || str_contains($name, 'wordpress')
            || str_contains($name, 'reseller');
    }

    private function websiteProductIds(): array
    {
        $raw = (string) (
            $this->settings['website_development_product_ids']
            ?? $this->whmcs->checkoutConfig()['website_package']['product_ids']
            ?? ''
        );
        $ids = [];
        foreach (preg_split('/[,\s]+/', $raw) ?: [] as $value) {
            $id = (int) trim($value);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $fallback = (int) ($this->whmcs->checkoutConfig()['website_package']['pid'] ?? 0);
        if ($fallback > 0) {
            $ids[] = $fallback;
        }

        return array_values(array_unique($ids));
    }

    private function productId(array $service): int
    {
        foreach (['pid', 'productid', 'product_id', 'packageid'] as $key) {
            $id = (int) ($service[$key] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
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

    private function log(string $message, array $context = []): void
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        unset($context['password'], $context['api_token'], $context['secret']);
        @file_put_contents(
            $dir . '/account-sync.log',
            '[' . date('c') . '] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }
}
