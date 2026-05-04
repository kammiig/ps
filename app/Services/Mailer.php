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
}
