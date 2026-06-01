<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\CustomerRepository;

final class CustomerAuth
{
    private CustomerRepository $customers;

    public function __construct()
    {
        $this->customers = new CustomerRepository();
    }

    public function check(): bool
    {
        return !empty($_SESSION['customer_user']['id']);
    }

    public function user(): ?array
    {
        if (empty($_SESSION['customer_user']['id'])) {
            return null;
        }

        $user = $this->customers->find((int) $_SESSION['customer_user']['id']);
        if (!$user) {
            $this->logout();
            return null;
        }

        $_SESSION['customer_user'] = $this->sessionPayload($user);
        return $_SESSION['customer_user'];
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->customers->findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $this->login($user);
        return true;
    }

    public function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['customer_user'] = $this->sessionPayload($user);
        try {
            $this->customers->touchLogin((int) $user['id']);
        } catch (\Throwable) {
            // Login should not fail if the non-critical last-login timestamp cannot be written.
        }
    }

    public function logout(): void
    {
        unset($_SESSION['customer_user']);
        session_regenerate_id(true);
    }

    private function sessionPayload(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'whmcs_client_id' => isset($user['whmcs_client_id']) ? (int) $user['whmcs_client_id'] : 0,
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'email' => $user['email'] ?? '',
        ];
    }
}
