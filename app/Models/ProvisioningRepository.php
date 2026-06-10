<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class ProvisioningRepository
{
    public const WEBSITE_STATUSES = [
        'Payment Pending',
        'Payment Confirmed',
        'Awaiting Client Details',
        'Content Collection',
        'Design Started',
        'Development Started',
        'Review Stage',
        'Revisions',
        'Completed',
        'Delivered',
    ];

    private PDO $db;
    private ?array $customerProvisioningColumns = null;
    private ?bool $provisioningLogsAvailable = null;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function ensureOrderRecords(array $order, string $whmPackage = ''): void
    {
        $type = $this->orderType($order);
        if ($type === '') {
            return;
        }

        $domain = trim((string) ($order['selected_domain'] ?? ''));
        $paymentStatus = (string) ($order['payment_status'] ?? 'pending');

        if ($domain !== '' && in_array($type, ['domain', 'bundle', 'website'], true)) {
            $this->upsertItem($order, [
                'item_type' => 'domain',
                'item_key' => strtolower($domain),
                'display_name' => 'Domain Registration',
                'domain_name' => $domain,
                'payment_status' => $paymentStatus,
                'domain_registration_status' => $paymentStatus === 'paid' ? 'Processing' : 'Payment Pending',
            ]);
        }

        if (in_array($type, ['hosting', 'bundle'], true) || ($type === 'website' && $whmPackage !== '')) {
            $slug = trim((string) ($order['hosting_plan_slug'] ?? ''));
            $itemKey = strtolower(($slug !== '' ? $slug : ($type === 'website' ? 'website-hosting' : 'hosting')) . ':' . ($domain !== '' ? $domain : 'no-domain'));
            $this->upsertItem($order, [
                'item_type' => 'hosting',
                'item_key' => $itemKey,
                'display_name' => trim((string) ($order['package_label'] ?? '')) ?: ($type === 'website' ? 'Website Hosting' : 'Hosting Package'),
                'domain_name' => $domain,
                'hosting_plan_slug' => $slug,
                'whm_package' => $whmPackage,
                'billing_cycle' => (string) ($order['billing_cycle'] ?? ''),
                'payment_status' => $paymentStatus,
                'hosting_setup_status' => $paymentStatus === 'paid' ? 'Setup in Progress' : 'Payment Pending',
            ]);
        }

        if ($type === 'website') {
            $this->ensureWebsiteProject($order);
        }
    }

    public function markPaymentConfirmed(array $order, string $stripeReference = ''): void
    {
        $orderId = (int) ($order['id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE customer_provisioning_items
             SET payment_status = "paid",
                 stripe_reference = COALESCE(NULLIF(:stripe_reference, ""), stripe_reference),
                 provisioning_status = CASE
                    WHEN provisioning_status = "pending_payment" THEN "processing"
                    ELSE provisioning_status
                 END,
                 domain_registration_status = CASE
                    WHEN item_type = "domain" AND domain_registration_status IN ("", "Payment Pending") THEN "Processing"
                    ELSE domain_registration_status
                 END,
                 hosting_setup_status = CASE
                    WHEN item_type = "hosting" AND hosting_setup_status IN ("", "Payment Pending") THEN "Setup in Progress"
                    ELSE hosting_setup_status
                 END,
                 updated_at = NOW()
             WHERE customer_order_id = :order_id'
        );
        $stmt->execute(['order_id' => $orderId, 'stripe_reference' => $stripeReference]);

        $stmt = $this->db->prepare(
            'UPDATE website_projects
             SET payment_status = "Payment Confirmed",
                 project_status = CASE
                    WHEN project_status = "Payment Pending" THEN "Payment Confirmed"
                    ELSE project_status
                 END,
                 onboarding_status = CASE
                    WHEN onboarding_status = "" OR onboarding_status IS NULL OR onboarding_status = "Payment Pending" THEN "Awaiting Client Details"
                    ELSE onboarding_status
                 END,
                 estimated_next_step = CASE
                    WHEN estimated_next_step = "" OR estimated_next_step IS NULL THEN "Our team will contact you to collect the project details."
                    ELSE estimated_next_step
                 END,
                 updated_at = NOW()
             WHERE customer_order_id = :order_id'
        );
        $stmt->execute(['order_id' => $orderId]);

        $this->markOrderAggregate($orderId, [
            'provisioning_status' => 'processing',
            'website_project_status' => $this->orderType($order) === 'website' ? 'Payment Confirmed' : null,
            'provisioning_last_error' => null,
        ]);
    }

    public function noteProvisioningAttempt(int $orderId, string $itemType): void
    {
        if ($orderId <= 0 || !in_array($itemType, ['domain', 'hosting'], true)) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE customer_provisioning_items
             SET provision_attempts = provision_attempts + 1,
                 last_attempt_at = NOW(),
                 updated_at = NOW()
             WHERE customer_order_id = :order_id AND item_type = :item_type'
        );
        $stmt->execute(['order_id' => $orderId, 'item_type' => $itemType]);
    }

    public function completedItemExists(int $orderId, string $itemType): bool
    {
        if ($orderId <= 0 || !in_array($itemType, ['domain', 'hosting'], true)) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM customer_provisioning_items
             WHERE customer_order_id = :order_id
               AND item_type = :item_type
               AND provisioning_status = "active"'
        );
        $stmt->execute(['order_id' => $orderId, 'item_type' => $itemType]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function itemNeedsAutomaticRetry(int $orderId, string $itemType): bool
    {
        if ($orderId <= 0 || !in_array($itemType, ['domain', 'hosting'], true)) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT provisioning_status, provision_attempts
             FROM customer_provisioning_items
             WHERE customer_order_id = :order_id AND item_type = :item_type
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute(['order_id' => $orderId, 'item_type' => $itemType]);
        $item = $stmt->fetch();
        if (!$item) {
            return true;
        }

        $status = (string) ($item['provisioning_status'] ?? '');
        if (in_array($status, ['active', 'action_required'], true)) {
            return false;
        }

        return (int) ($item['provision_attempts'] ?? 0) < 2;
    }

    public function markDomainFromWhmcs(array $order, array $domain, array $nameservers = []): void
    {
        $orderId = (int) ($order['id'] ?? 0);
        $domainName = strtolower(trim((string) ($domain['domainname'] ?? $domain['domain'] ?? $order['selected_domain'] ?? '')));
        if ($orderId <= 0 || $domainName === '') {
            return;
        }

        [$provisioningStatus, $registrationStatus] = $this->domainStatuses((string) ($domain['status'] ?? ''));
        $stmt = $this->db->prepare(
            'UPDATE customer_provisioning_items
             SET whmcs_domain_id = :whmcs_domain_id,
                 payment_status = "paid",
                 provisioning_status = :provisioning_status,
                 domain_registration_status = :domain_registration_status,
                 registration_date = :registration_date,
                 expiry_date = :expiry_date,
                 renewal_date = :renewal_date,
                 renewal_amount = :renewal_amount,
                 nameservers_json = :nameservers_json,
                 setup_issue_public = CASE WHEN :provisioning_status = "active" THEN NULL ELSE setup_issue_public END,
                 provisioned_at = CASE WHEN :provisioning_status = "active" THEN COALESCE(provisioned_at, NOW()) ELSE provisioned_at END,
                 updated_at = NOW()
             WHERE customer_order_id = :order_id
               AND item_type = "domain"
               AND LOWER(domain_name) = :domain_name'
        );
        $stmt->execute([
            'order_id' => $orderId,
            'domain_name' => $domainName,
            'whmcs_domain_id' => $this->firstInt($domain, ['id', 'domainid']),
            'provisioning_status' => $provisioningStatus,
            'domain_registration_status' => $registrationStatus,
            'registration_date' => $this->dateOrNull($domain['registrationdate'] ?? $domain['regdate'] ?? null),
            'expiry_date' => $this->dateOrNull($domain['expirydate'] ?? null),
            'renewal_date' => $this->dateOrNull($domain['nextduedate'] ?? $domain['nextdue'] ?? null),
            'renewal_amount' => $this->amountOrNull($domain['recurringamount'] ?? $domain['renewalamount'] ?? null),
            'nameservers_json' => $nameservers ? json_encode(array_values($nameservers), JSON_UNESCAPED_SLASHES) : null,
        ]);

        $this->markOrderAggregate($orderId, [
            'provisioning_status' => $provisioningStatus === 'active' ? 'completed' : 'processing',
            'domain_registration_status' => $registrationStatus,
            'provisioned_at' => $provisioningStatus === 'active' ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public function markHostingFromWhmcs(array $order, array $service, string $whmPackage = ''): void
    {
        $orderId = (int) ($order['id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        [$provisioningStatus, $hostingStatus] = $this->hostingStatuses((string) ($service['status'] ?? ''));
        $serviceId = $this->firstInt($service, ['id', 'serviceid', 'service_id', 'hostingid', 'relid']);
        $stmt = $this->db->prepare(
            'UPDATE customer_provisioning_items
             SET whmcs_service_id = COALESCE(NULLIF(:whmcs_service_id, 0), whmcs_service_id),
                 payment_status = "paid",
                 provisioning_status = :provisioning_status,
                 hosting_setup_status = :hosting_setup_status,
                 display_name = COALESCE(NULLIF(:display_name, ""), display_name),
                 domain_name = COALESCE(NULLIF(:domain_name, ""), domain_name),
                 whm_package = COALESCE(NULLIF(:whm_package, ""), whm_package),
                 billing_cycle = COALESCE(NULLIF(:billing_cycle, ""), billing_cycle),
                 start_date = :start_date,
                 next_due_date = :next_due_date,
                 renewal_amount = :renewal_amount,
                 setup_issue_public = CASE WHEN :provisioning_status = "active" THEN NULL ELSE setup_issue_public END,
                 provisioned_at = CASE WHEN :provisioning_status = "active" THEN COALESCE(provisioned_at, NOW()) ELSE provisioned_at END,
                 updated_at = NOW()
             WHERE customer_order_id = :order_id AND item_type = "hosting"'
        );
        $stmt->execute([
            'order_id' => $orderId,
            'whmcs_service_id' => $serviceId,
            'provisioning_status' => $provisioningStatus,
            'hosting_setup_status' => $hostingStatus,
            'display_name' => (string) ($service['name'] ?? $service['productname'] ?? ''),
            'domain_name' => (string) ($service['domain'] ?? ''),
            'whm_package' => $whmPackage,
            'billing_cycle' => (string) ($service['billingcycle'] ?? ''),
            'start_date' => $this->dateOrNull($service['regdate'] ?? $service['registrationdate'] ?? null),
            'next_due_date' => $this->dateOrNull($service['nextduedate'] ?? null),
            'renewal_amount' => $this->amountOrNull($service['recurringamount'] ?? $service['amount'] ?? null),
        ]);

        $this->markOrderAggregate($orderId, [
            'provisioning_status' => $provisioningStatus === 'active' ? 'completed' : 'processing',
            'hosting_setup_status' => $hostingStatus,
            'whm_package' => $whmPackage !== '' ? $whmPackage : null,
            'provisioned_at' => $provisioningStatus === 'active' ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public function markHostingProvisioned(array $order, int $serviceId, string $whmPackage = ''): void
    {
        $orderId = (int) ($order['id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE customer_provisioning_items
             SET whmcs_service_id = COALESCE(NULLIF(:service_id, 0), whmcs_service_id),
                 payment_status = "paid",
                 provisioning_status = "active",
                 hosting_setup_status = "Setup Completed",
                 whm_package = COALESCE(NULLIF(:whm_package, ""), whm_package),
                 setup_issue_public = NULL,
                 setup_issue_internal = NULL,
                 provisioned_at = COALESCE(provisioned_at, NOW()),
                 updated_at = NOW()
             WHERE customer_order_id = :order_id AND item_type = "hosting"'
        );
        $stmt->execute(['order_id' => $orderId, 'service_id' => $serviceId, 'whm_package' => $whmPackage]);

        $this->markOrderAggregate($orderId, [
            'provisioning_status' => 'completed',
            'hosting_setup_status' => 'Setup Completed',
            'whm_package' => $whmPackage !== '' ? $whmPackage : null,
            'provisioned_at' => date('Y-m-d H:i:s'),
            'provisioning_last_error' => null,
        ]);
    }

    public function markItemIssue(array $order, string $itemType, string $publicMessage, string $internalMessage = ''): void
    {
        $orderId = (int) ($order['id'] ?? 0);
        if ($orderId <= 0 || !in_array($itemType, ['domain', 'hosting'], true)) {
            return;
        }

        $field = $itemType === 'domain' ? 'domain_registration_status' : 'hosting_setup_status';
        $stmt = $this->db->prepare(
            'UPDATE customer_provisioning_items
             SET provisioning_status = "action_required",
                 ' . $field . ' = "Action Required",
                 setup_issue_public = :public_message,
                 setup_issue_internal = :internal_message,
                 updated_at = NOW()
             WHERE customer_order_id = :order_id AND item_type = :item_type'
        );
        $stmt->execute([
            'order_id' => $orderId,
            'item_type' => $itemType,
            'public_message' => substr($publicMessage, 0, 255),
            'internal_message' => $internalMessage,
        ]);

        $this->markOrderAggregate($orderId, [
            'provisioning_status' => 'action_required',
            $field => 'Action Required',
            'provisioning_last_error' => $internalMessage !== '' ? $internalMessage : $publicMessage,
        ]);
    }

    public function markItemProcessing(array $order, string $itemType, string $statusLabel): void
    {
        $orderId = (int) ($order['id'] ?? 0);
        if ($orderId <= 0 || !in_array($itemType, ['domain', 'hosting'], true)) {
            return;
        }

        $field = $itemType === 'domain' ? 'domain_registration_status' : 'hosting_setup_status';
        $stmt = $this->db->prepare(
            'UPDATE customer_provisioning_items
             SET provisioning_status = "processing",
                 ' . $field . ' = :status_label,
                 updated_at = NOW()
             WHERE customer_order_id = :order_id AND item_type = :item_type
               AND provisioning_status != "active"'
        );
        $stmt->execute([
            'order_id' => $orderId,
            'item_type' => $itemType,
            'status_label' => substr($statusLabel, 0, 80),
        ]);
    }

    public function upsertHostingServiceForAccount(array $account, array $service, string $whmPackage = ''): void
    {
        $clientId = (int) ($account['whmcs_client_id'] ?? 0);
        $serviceId = $this->firstInt($service, ['id', 'serviceid', 'service_id', 'hostingid', 'relid']);
        if ($clientId <= 0 || $serviceId <= 0) {
            return;
        }

        [$provisioningStatus, $hostingStatus] = $this->hostingStatuses((string) ($service['status'] ?? ''));
        $itemKey = 'service:' . $serviceId;
        $stmt = $this->db->prepare(
            'INSERT INTO customer_provisioning_items
             (customer_order_id, customer_user_id, whmcs_client_id, whmcs_order_id, whmcs_invoice_id,
              item_type, item_key, display_name, domain_name, hosting_plan_slug, whm_package, billing_cycle,
              payment_status, provisioning_status, hosting_setup_status, whmcs_service_id,
              start_date, next_due_date, renewal_amount, provisioned_at, created_at, updated_at)
             VALUES
             (NULL, :customer_user_id, :whmcs_client_id, :whmcs_order_id, :whmcs_invoice_id,
              "hosting", :item_key, :display_name, :domain_name, :hosting_plan_slug, :whm_package, :billing_cycle,
              :payment_status, :provisioning_status, :hosting_setup_status, :whmcs_service_id,
              :start_date, :next_due_date, :renewal_amount, :provisioned_at, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
              customer_user_id = VALUES(customer_user_id),
              whmcs_order_id = COALESCE(VALUES(whmcs_order_id), whmcs_order_id),
              whmcs_invoice_id = IF(VALUES(whmcs_invoice_id) > 0, VALUES(whmcs_invoice_id), whmcs_invoice_id),
              display_name = VALUES(display_name),
              domain_name = VALUES(domain_name),
              hosting_plan_slug = COALESCE(VALUES(hosting_plan_slug), hosting_plan_slug),
              whm_package = COALESCE(NULLIF(VALUES(whm_package), ""), whm_package),
              billing_cycle = COALESCE(NULLIF(VALUES(billing_cycle), ""), billing_cycle),
              payment_status = VALUES(payment_status),
              provisioning_status = VALUES(provisioning_status),
              hosting_setup_status = VALUES(hosting_setup_status),
              whmcs_service_id = VALUES(whmcs_service_id),
              start_date = VALUES(start_date),
              next_due_date = VALUES(next_due_date),
              renewal_amount = VALUES(renewal_amount),
              provisioned_at = COALESCE(provisioned_at, VALUES(provisioned_at)),
              updated_at = NOW()'
        );
        $stmt->execute([
            'customer_user_id' => $this->nullableInt($account['id'] ?? null),
            'whmcs_client_id' => $clientId,
            'whmcs_order_id' => $this->nullableInt($service['orderid'] ?? $service['order_id'] ?? null),
            'whmcs_invoice_id' => (int) ($service['invoiceid'] ?? $service['invoice_id'] ?? 0),
            'item_key' => $itemKey,
            'display_name' => (string) ($service['name'] ?? $service['productname'] ?? 'Hosting Package'),
            'domain_name' => (string) ($service['domain'] ?? ''),
            'hosting_plan_slug' => (string) ($service['slug'] ?? ''),
            'whm_package' => $whmPackage,
            'billing_cycle' => (string) ($service['billingcycle'] ?? ''),
            'payment_status' => $provisioningStatus === 'active' ? 'paid' : 'processing',
            'provisioning_status' => $provisioningStatus,
            'hosting_setup_status' => $hostingStatus,
            'whmcs_service_id' => $serviceId,
            'start_date' => $this->dateOrNull($service['regdate'] ?? $service['registrationdate'] ?? null),
            'next_due_date' => $this->dateOrNull($service['nextduedate'] ?? null),
            'renewal_amount' => $this->amountOrNull($service['recurringamount'] ?? $service['amount'] ?? null),
            'provisioned_at' => $provisioningStatus === 'active' ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public function upsertWebsiteProjectForAccount(array $account, array $service): void
    {
        $clientId = (int) ($account['whmcs_client_id'] ?? 0);
        $serviceId = $this->firstInt($service, ['id', 'serviceid', 'service_id', 'hostingid', 'relid']);
        if ($clientId <= 0 || $serviceId <= 0) {
            return;
        }

        $status = strtolower((string) ($service['status'] ?? ''));
        $paid = in_array($status, ['active', 'completed'], true);
        $stmt = $this->db->prepare(
            'INSERT INTO website_projects
             (customer_order_id, customer_user_id, whmcs_client_id, whmcs_order_id, whmcs_invoice_id,
              whmcs_service_id, whmcs_product_id, package_name, domain_name, hosting_plan_slug,
              payment_status, project_status, onboarding_status, estimated_next_step, purchase_date, created_at, updated_at)
             VALUES
             (NULL, :customer_user_id, :whmcs_client_id, :whmcs_order_id, :whmcs_invoice_id,
              :whmcs_service_id, :whmcs_product_id, :package_name, :domain_name, :hosting_plan_slug,
              :payment_status, :project_status, :onboarding_status, :estimated_next_step, :purchase_date, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
              customer_user_id = VALUES(customer_user_id),
              whmcs_order_id = COALESCE(VALUES(whmcs_order_id), whmcs_order_id),
              whmcs_invoice_id = IF(VALUES(whmcs_invoice_id) > 0, VALUES(whmcs_invoice_id), whmcs_invoice_id),
              whmcs_product_id = COALESCE(VALUES(whmcs_product_id), whmcs_product_id),
              package_name = VALUES(package_name),
              domain_name = COALESCE(VALUES(domain_name), domain_name),
              payment_status = VALUES(payment_status),
              project_status = CASE
                WHEN project_status IN ("Payment Pending", "Payment Confirmed") THEN VALUES(project_status)
                ELSE project_status
              END,
              onboarding_status = CASE
                WHEN onboarding_status IN ("", "Payment Pending") THEN VALUES(onboarding_status)
                ELSE onboarding_status
              END,
              estimated_next_step = COALESCE(estimated_next_step, VALUES(estimated_next_step)),
              purchase_date = COALESCE(purchase_date, VALUES(purchase_date)),
              updated_at = NOW()'
        );
        $stmt->execute([
            'customer_user_id' => $this->nullableInt($account['id'] ?? null),
            'whmcs_client_id' => $clientId,
            'whmcs_order_id' => $this->nullableInt($service['orderid'] ?? $service['order_id'] ?? null),
            'whmcs_invoice_id' => (int) ($service['invoiceid'] ?? $service['invoice_id'] ?? 0),
            'whmcs_service_id' => $serviceId,
            'whmcs_product_id' => $this->firstInt($service, ['pid', 'productid', 'product_id']),
            'package_name' => (string) ($service['name'] ?? $service['productname'] ?? 'Website Development'),
            'domain_name' => trim((string) ($service['domain'] ?? '')) ?: null,
            'hosting_plan_slug' => null,
            'payment_status' => $paid ? 'Payment Confirmed' : 'Payment Pending',
            'project_status' => $paid ? 'Payment Confirmed' : 'Payment Pending',
            'onboarding_status' => $paid ? 'Awaiting Client Details' : 'Payment Pending',
            'estimated_next_step' => $paid ? 'Our team will contact you to collect the project details.' : 'Complete payment to start the website project.',
            'purchase_date' => $this->dateOrNull($service['regdate'] ?? $service['registrationdate'] ?? null),
        ]);
    }

    public function itemsForAccount(?int $customerId, ?int $whmcsClientId, ?string $itemType = null): array
    {
        $clauses = [];
        $params = [];
        if ($customerId !== null && $customerId > 0) {
            $clauses[] = 'customer_user_id = :customer_id';
            $params['customer_id'] = $customerId;
        }
        if ($whmcsClientId !== null && $whmcsClientId > 0) {
            $clauses[] = 'whmcs_client_id = :whmcs_client_id';
            $params['whmcs_client_id'] = $whmcsClientId;
        }
        if (!$clauses) {
            return [];
        }

        $typeSql = '';
        if ($itemType !== null && in_array($itemType, ['domain', 'hosting'], true)) {
            $typeSql = ' AND item_type = :item_type';
            $params['item_type'] = $itemType;
        }

        $stmt = $this->db->prepare(
            'SELECT *
             FROM customer_provisioning_items
             WHERE (' . implode(' OR ', $clauses) . ')' . $typeSql . '
             ORDER BY COALESCE(provisioned_at, updated_at, created_at) DESC, id DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function websiteProjectsForAccount(?int $customerId, ?int $whmcsClientId): array
    {
        $clauses = [];
        $params = [];
        if ($customerId !== null && $customerId > 0) {
            $clauses[] = 'customer_user_id = :customer_id';
            $params['customer_id'] = $customerId;
        }
        if ($whmcsClientId !== null && $whmcsClientId > 0) {
            $clauses[] = 'whmcs_client_id = :whmcs_client_id';
            $params['whmcs_client_id'] = $whmcsClientId;
        }
        if (!$clauses) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT *
             FROM website_projects
             WHERE ' . implode(' OR ', $clauses) . '
             ORDER BY COALESCE(delivered_at, completed_at, updated_at, created_at) DESC, id DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function websiteProjectsForAdmin(): array
    {
        return $this->db->query(
            'SELECT p.*, u.email AS customer_email, CONCAT(u.first_name, " ", u.last_name) AS customer_name
             FROM website_projects p
             LEFT JOIN customer_users u ON u.id = p.customer_user_id
             ORDER BY COALESCE(p.updated_at, p.created_at) DESC, p.id DESC'
        )->fetchAll();
    }

    public function websiteProject(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, u.email AS customer_email, CONCAT(u.first_name, " ", u.last_name) AS customer_name
             FROM website_projects p
             LEFT JOIN customer_users u ON u.id = p.customer_user_id
             WHERE p.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $project = $stmt->fetch();
        return $project ?: null;
    }

    public function updateWebsiteProject(int $id, array $data): void
    {
        $status = (string) ($data['project_status'] ?? 'Payment Confirmed');
        if (!in_array($status, self::WEBSITE_STATUSES, true)) {
            $status = 'Payment Confirmed';
        }

        $completedAt = $this->dateTimeOrNull($data['completed_at'] ?? null);
        $deliveredAt = $this->dateTimeOrNull($data['delivered_at'] ?? null);
        if ($status === 'Completed' && $completedAt === null) {
            $completedAt = date('Y-m-d H:i:s');
        }
        if ($status === 'Delivered') {
            $deliveredAt ??= date('Y-m-d H:i:s');
            $completedAt ??= $deliveredAt;
        }

        $stmt = $this->db->prepare(
            'UPDATE website_projects
             SET project_status = :project_status,
                 onboarding_status = :onboarding_status,
                 internal_notes = :internal_notes,
                 customer_note = :customer_note,
                 estimated_next_step = :estimated_next_step,
                 completed_at = :completed_at,
                 delivered_at = :delivered_at,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'project_status' => $status,
            'onboarding_status' => substr((string) ($data['onboarding_status'] ?? ''), 0, 120),
            'internal_notes' => (string) ($data['internal_notes'] ?? ''),
            'customer_note' => (string) ($data['customer_note'] ?? ''),
            'estimated_next_step' => substr((string) ($data['estimated_next_step'] ?? ''), 0, 255),
            'completed_at' => $completedAt,
            'delivered_at' => $deliveredAt,
        ]);

        $project = $this->websiteProject($id);
        if ($project && !empty($project['customer_order_id'])) {
            $this->markOrderAggregate((int) $project['customer_order_id'], [
                'website_project_status' => $status,
            ]);
        }
    }

    public function markHostingAccountProvisioned(array $order, int $serviceId, string $whmPackage, string $username = '', string $serverIp = '', string $encryptedPassword = ''): void
    {
        $orderId = (int) ($order['id'] ?? $order['customer_order_id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        $columns = $this->customerProvisioningColumns();
        $set = [
            'whmcs_service_id = COALESCE(NULLIF(:service_id, 0), whmcs_service_id)',
            'payment_status = "paid"',
            'provisioning_status = "active"',
            'hosting_setup_status = "Setup Completed"',
            'whm_package = COALESCE(NULLIF(:whm_package, ""), whm_package)',
            'setup_issue_public = NULL',
            'setup_issue_internal = NULL',
            'provisioned_at = COALESCE(provisioned_at, NOW())',
            'updated_at = NOW()',
        ];
        $params = [
            'order_id' => $orderId,
            'service_id' => $serviceId,
            'whm_package' => $whmPackage,
        ];

        foreach ([
            'cpanel_username' => substr($username, 0, 16),
            'server_ip' => substr($serverIp, 0, 45),
            'cpanel_password_encrypted' => $encryptedPassword,
        ] as $field => $value) {
            if (!isset($columns[$field]) || $value === '') {
                continue;
            }
            $set[] = $field . ' = COALESCE(NULLIF(:' . $field . ', ""), ' . $field . ')';
            $params[$field] = $value;
        }

        $stmt = $this->db->prepare(
            'UPDATE customer_provisioning_items
             SET ' . implode(', ', $set) . '
             WHERE customer_order_id = :order_id AND item_type = "hosting"'
        );
        $stmt->execute($params);

        $aggregate = [
            'provisioning_status' => 'completed',
            'hosting_setup_status' => 'Setup Completed',
            'whm_package' => $whmPackage !== '' ? $whmPackage : null,
            'provisioned_at' => date('Y-m-d H:i:s'),
            'provisioning_last_error' => null,
        ];
        $this->markOrderAggregate($orderId, $aggregate);
    }

    public function markCpanelPasswordSent(int $orderId, string $domain): void
    {
        if ($orderId <= 0 || !isset($this->customerProvisioningColumns()['cpanel_password_sent_at'])) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE customer_provisioning_items
             SET cpanel_password_sent_at = COALESCE(cpanel_password_sent_at, NOW()), updated_at = NOW()
             WHERE customer_order_id = :order_id AND item_type = "hosting"
               AND (:domain = "" OR LOWER(domain_name) = LOWER(:domain))'
        );
        $stmt->execute(['order_id' => $orderId, 'domain' => $domain]);
    }

    public function markCloudflareProvisioned(array $order, string $domain, string $zoneId, array $nameservers, array $records = []): void
    {
        $orderId = (int) ($order['id'] ?? $order['customer_order_id'] ?? 0);
        $domain = strtolower(trim($domain));
        if ($orderId <= 0 || $domain === '') {
            return;
        }

        $columns = $this->customerProvisioningColumns();
        $fields = [
            'cloudflare_zone_id' => $zoneId,
            'cloudflare_status' => 'Active',
            'cloudflare_nameservers_json' => json_encode(array_values($nameservers), JSON_UNESCAPED_SLASHES),
            'dns_records_json' => json_encode(array_values($records), JSON_UNESCAPED_SLASHES),
            'cloudflare_last_error' => null,
        ];
        $set = ['updated_at = NOW()'];
        $params = ['order_id' => $orderId, 'domain' => $domain];
        foreach ($fields as $field => $value) {
            if (!isset($columns[$field])) {
                continue;
            }
            $set[] = $field . ' = :' . $field;
            $params[$field] = is_string($value) ? $value : $value;
        }
        if (isset($columns['dns_attempts'])) {
            $set[] = 'dns_attempts = dns_attempts + 1';
        }
        if (isset($columns['dns_last_attempt_at'])) {
            $set[] = 'dns_last_attempt_at = NOW()';
        }
        if (isset($columns['nameservers_json']) && $nameservers) {
            $set[] = 'nameservers_json = COALESCE(NULLIF(:nameservers_json, "[]"), nameservers_json)';
            $params['nameservers_json'] = json_encode(array_values($nameservers), JSON_UNESCAPED_SLASHES);
        }

        if (count($set) <= 1) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE customer_provisioning_items
             SET ' . implode(', ', $set) . '
             WHERE customer_order_id = :order_id
               AND LOWER(domain_name) = :domain
               AND item_type IN ("domain", "hosting")'
        );
        $stmt->execute($params);
    }

    public function markDnsIssue(array $order, string $domain, string $message): void
    {
        $orderId = (int) ($order['id'] ?? $order['customer_order_id'] ?? 0);
        $domain = strtolower(trim($domain));
        if ($orderId <= 0 || $domain === '') {
            return;
        }

        $columns = $this->customerProvisioningColumns();
        $set = ['updated_at = NOW()'];
        $params = [
            'order_id' => $orderId,
            'domain' => $domain,
        ];

        if (isset($columns['cloudflare_status'])) {
            $set[] = 'cloudflare_status = "Failed"';
        }
        if (isset($columns['cloudflare_last_error'])) {
            $set[] = 'cloudflare_last_error = :message';
            $params['message'] = substr($message, 0, 1000);
        }
        if (isset($columns['dns_attempts'])) {
            $set[] = 'dns_attempts = dns_attempts + 1';
        }
        if (isset($columns['dns_last_attempt_at'])) {
            $set[] = 'dns_last_attempt_at = NOW()';
        }

        if (count($set) <= 1) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE customer_provisioning_items
             SET ' . implode(', ', $set) . '
             WHERE customer_order_id = :order_id
               AND LOWER(domain_name) = :domain
               AND item_type IN ("domain", "hosting")'
        );
        $stmt->execute($params);
    }

    public function itemWithOrder(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT i.*,
                    o.public_token AS order_public_token,
                    o.order_type AS order_type,
                    o.selected_domain AS selected_domain,
                    o.hosting_plan_slug AS order_hosting_plan_slug,
                    o.package_label AS order_package_label,
                    o.billing_cycle AS order_billing_cycle,
                    o.payment_status AS order_payment_status,
                    o.stripe_payment_reference AS order_stripe_payment_reference,
                    o.paid_at AS order_paid_at
             FROM customer_provisioning_items i
             LEFT JOIN customer_orders o ON o.id = i.customer_order_id
             WHERE i.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch();
        return $item ?: null;
    }

    public function resetItemForRetry(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE customer_provisioning_items
             SET provisioning_status = "processing",
                 hosting_setup_status = CASE WHEN item_type = "hosting" THEN "Setup in Progress" ELSE hosting_setup_status END,
                 domain_registration_status = CASE WHEN item_type = "domain" THEN "Processing" ELSE domain_registration_status END,
                 setup_issue_public = NULL,
                 setup_issue_internal = NULL,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public function itemsForAdmin(int $limit = 200): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.*, u.email AS customer_email, CONCAT(u.first_name, " ", u.last_name) AS customer_name
             FROM customer_provisioning_items i
             LEFT JOIN customer_users u ON u.id = i.customer_user_id
             ORDER BY COALESCE(i.updated_at, i.created_at) DESC, i.id DESC
             LIMIT ' . max(1, min(500, $limit))
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function provisioningLogs(int $limit = 200, ?int $itemId = null): array
    {
        if (!$this->provisioningLogsAvailable()) {
            return [];
        }

        $params = [];
        $where = '';
        if ($itemId !== null && $itemId > 0) {
            $where = 'WHERE provisioning_item_id = :item_id';
            $params['item_id'] = $itemId;
        }

        $stmt = $this->db->prepare(
            'SELECT *
             FROM provisioning_logs
             ' . $where . '
             ORDER BY id DESC
             LIMIT ' . max(1, min(500, $limit))
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function logStep(?int $orderId, ?int $itemId, string $itemType, string $event, string $status, string $message, array $context = []): void
    {
        $safeContext = $context;
        unset(
            $safeContext['password'],
            $safeContext['cpanel_password'],
            $safeContext['api_token'],
            $safeContext['secret'],
            $safeContext['stripe_secret'],
            $safeContext['whm_api_token'],
            $safeContext['cloudflare_api_token']
        );

        if ($this->provisioningLogsAvailable()) {
            try {
                $stmt = $this->db->prepare(
                    'INSERT INTO provisioning_logs
                     (customer_order_id, provisioning_item_id, item_type, event, status, message, context_json, created_at)
                     VALUES
                     (:order_id, :item_id, :item_type, :event, :status, :message, :context_json, NOW())'
                );
                $stmt->execute([
                    'order_id' => $orderId ?: null,
                    'item_id' => $itemId ?: null,
                    'item_type' => in_array($itemType, ['domain', 'hosting', 'payment', 'dns', 'whmcs'], true) ? $itemType : 'whmcs',
                    'event' => substr($event, 0, 120),
                    'status' => substr($status, 0, 40),
                    'message' => substr($message, 0, 1000),
                    'context_json' => json_encode($safeContext, JSON_UNESCAPED_SLASHES),
                ]);
                return;
            } catch (\Throwable) {
                $this->provisioningLogsAvailable = false;
            }
        }

        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents(
            $dir . '/provisioning.log',
            '[' . date('c') . '] ' . $event . ' ' . $status . ' ' . $message . ' ' . json_encode($safeContext, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }

    private function ensureWebsiteProject(array $order): void
    {
        $orderId = (int) ($order['id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        $paymentStatus = (string) ($order['payment_status'] ?? 'pending');
        $projectStatus = $paymentStatus === 'paid' ? 'Payment Confirmed' : 'Payment Pending';
        $stmt = $this->db->prepare(
            'INSERT INTO website_projects
             (customer_order_id, customer_user_id, whmcs_client_id, whmcs_order_id, whmcs_invoice_id,
              package_name, domain_name, hosting_plan_slug, payment_status, project_status,
              onboarding_status, estimated_next_step, purchase_date, created_at, updated_at)
             VALUES
             (:customer_order_id, :customer_user_id, :whmcs_client_id, :whmcs_order_id, :whmcs_invoice_id,
              :package_name, :domain_name, :hosting_plan_slug, :payment_status, :project_status,
              :onboarding_status, :estimated_next_step, :purchase_date, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
              customer_user_id = VALUES(customer_user_id),
              whmcs_client_id = VALUES(whmcs_client_id),
              whmcs_order_id = VALUES(whmcs_order_id),
              whmcs_invoice_id = VALUES(whmcs_invoice_id),
              package_name = VALUES(package_name),
              domain_name = VALUES(domain_name),
              hosting_plan_slug = VALUES(hosting_plan_slug),
              payment_status = IF(payment_status = "Payment Confirmed", payment_status, VALUES(payment_status)),
              project_status = IF(project_status = "Payment Pending", VALUES(project_status), project_status),
              updated_at = NOW()'
        );
        $stmt->execute([
            'customer_order_id' => $orderId,
            'customer_user_id' => $this->nullableInt($order['customer_user_id'] ?? null),
            'whmcs_client_id' => (int) ($order['whmcs_client_id'] ?? 0),
            'whmcs_order_id' => $this->nullableInt($order['whmcs_order_id'] ?? null),
            'whmcs_invoice_id' => (int) ($order['whmcs_invoice_id'] ?? 0),
            'package_name' => trim((string) ($order['package_label'] ?? '')) ?: 'Bespoke Website Development',
            'domain_name' => trim((string) ($order['selected_domain'] ?? '')) ?: null,
            'hosting_plan_slug' => trim((string) ($order['hosting_plan_slug'] ?? '')) ?: null,
            'payment_status' => $paymentStatus === 'paid' ? 'Payment Confirmed' : 'Payment Pending',
            'project_status' => $projectStatus,
            'onboarding_status' => $paymentStatus === 'paid' ? 'Awaiting Client Details' : 'Payment Pending',
            'estimated_next_step' => $paymentStatus === 'paid'
                ? 'Our team will contact you to collect the project details.'
                : 'Complete payment to start the website project.',
            'purchase_date' => $this->dateOrNull($order['paid_at'] ?? $order['created_at'] ?? null),
        ]);

        $this->markOrderAggregate($orderId, [
            'website_project_status' => $projectStatus,
        ]);
    }

    private function upsertItem(array $order, array $item): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO customer_provisioning_items
             (customer_order_id, customer_user_id, whmcs_client_id, whmcs_order_id, whmcs_invoice_id,
              item_type, item_key, display_name, domain_name, hosting_plan_slug, whm_package, billing_cycle,
              payment_status, provisioning_status, domain_registration_status, hosting_setup_status,
              stripe_reference, created_at, updated_at)
             VALUES
             (:customer_order_id, :customer_user_id, :whmcs_client_id, :whmcs_order_id, :whmcs_invoice_id,
              :item_type, :item_key, :display_name, :domain_name, :hosting_plan_slug, :whm_package, :billing_cycle,
              :payment_status, :provisioning_status, :domain_registration_status, :hosting_setup_status,
              :stripe_reference, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
              customer_user_id = VALUES(customer_user_id),
              whmcs_client_id = VALUES(whmcs_client_id),
              whmcs_order_id = VALUES(whmcs_order_id),
              whmcs_invoice_id = VALUES(whmcs_invoice_id),
              display_name = VALUES(display_name),
              domain_name = COALESCE(VALUES(domain_name), domain_name),
              hosting_plan_slug = COALESCE(VALUES(hosting_plan_slug), hosting_plan_slug),
              whm_package = COALESCE(NULLIF(VALUES(whm_package), ""), whm_package),
              billing_cycle = COALESCE(NULLIF(VALUES(billing_cycle), ""), billing_cycle),
              payment_status = VALUES(payment_status),
              provisioning_status = IF(provisioning_status = "active", provisioning_status, VALUES(provisioning_status)),
              domain_registration_status = COALESCE(VALUES(domain_registration_status), domain_registration_status),
              hosting_setup_status = COALESCE(VALUES(hosting_setup_status), hosting_setup_status),
              stripe_reference = COALESCE(NULLIF(VALUES(stripe_reference), ""), stripe_reference),
              updated_at = NOW()'
        );

        $paymentStatus = (string) ($item['payment_status'] ?? $order['payment_status'] ?? 'pending');
        $stmt->execute([
            'customer_order_id' => (int) ($order['id'] ?? 0),
            'customer_user_id' => $this->nullableInt($order['customer_user_id'] ?? null),
            'whmcs_client_id' => (int) ($order['whmcs_client_id'] ?? 0),
            'whmcs_order_id' => $this->nullableInt($order['whmcs_order_id'] ?? null),
            'whmcs_invoice_id' => (int) ($order['whmcs_invoice_id'] ?? 0),
            'item_type' => (string) $item['item_type'],
            'item_key' => (string) $item['item_key'],
            'display_name' => (string) ($item['display_name'] ?? ''),
            'domain_name' => $item['domain_name'] ?? null,
            'hosting_plan_slug' => $item['hosting_plan_slug'] ?? null,
            'whm_package' => (string) ($item['whm_package'] ?? ''),
            'billing_cycle' => (string) ($item['billing_cycle'] ?? ''),
            'payment_status' => $paymentStatus,
            'provisioning_status' => $paymentStatus === 'paid' ? 'processing' : 'pending_payment',
            'domain_registration_status' => $item['domain_registration_status'] ?? null,
            'hosting_setup_status' => $item['hosting_setup_status'] ?? null,
            'stripe_reference' => (string) ($order['stripe_payment_reference'] ?? ''),
        ]);
    }

    private function markOrderAggregate(int $orderId, array $fields): void
    {
        if ($orderId <= 0) {
            return;
        }

        $columns = $this->customerOrderColumns();
        $set = [];
        $params = ['id' => $orderId];
        foreach ($fields as $field => $value) {
            if (!isset($columns[$field])) {
                continue;
            }
            $set[] = $field . ' = :' . $field;
            $params[$field] = is_string($value) ? substr($value, 0, $field === 'provisioning_last_error' ? 1000 : 190) : $value;
        }

        if (!$set) {
            return;
        }

        if (isset($columns['updated_at'])) {
            $set[] = 'updated_at = NOW()';
        }

        $stmt = $this->db->prepare('UPDATE customer_orders SET ' . implode(', ', $set) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    private function customerOrderColumns(): array
    {
        try {
            $stmt = $this->db->query('SHOW COLUMNS FROM customer_orders');
            $columns = [];
            foreach ($stmt->fetchAll() as $row) {
                $field = (string) ($row['Field'] ?? '');
                if ($field !== '') {
                    $columns[$field] = true;
                }
            }

            return $columns;
        } catch (\Throwable) {
            return [];
        }
    }

    private function customerProvisioningColumns(): array
    {
        if ($this->customerProvisioningColumns !== null) {
            return $this->customerProvisioningColumns;
        }

        try {
            $stmt = $this->db->query('SHOW COLUMNS FROM customer_provisioning_items');
            $columns = [];
            foreach ($stmt->fetchAll() as $row) {
                $field = (string) ($row['Field'] ?? $row['field'] ?? '');
                if ($field !== '') {
                    $columns[$field] = true;
                }
            }

            return $this->customerProvisioningColumns = $columns;
        } catch (\Throwable) {
            return $this->customerProvisioningColumns = [];
        }
    }

    private function provisioningLogsAvailable(): bool
    {
        if ($this->provisioningLogsAvailable !== null) {
            return $this->provisioningLogsAvailable;
        }

        try {
            $stmt = $this->db->query('SHOW TABLES LIKE "provisioning_logs"');
            return $this->provisioningLogsAvailable = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return $this->provisioningLogsAvailable = false;
        }
    }

    private function orderType(array $order): string
    {
        $type = strtolower(trim((string) ($order['order_type'] ?? '')));
        return in_array($type, ['domain', 'hosting', 'bundle', 'website'], true) ? $type : '';
    }

    private function domainStatuses(string $status): array
    {
        $status = strtolower(trim($status));
        return match ($status) {
            'active' => ['active', 'Registered'],
            'pending', 'pending registration' => ['processing', 'Processing'],
            'cancelled', 'canceled', 'fraud', 'expired' => ['action_required', 'Action Required'],
            default => ['processing', $status !== '' ? ucwords(str_replace('_', ' ', $status)) : 'Processing'],
        };
    }

    private function hostingStatuses(string $status): array
    {
        $status = strtolower(trim($status));
        return match ($status) {
            'active' => ['active', 'Setup Completed'],
            'pending' => ['processing', 'Setup in Progress'],
            'suspended' => ['action_required', 'Action Required'],
            'terminated', 'cancelled', 'canceled', 'fraud' => ['action_required', 'Action Required'],
            default => ['processing', $status !== '' ? ucwords(str_replace('_', ' ', $status)) : 'Setup in Progress'],
        };
    }

    private function firstInt(array $row, array $keys): ?int
    {
        foreach ($keys as $key) {
            $id = (int) ($row[$key] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return null;
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = (int) $value;
        return $value > 0 ? $value : null;
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return null;
        }

        return substr($value, 0, 10);
    }

    private function dateTimeOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value . ' 00:00:00';
        }

        return substr($value, 0, 19);
    }

    private function amountOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        $clean = preg_replace('/[^0-9.,-]+/', '', (string) $value) ?: '';
        if ($clean === '') {
            return null;
        }

        if (str_contains($clean, ',') && !str_contains($clean, '.')) {
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }

        return is_numeric($clean) ? number_format((float) $clean, 2, '.', '') : null;
    }
}
