<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class CustomerRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customer_users WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customer_users WHERE email = :email AND is_active = 1 LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function create(array $data): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO customer_users
             (whmcs_client_id, first_name, last_name, company_name, email, phone, address, city, state, postcode, country, password_hash, is_active, created_at, updated_at)
             VALUES
             (:whmcs_client_id, :first_name, :last_name, :company_name, :email, :phone, :address, :city, :state, :postcode, :country, :password_hash, 1, NOW(), NOW())'
        );

        $stmt->execute([
            'whmcs_client_id' => $data['whmcs_client_id'] ?? null,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'company_name' => $data['company_name'] ?? '',
            'email' => strtolower(trim((string) $data['email'])),
            'phone' => $data['phone'] ?? '',
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'state' => $data['state'] ?? '',
            'postcode' => $data['postcode'] ?? '',
            'country' => strtoupper((string) ($data['country'] ?? 'GB')),
            'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
        ]);

        return $this->find((int) $this->db->lastInsertId()) ?? [];
    }

    public function updateProfile(int $id, array $data): ?array
    {
        $stmt = $this->db->prepare(
            'UPDATE customer_users
             SET first_name = :first_name, last_name = :last_name, company_name = :company_name,
                 email = :email, phone = :phone, address = :address, city = :city, state = :state,
                 postcode = :postcode, country = :country, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'company_name' => $data['company_name'] ?? '',
            'email' => strtolower(trim((string) $data['email'])),
            'phone' => $data['phone'] ?? '',
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'state' => $data['state'] ?? '',
            'postcode' => $data['postcode'] ?? '',
            'country' => strtoupper((string) ($data['country'] ?? 'GB')),
        ]);

        return $this->find($id);
    }

    public function setWhmcsClient(int $id, int $whmcsClientId): ?array
    {
        $stmt = $this->db->prepare(
            'UPDATE customer_users
             SET whmcs_client_id = :whmcs_client_id, updated_at = NOW()
             WHERE id = :id AND (whmcs_client_id IS NULL OR whmcs_client_id = :existing_whmcs_client_id)'
        );
        $stmt->execute([
            'id' => $id,
            'whmcs_client_id' => $whmcsClientId,
            'existing_whmcs_client_id' => $whmcsClientId,
        ]);

        return $this->find($id);
    }

    public function touchLogin(int $id): void
    {
        $this->db->prepare('UPDATE customer_users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $id]);
    }

    public function createPasswordReset(int $customerId, string $token, int $minutes = 60): void
    {
        $expiresAt = (new \DateTimeImmutable('+' . $minutes . ' minutes'))->format('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            'INSERT INTO customer_password_resets (customer_user_id, token_hash, expires_at, created_at)
             VALUES (:customer_user_id, :token_hash, :expires_at, NOW())'
        );
        $stmt->execute([
            'customer_user_id' => $customerId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);
    }

    public function resetByToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.email
             FROM customer_password_resets r
             INNER JOIN customer_users u ON u.id = r.customer_user_id
             WHERE r.token_hash = :token_hash AND r.used_at IS NULL AND r.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => hash('sha256', $token)]);
        $reset = $stmt->fetch();
        return $reset ?: null;
    }

    public function updatePasswordFromReset(int $resetId, int $customerId, string $password): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE customer_users SET password_hash = :hash, updated_at = NOW() WHERE id = :id')
                ->execute(['id' => $customerId, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
            $this->db->prepare('UPDATE customer_password_resets SET used_at = NOW() WHERE id = :id')
                ->execute(['id' => $resetId]);
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }
}
