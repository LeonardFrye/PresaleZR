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

function format_datetime_local(?string $value): string
{
    if (!$value) {
        return '';
    }

    return date('Y-m-d\TH:i', strtotime($value));
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

function normalize_datetime_value(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = str_replace('T', ' ', $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
        $value .= ' 09:00:00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $value) === 1) {
        $value .= ':00';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function project_workday_minutes(string $startAt, string $endAt): int
{
    $startAt = normalize_datetime_value($startAt);
    $endAt = normalize_datetime_value($endAt);
    if ($startAt === '' || $endAt === '') {
        return 0;
    }

    $start = new DateTimeImmutable($startAt);
    $end = new DateTimeImmutable($endAt);
    if ($end <= $start) {
        return 0;
    }

    $minutes = 0;
    $currentDate = $start->setTime(0, 0, 0);
    $lastDate = $end->setTime(0, 0, 0);
    $windows = [
        ['09:00:00', '12:00:00'],
        ['13:00:00', '18:00:00'],
    ];

    while ($currentDate <= $lastDate) {
        $date = $currentDate->format('Y-m-d');
        foreach ($windows as [$windowStartTime, $windowEndTime]) {
            $windowStart = new DateTimeImmutable($date . ' ' . $windowStartTime);
            $windowEnd = new DateTimeImmutable($date . ' ' . $windowEndTime);
            $effectiveStart = $start > $windowStart ? $start : $windowStart;
            $effectiveEnd = $end < $windowEnd ? $end : $windowEnd;

            if ($effectiveEnd > $effectiveStart) {
                $minutes += (int) floor(($effectiveEnd->getTimestamp() - $effectiveStart->getTimestamp()) / 60);
            }
        }

        $currentDate = $currentDate->modify('+1 day');
    }

    return $minutes;
}

function project_workload_score(string $startAt, string $endAt): float
{
    return round(project_workday_minutes($startAt, $endAt) / 480, 2);
}

function project_daily_workload_scores(string $startAt, string $endAt): array
{
    $startAt = normalize_datetime_value($startAt);
    $endAt = normalize_datetime_value($endAt);
    if ($startAt === '' || $endAt === '') {
        return [];
    }

    $start = new DateTimeImmutable($startAt);
    $end = new DateTimeImmutable($endAt);
    if ($end <= $start) {
        return [];
    }

    $scores = [];
    $currentDate = $start->setTime(0, 0, 0);
    $lastDate = $end->setTime(0, 0, 0);

    while ($currentDate <= $lastDate) {
        $date = $currentDate->format('Y-m-d');
        $dayStart = $date . ' 00:00:00';
        $dayEnd = $date . ' 23:59:59';
        $effectiveStart = $startAt > $dayStart ? $startAt : $dayStart;
        $effectiveEnd = $endAt < $dayEnd ? $endAt : $dayEnd;
        $scores[$date] = round(project_workday_minutes($effectiveStart, $effectiveEnd) / 480, 2);
        $currentDate = $currentDate->modify('+1 day');
    }

    return $scores;
}

function permission_denied(): void
{
    http_response_code(403);
    exit('没有权限执行此操作。');
}
