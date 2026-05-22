<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use PDOException;

final class RateLimiter
{
    public static function tooManyAttempts(string $action, string $identifier, int $maxAttempts, int $decaySeconds): bool
    {
        try {
            self::cleanup();
            $stmt = Database::connection()->prepare(
                'SELECT attempts, available_at FROM customer_rate_limits
                 WHERE action = :action AND identifier = :identifier LIMIT 1'
            );
            $stmt->execute(['action' => $action, 'identifier' => self::identifier($identifier)]);
            $row = $stmt->fetch();
            if (!$row) {
                return false;
            }

            if (!empty($row['available_at']) && strtotime((string) $row['available_at']) > time()) {
                return true;
            }

            return (int) $row['attempts'] >= $maxAttempts;
        } catch (PDOException) {
            return false;
        }
    }

    public static function hit(string $action, string $identifier, int $decaySeconds): void
    {
        try {
            $expires = (new DateTimeImmutable('+' . $decaySeconds . ' seconds'))->format('Y-m-d H:i:s');
            Database::connection()->prepare(
                'INSERT INTO customer_rate_limits (action, identifier, attempts, available_at, expires_at, updated_at)
                 VALUES (:action, :identifier, 1, NULL, :expires_at, NOW())
                 ON DUPLICATE KEY UPDATE attempts = attempts + 1, expires_at = VALUES(expires_at), updated_at = NOW()'
            )->execute([
                'action' => $action,
                'identifier' => self::identifier($identifier),
                'expires_at' => $expires,
            ]);
        } catch (PDOException) {
            return;
        }
    }

    public static function clear(string $action, string $identifier): void
    {
        try {
            Database::connection()->prepare(
                'DELETE FROM customer_rate_limits WHERE action = :action AND identifier = :identifier'
            )->execute(['action' => $action, 'identifier' => self::identifier($identifier)]);
        } catch (PDOException) {
            return;
        }
    }

    private static function cleanup(): void
    {
        Database::connection()->exec('DELETE FROM customer_rate_limits WHERE expires_at < NOW()');
    }

    private static function identifier(string $identifier): string
    {
        return substr(strtolower(trim($identifier)), 0, 190);
    }
}
