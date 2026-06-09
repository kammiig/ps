<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class TicketRepository
{
    public const DEPARTMENTS = [
        'Domain Support',
        'Hosting Support',
        'Website Development',
        'Billing',
        'General Support',
    ];

    public const PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];

    public const STATUSES = [
        'Open',
        'Answered',
        'Customer Reply',
        'In Progress',
        'On Hold',
        'Closed',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function createForCustomer(array $account, array $data, string $message, array $attachments = []): array
    {
        $customerId = (int) ($account['id'] ?? 0);
        if ($customerId <= 0) {
            throw new \InvalidArgumentException('A customer account is required.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO support_tickets
                 (public_ref, customer_user_id, whmcs_client_id, subject, department, priority, status,
                  related_type, related_label, related_reference, last_customer_reply_at, created_at, updated_at)
                 VALUES
                 (:public_ref, :customer_user_id, :whmcs_client_id, :subject, :department, :priority, "Open",
                  :related_type, :related_label, :related_reference, NOW(), NOW(), NOW())'
            );
            $stmt->execute([
                'public_ref' => $this->newPublicRef(),
                'customer_user_id' => $customerId,
                'whmcs_client_id' => $this->nullableInt($account['whmcs_client_id'] ?? null),
                'subject' => $this->cleanText((string) ($data['subject'] ?? ''), 190),
                'department' => $this->department((string) ($data['department'] ?? '')),
                'priority' => $this->priority((string) ($data['priority'] ?? '')),
                'related_type' => $this->cleanNullable((string) ($data['related_type'] ?? ''), 40),
                'related_label' => $this->cleanNullable((string) ($data['related_label'] ?? ''), 190),
                'related_reference' => $this->cleanNullable((string) ($data['related_reference'] ?? ''), 190),
            ]);

            $ticketId = (int) $this->db->lastInsertId();
            $messageId = $this->insertMessage(
                $ticketId,
                'customer',
                $customerId,
                $this->customerName($account),
                $message
            );
            $this->insertAttachments($ticketId, $messageId, 'customer', $attachments);
            $this->db->commit();

            return $this->findForCustomer($ticketId, $customerId) ?? [];
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function addCustomerReply(array $ticket, array $account, string $message, array $attachments = []): void
    {
        $ticketId = (int) ($ticket['id'] ?? 0);
        $customerId = (int) ($account['id'] ?? 0);
        if ($ticketId <= 0 || $customerId <= 0) {
            throw new \InvalidArgumentException('A ticket and customer account are required.');
        }

        $this->db->beginTransaction();
        try {
            $messageId = $this->insertMessage($ticketId, 'customer', $customerId, $this->customerName($account), $message);
            $this->insertAttachments($ticketId, $messageId, 'customer', $attachments);

            $status = (string) ($ticket['status'] ?? '') === 'Closed' ? 'Open' : 'Customer Reply';
            $stmt = $this->db->prepare(
                'UPDATE support_tickets
                 SET status = :status, last_customer_reply_at = NOW(), closed_at = NULL, updated_at = NOW()
                 WHERE id = :id AND customer_user_id = :customer_user_id'
            );
            $stmt->execute(['id' => $ticketId, 'customer_user_id' => $customerId, 'status' => $status]);
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function addAdminReply(array $ticket, array $admin, string $message, string $status, array $attachments = []): void
    {
        $ticketId = (int) ($ticket['id'] ?? 0);
        if ($ticketId <= 0) {
            throw new \InvalidArgumentException('A ticket is required.');
        }

        $this->db->beginTransaction();
        try {
            $message = trim($message);
            if ($message !== '') {
                $messageId = $this->insertMessage(
                    $ticketId,
                    'admin',
                    $this->nullableInt($admin['id'] ?? null),
                    trim((string) ($admin['name'] ?? $admin['email'] ?? 'Planetic Support')) ?: 'Planetic Support',
                    $message
                );
                $this->insertAttachments($ticketId, $messageId, 'admin', $attachments);
            }

            $this->updateStatusInternal($ticketId, $this->status($status), $message !== '');
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function ticketsForCustomer(int $customerId, int $limit = 100): array
    {
        if ($customerId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT t.*,
                    (SELECT COUNT(*) FROM support_ticket_messages m WHERE m.ticket_id = t.id) AS message_count
             FROM support_tickets t
             WHERE t.customer_user_id = :customer_user_id
             ORDER BY t.updated_at DESC, t.id DESC
             LIMIT ' . max(1, min(200, $limit))
        );
        $stmt->execute(['customer_user_id' => $customerId]);
        return $stmt->fetchAll();
    }

    public function recentForCustomer(int $customerId, int $limit = 3): array
    {
        return $this->ticketsForCustomer($customerId, $limit);
    }

    public function openCountForCustomer(int $customerId): int
    {
        if ($customerId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM support_tickets WHERE customer_user_id = :customer_user_id AND status != 'Closed'");
        $stmt->execute(['customer_user_id' => $customerId]);
        return (int) $stmt->fetchColumn();
    }

    public function findForCustomer(int $ticketId, int $customerId): ?array
    {
        if ($ticketId <= 0 || $customerId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT t.*
             FROM support_tickets t
             WHERE t.id = :id AND t.customer_user_id = :customer_user_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $ticketId, 'customer_user_id' => $customerId]);
        $ticket = $stmt->fetch();
        return $ticket ?: null;
    }

    public function findForAdmin(int $ticketId): ?array
    {
        if ($ticketId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT t.*, CONCAT(c.first_name, " ", c.last_name) AS customer_name, c.email AS customer_email
             FROM support_tickets t
             INNER JOIN customer_users c ON c.id = t.customer_user_id
             WHERE t.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $ticketId]);
        $ticket = $stmt->fetch();
        return $ticket ?: null;
    }

    public function ticketsForAdmin(string $status = '', int $limit = 200): array
    {
        $params = [];
        $where = '';
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where = 'WHERE t.status = :status';
            $params['status'] = $status;
        }

        $stmt = $this->db->prepare(
            'SELECT t.*, CONCAT(c.first_name, " ", c.last_name) AS customer_name, c.email AS customer_email,
                    (SELECT COUNT(*) FROM support_ticket_messages m WHERE m.ticket_id = t.id) AS message_count
             FROM support_tickets t
             INNER JOIN customer_users c ON c.id = t.customer_user_id
             ' . $where . '
             ORDER BY
                CASE WHEN t.status = "Customer Reply" THEN 0
                     WHEN t.status = "Open" THEN 1
                     WHEN t.status = "In Progress" THEN 2
                     WHEN t.status = "On Hold" THEN 3
                     WHEN t.status = "Answered" THEN 4
                     ELSE 5 END,
                t.updated_at DESC, t.id DESC
             LIMIT ' . max(1, min(300, $limit))
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function messages(int $ticketId): array
    {
        if ($ticketId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT *
             FROM support_ticket_messages
             WHERE ticket_id = :ticket_id
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute(['ticket_id' => $ticketId]);
        return $stmt->fetchAll();
    }

    public function attachmentsByMessage(int $ticketId): array
    {
        if ($ticketId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT *
             FROM support_ticket_attachments
             WHERE ticket_id = :ticket_id
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute(['ticket_id' => $ticketId]);
        $grouped = [];
        foreach ($stmt->fetchAll() as $attachment) {
            $grouped[(int) $attachment['message_id']][] = $attachment;
        }

        return $grouped;
    }

    public function adminCounts(): array
    {
        $counts = ['open' => 0, 'customer_reply' => 0, 'total' => 0];
        try {
            $counts['total'] = (int) $this->db->query('SELECT COUNT(*) FROM support_tickets')->fetchColumn();
            $counts['open'] = (int) $this->db->query("SELECT COUNT(*) FROM support_tickets WHERE status != 'Closed'")->fetchColumn();
            $counts['customer_reply'] = (int) $this->db->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'Customer Reply'")->fetchColumn();
        } catch (\Throwable) {
            return $counts;
        }

        return $counts;
    }

    private function insertMessage(int $ticketId, string $authorType, ?int $authorUserId, string $authorName, string $message): int
    {
        $message = trim($message);
        if ($message === '') {
            throw new \InvalidArgumentException('A message is required.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO support_ticket_messages
             (ticket_id, author_type, author_user_id, author_name, message, created_at)
             VALUES
             (:ticket_id, :author_type, :author_user_id, :author_name, :message, NOW())'
        );
        $stmt->execute([
            'ticket_id' => $ticketId,
            'author_type' => $authorType,
            'author_user_id' => $authorUserId,
            'author_name' => $this->cleanText($authorName, 160),
            'message' => substr($message, 0, 10000),
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function insertAttachments(int $ticketId, int $messageId, string $uploadedByType, array $attachments): void
    {
        if (!$attachments) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO support_ticket_attachments
             (ticket_id, message_id, uploaded_by_type, original_name, stored_path, mime_type, file_size, created_at)
             VALUES
             (:ticket_id, :message_id, :uploaded_by_type, :original_name, :stored_path, :mime_type, :file_size, NOW())'
        );

        foreach ($attachments as $attachment) {
            $stmt->execute([
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
                'uploaded_by_type' => $uploadedByType,
                'original_name' => $this->cleanText((string) ($attachment['original_name'] ?? 'attachment'), 190),
                'stored_path' => $this->cleanText((string) ($attachment['stored_path'] ?? ''), 255),
                'mime_type' => $this->cleanNullable((string) ($attachment['mime_type'] ?? ''), 120),
                'file_size' => max(0, (int) ($attachment['file_size'] ?? 0)),
            ]);
        }
    }

    private function updateStatusInternal(int $ticketId, string $status, bool $hasAdminReply): void
    {
        $stmt = $this->db->prepare(
            'UPDATE support_tickets
             SET status = :status,
                 last_admin_reply_at = CASE WHEN :has_admin_reply = 1 THEN NOW() ELSE last_admin_reply_at END,
                 closed_at = CASE WHEN :status_closed = 1 THEN NOW() ELSE NULL END,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $ticketId,
            'status' => $status,
            'has_admin_reply' => $hasAdminReply ? 1 : 0,
            'status_closed' => $status === 'Closed' ? 1 : 0,
        ]);
    }

    private function newPublicRef(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $ref = 'PS' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM support_tickets WHERE public_ref = :public_ref');
            $stmt->execute(['public_ref' => $ref]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $ref;
            }
        }

        return 'PS' . strtoupper(bin2hex(random_bytes(7)));
    }

    private function customerName(array $account): string
    {
        $name = trim((string) ($account['first_name'] ?? '') . ' ' . (string) ($account['last_name'] ?? ''));
        return $name !== '' ? $name : (string) ($account['email'] ?? 'Customer');
    }

    private function department(string $department): string
    {
        return in_array($department, self::DEPARTMENTS, true) ? $department : 'General Support';
    }

    private function priority(string $priority): string
    {
        return in_array($priority, self::PRIORITIES, true) ? $priority : 'Medium';
    }

    private function status(string $status): string
    {
        return in_array($status, self::STATUSES, true) ? $status : 'Answered';
    }

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    private function cleanText(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?: '');
        return substr($value, 0, $limit);
    }

    private function cleanNullable(string $value, int $limit): ?string
    {
        $value = $this->cleanText($value, $limit);
        return $value !== '' ? $value : null;
    }
}
