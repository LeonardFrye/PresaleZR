<?php

declare(strict_types=1);

use App\Support\Database;

function app_config(?string $key = null, $default = null)
{
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/../../config/app.php';
    }

    if ($key === null) {
        return $config;
    }

    $segments = explode('.', $key);
    $value = $config;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function db(): PDO
{
    return Database::connection();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('CSRF 校验失败，请刷新页面后重试。');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return $value;
}

function old(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? (string) $_POST[$key] : $default;
}

function query(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }

    return '?' . http_build_query($params);
}

function now_string(): string
{
    return date('Y-m-d H:i:s');
}

function role_label(string $role): string
{
    $roles = app_config('roles', []);

    return $roles[$role] ?? $role;
}

function date_label(?string $date): string
{
    if (!$date) {
        return '-';
    }

    return date('Y-m-d', strtotime($date));
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return date('Y-m-d H:i', strtotime($value));
}

function format_bytes(int $size): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $step = 0;
    $value = (float) $size;
    while ($value >= 1024 && $step < count($units) - 1) {
        $value /= 1024;
        $step++;
    }

    return round($value, 2) . ' ' . $units[$step];
}

function support_people(string $value): array
{
    $parts = preg_split('/[、,，;；\/\s]+/u', $value) ?: [];
    $parts = array_filter(array_map('trim', $parts), static function ($item): bool {
        return $item !== '';
    });

    return array_values(array_unique($parts));
}

function weekday_label(string $date): string
{
    $map = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'];
    $index = (int) date('w', strtotime($date));

    return $map[$index] ?? '';
}

function week_range(?string $pivot = null): array
{
    $pivot = $pivot ?: date('Y-m-d');
    $timestamp = strtotime($pivot);
    $weekday = (int) date('N', $timestamp);
    $monday = strtotime('-' . ($weekday - 1) . ' days', $timestamp);
    $days = [];

    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime('+' . $i . ' days', $monday));
        $days[] = [
            'date' => $date,
            'label' => date('m/d', strtotime($date)) . ' ' . weekday_label($date),
        ];
    }

    return $days;
}

function in_date_range(string $target, string $start, string $end): bool
{
    return strtotime($target) >= strtotime($start) && strtotime($target) <= strtotime($end);
}

function permission_denied(): void
{
    http_response_code(403);
    exit('没有权限执行此操作。');
}
