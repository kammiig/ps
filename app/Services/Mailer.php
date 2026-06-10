<?php

declare(strict_types=1);

namespace App\Services;

final class Mailer
{
    public static function inquiry(array $settings, array $data): bool
    {
        $to = $settings['admin_email'] ?? env('ADMIN_EMAIL', '');
        if (!$to) {
            return false;
        }

        $subject = 'New Planetic Solutions inquiry: ' . ($data['service'] ?? 'Website');
        $body = "A new inquiry was submitted:\n\n"
            . "Name: {$data['full_name']}\n"
            . "Email: {$data['email']}\n"
            . "Phone: {$data['phone']}\n"
            . "Service: {$data['service']}\n\n"
            . "Message:\n{$data['message']}\n";

        $from = $settings['mail_from'] ?? env('MAIL_FROM', $to);
        $headers = [
            'From: Planetic Solutions <' . $from . '>',
            'Reply-To: ' . $data['email'],
            'Content-Type: text/plain; charset=UTF-8',
        ];

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    public static function customerPasswordReset(array $settings, array $user, string $resetUrl): bool
    {
        $to = $user['email'] ?? '';
        if (!$to) {
            return false;
        }

        $subject = 'Reset your Planetic Solutions password';
        $body = "Hello {$user['first_name']},\n\n"
            . "Use this secure link to reset your Planetic Solutions account password:\n\n"
            . $resetUrl . "\n\n"
            . "This link expires in 60 minutes. If you did not request this, you can ignore this email.\n";

        $from = $settings['mail_from'] ?? env('MAIL_FROM', $settings['admin_email'] ?? $to);
        $headers = [
            'From: Planetic Solutions <' . $from . '>',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    public static function hostingProvisioned(array $settings, array $user, array $credentials): bool
    {
        $to = $user['email'] ?? '';
        if (!$to) {
            return false;
        }

        $firstName = trim((string) ($user['first_name'] ?? 'there')) ?: 'there';
        $domain = (string) ($credentials['domain'] ?? '');
        $username = (string) ($credentials['username'] ?? '');
        $password = (string) ($credentials['password'] ?? '');
        $serverIp = (string) ($credentials['server_ip'] ?? '');
        $cpanelUrl = (string) ($credentials['cpanel_url'] ?? '');

        if ($domain === '' || $username === '' || $password === '') {
            return false;
        }

        $subject = 'Your Planetic Solutions hosting is ready';
        $body = "Hello {$firstName},\n\n"
            . "Your hosting account for {$domain} has been created.\n\n"
            . "cPanel URL: " . ($cpanelUrl !== '' ? $cpanelUrl : 'Available from your Planetic account') . "\n"
            . "Server IP: " . ($serverIp !== '' ? $serverIp : 'Available from your Planetic account') . "\n"
            . "cPanel username: {$username}\n"
            . "Initial cPanel password: {$password}\n\n"
            . "For your security, keep this password private and change it after your first login. "
            . "DNS and Cloudflare setup can take time to propagate after nameservers are updated.\n\n"
            . "You can view your hosting status from your Planetic Solutions account dashboard.\n";

        $from = $settings['mail_from'] ?? env('MAIL_FROM', $settings['admin_email'] ?? $to);
        $headers = [
            'From: Planetic Solutions <' . $from . '>',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
}
