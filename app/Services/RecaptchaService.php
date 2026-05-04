<?php

declare(strict_types=1);

namespace App\Services;

final class RecaptchaService
{
    public static function verify(array $settings, ?string $token): bool
    {
        $enabled = (string) ($settings['recaptcha_enabled'] ?? '0') === '1';
        $secret = $settings['recaptcha_secret_key'] ?? env('RECAPTCHA_SECRET_KEY', '');

        if (!$enabled || !$secret) {
            return true;
        }

        if (!$token) {
            return false;
        }

        $payload = http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);

        $response = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
        if (!$response) {
            return false;
        }

        $decoded = json_decode($response, true);
        return (bool) ($decoded['success'] ?? false);
    }
}
