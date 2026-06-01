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
        if (!$user || !self::passwordMatches($password, (string) $user['password_hash'])) {
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

    public static function passwordMatches(string $password, string $hash): bool
    {
        $safety = self::passwordHashSafety($hash);
        if (!$safety['safe']) {
            return false;
        }

        return password_verify($password, $hash);
    }

    public static function passwordHashSafety(string $hash): array
    {
        $info = password_get_info($hash);
        $algoName = strtolower((string) ($info['algoName'] ?? ''));
        $options = is_array($info['options'] ?? null) ? $info['options'] : [];

        if (($info['algo'] ?? 0) === 0 || $algoName === 'unknown') {
            return ['safe' => false, 'reason' => 'unsupported_password_hash'];
        }

        if ($algoName === 'bcrypt') {
            $cost = (int) ($options['cost'] ?? 0);
            if ($cost > 14) {
                return ['safe' => false, 'reason' => 'bcrypt_cost_too_high'];
            }
        }

        if (str_starts_with($algoName, 'argon2')) {
            $memoryCost = (int) ($options['memory_cost'] ?? 0);
            $timeCost = (int) ($options['time_cost'] ?? 0);
            if ($memoryCost > 131072 || $timeCost > 6) {
                return ['safe' => false, 'reason' => 'argon2_cost_too_high'];
            }
        }

        return ['safe' => true, 'reason' => 'ok'];
    }
}
