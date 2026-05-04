<?php

declare(strict_types=1);

use App\Core\Env;

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return $path === '/' ? '/' : $path;
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function upload_url(?string $path): string
{
    if (!$path) {
        return '';
    }

    return str_starts_with($path, 'http') ? $path : url(ltrim($path, '/'));
}

function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return rtrim($path, '/') ?: '/';
}

function is_active(string $path): string
{
    return current_path() === $path ? 'is-active' : '';
}

function safe_html(?string $html): string
{
    $allowed = '<p><br><strong><b><em><i><u><a><ul><ol><li><h2><h3><h4><blockquote><table><thead><tbody><tr><th><td><span><div>';
    $clean = strip_tags((string) $html, $allowed);
    $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
    $clean = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
    $clean = preg_replace('/\s+(href|src)\s*=\s*([\'"])\s*javascript:[^\'"]*\2/i', ' $1="#"', $clean) ?? $clean;
    return $clean;
}

function lines_to_array(?string $value): array
{
    $lines = preg_split('/\R/', (string) $value) ?: [];
    return array_values(array_filter(array_map(static fn ($line) => trim($line), $lines)));
}

function array_to_lines(mixed $value): string
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [$value];
    }

    return implode("\n", is_array($value) ? $value : []);
}

function money(string|float|int|null $value): string
{
    if ($value === null || $value === '') {
        return 'Contact us';
    }

    return str_contains((string) $value, '£') ? (string) $value : '£' . $value;
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?: '';
    return trim($value, '-') ?: uniqid('item-', false);
}

function excerpt(string $html, int $limit = 155): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?: '');
    if (strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(substr($text, 0, $limit - 1)) . '...';
}

function icon(string $name): string
{
    $icons = [
        'bolt' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 3 14h8l-1 8 11-14h-8l0-6Z"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5l-8-3Zm0 4 4 1.5V11c0 3-1.6 5.8-4 7-2.4-1.2-4-4-4-7V7.5L12 6Z"/></svg>',
        'cloud' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.5 19h8.7A5.8 5.8 0 0 0 18 7.5 7 7 0 0 0 4.8 10 4.7 4.7 0 0 0 8.5 19Zm0-2a2.7 2.7 0 0 1-.4-5.4l1.2-.2.2-1.2A5 5 0 0 1 19 12.1l-.2 1.1 1 .5A3 3 0 0 1 17.2 17H8.5Z"/></svg>',
        'panel' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h18v16H3V4Zm2 4h14V6H5v2Zm0 10h6v-8H5v8Zm8 0h6v-8h-6v8Z"/></svg>',
        'globe' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm6.9 9h-3a15.7 15.7 0 0 0-1.1-5 8 8 0 0 1 4.1 5ZM12 4.1c.7 1 1.5 3.1 1.8 6.9h-3.6c.3-3.8 1.1-5.9 1.8-6.9ZM4.2 13h3.9c.1 1.9.4 3.5.8 4.9A8 8 0 0 1 4.2 13Zm3.9-2H4.2a8 8 0 0 1 5-5c-.5 1.4-.9 3.1-1.1 5Zm3.9 8.9c-.7-1-1.5-3.1-1.8-6.9h3.6c-.3 3.8-1.1 5.9-1.8 6.9Zm2.8-2c.4-1.4.7-3 .8-4.9h3.1a8 8 0 0 1-3.9 4.9Z"/></svg>',
        'code' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8.7 16.3-4.3-4.3 4.3-4.3 1.4 1.4-2.9 2.9 2.9 2.9-1.4 1.4Zm6.6 0-1.4-1.4 2.9-2.9-2.9-2.9 1.4-1.4 4.3 4.3-4.3 4.3ZM11 19l-1.9-.6L13 5l1.9.6L11 19Z"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9.2 16.6-4.1-4.1 1.4-1.4 2.7 2.7 8.3-8.3 1.4 1.4-9.7 9.7Z"/></svg>',
        'arrow' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 5 20 12l-7 7-1.4-1.4 4.6-4.6H4v-2h12.2l-4.6-4.6L13 5Z"/></svg>',
        'mail' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v14H3V5Zm2 3.2V17h14V8.2l-7 5-7-5Zm1.5-1.2 5.5 3.9L17.5 7h-11Z"/></svg>',
        'phone' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.2 15.2 0 0 0 6.6 6.6l2.2-2.2c.3-.3.8-.4 1.2-.3 1.3.4 2.6.6 4 .6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.9 21 3 13.1 3 3.4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.4.2 2.8.6 4 .1.4 0 .8-.3 1.1l-2.2 2.3Z"/></svg>',
    ];

    return $icons[$name] ?? $icons['check'];
}
