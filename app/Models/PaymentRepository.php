<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class PaymentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function createOrUpdateOrder(array $data): array
    {
        $existing = $this->findByInvoiceId((int) $data['whmcs_invoice_id']);
        if ($existing) {
            if ($existing['payment_status'] !== 'paid') {
                $stmt = $this->db->prepare(
                    'UPDATE customer_orders
                     SET customer_user_id = :customer_user_id, whmcs_client_id = :whmcs_client_id,
                         whmcs_order_id = :whmcs_order_id, invoice_amount = :invoice_amount,
                         currency = :currency, payment_status = IF(payment_status = "paid", payment_status, "pending"),
                         last_error = NULL, updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute([
                    'id' => (int) $existing['id'],
                    'customer_user_id' => $data['customer_user_id'] ?? $existing['customer_user_id'],
                    'whmcs_client_id' => (int) $data['whmcs_client_id'],
                    'whmcs_order_id' => $data['whmcs_order_id'] ?? null,
                    'invoice_amount' => number_format((float) $data['invoice_amount'], 2, '.', ''),
                    'currency' => strtoupper((string) $data['currency']),
                ]);
            }

            return $this->find((int) $existing['id']) ?? $existing;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO customer_orders
             (public_token, customer_user_id, whmcs_client_id, whmcs_order_id, whmcs_invoice_id,
              invoice_amount, currency, payment_status, created_at, updated_at)
             VALUES
             (:public_token, :customer_user_id, :whmcs_client_id, :whmcs_order_id, :whmcs_invoice_id,
              :invoice_amount, :currency, "pending", NOW(), NOW())'
        );
        $stmt->execute([
            'public_token' => bin2hex(random_bytes(32)),
            'customer_user_id' => $data['customer_user_id'] ?? null,
            'whmcs_client_id' => (int) $data['whmcs_client_id'],
            'whmcs_order_id' => $data['whmcs_order_id'] ?? null,
            'whmcs_invoice_id' => (int) $data['whmcs_invoice_id'],
            'invoice_amount' => number_format((float) $data['invoice_amount'], 2, '.', ''),
            'currency' => strtoupper((string) $data['currency']),
        ]);

        return $this->find((int) $this->db->lastInsertId()) ?? [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customer_orders WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch();
        return $order ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customer_orders WHERE public_token = :token LIMIT 1');
        $stmt->execute(['token' => $token]);
        $order = $stmt->fetch();
        return $order ?: null;
    }

    public function findByInvoiceId(int $invoiceId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customer_orders WHERE whmcs_invoice_id = :invoice_id LIMIT 1');
        $stmt->execute(['invoice_id' => $invoiceId]);
        $order = $stmt->fetch();
        return $order ?: null;
    }

    public function findByPaymentIntent(string $paymentIntentId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customer_orders WHERE stripe_payment_intent_id = :payment_intent LIMIT 1');
        $stmt->execute(['payment_intent' => $paymentIntentId]);
        $order = $stmt->fetch();
        return $order ?: null;
    }

    public function setPaymentIntent(int $id, string $paymentIntentId): ?array
    {
        $stmt = $this->db->prepare(
            'UPDATE customer_orders
             SET stripe_payment_intent_id = :payment_intent, payment_status = "pending", last_error = NULL, updated_at = NOW()
             WHERE id = :id AND payment_status != "paid"'
        );
        $stmt->execute(['id' => $id, 'payment_intent' => $paymentIntentId]);
        return $this->find($id);
    }

    public function markProcessing(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE customer_orders
             SET payment_status = "processing", updated_at = NOW()
             WHERE id = :id AND payment_status IN ("pending", "failed")'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function markPaid(int $id, string $reference): void
    {
        $stmt = $this->db->prepare(
            'UPDATE customer_orders
             SET payment_status = "paid", stripe_payment_reference = :reference, last_error = NULL,
                 paid_at = NOW(), updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'reference' => $reference]);
    }

    public function markFailedByPaymentIntent(string $paymentIntentId, string $message = ''): void
    {
        $stmt = $this->db->prepare(
            'UPDATE customer_orders
             SET payment_status = "failed", last_error = :message, updated_at = NOW()
             WHERE stripe_payment_intent_id = :payment_intent AND payment_status != "paid"'
        );
        $stmt->execute(['payment_intent' => $paymentIntentId, 'message' => substr($message, 0, 500)]);
    }

    public function markPendingWithError(int $id, string $message): void
    {
        $stmt = $this->db->prepare(
            'UPDATE customer_orders
             SET payment_status = "pending", last_error = :message, updated_at = NOW()
             WHERE id = :id AND payment_status != "paid"'
        );
        $stmt->execute(['id' => $id, 'message' => substr($message, 0, 500)]);
    }

    public function recentForCustomer(int $customerId, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM customer_orders
             WHERE customer_user_id = :customer_id
             ORDER BY created_at DESC, id DESC
             LIMIT ' . max(1, $limit)
        );
        $stmt->execute(['customer_id' => $customerId]);
        return $stmt->fetchAll();
    }

    public function insertWebhookEvent(string $eventId, string $type, string $paymentIntentId = '', ?int $orderId = null): bool
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO stripe_webhook_events
             (stripe_event_id, event_type, payment_intent_id, customer_order_id, processing_status, created_at)
             VALUES (:event_id, :event_type, :payment_intent, :order_id, "received", NOW())'
        );
        $stmt->execute([
            'event_id' => $eventId,
            'event_type' => $type,
            'payment_intent' => $paymentIntentId ?: null,
            'order_id' => $orderId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function webhookEvent(string $eventId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM stripe_webhook_events WHERE stripe_event_id = :event_id LIMIT 1');
        $stmt->execute(['event_id' => $eventId]);
        $event = $stmt->fetch();
        return $event ?: null;
    }

    public function markWebhookEvent(string $eventId, string $status, ?int $orderId = null): void
    {
        $stmt = $this->db->prepare(
            'UPDATE stripe_webhook_events
             SET processing_status = :status, customer_order_id = COALESCE(:order_id, customer_order_id),
                 processed_at = NOW()
             WHERE stripe_event_id = :event_id'
        );
        $stmt->execute([
            'event_id' => $eventId,
            'status' => in_array($status, ['processed', 'failed'], true) ? $status : 'processed',
            'order_id' => $orderId,
        ]);
    }
}
