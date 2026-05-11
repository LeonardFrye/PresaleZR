<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class AuthService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function attempt(string $username, string $password, string $ipAddress): bool
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !(int) $user['is_active'] || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

        $this->log((int) $user['id'], 'login', 'auth', '账号登录成功', $ipAddress, [
            'username' => $username,
            'role' => $user['role'],
        ]);

        return true;
    }

    public function logout(string $ipAddress): void
    {
        $user = $this->user();
        if ($user) {
            $this->log((int) $user['id'], 'logout', 'auth', '账号退出登录', $ipAddress, []);
        }

        unset($_SESSION['user_id']);
    }

    public function user(): ?array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function requireLogin(): array
    {
        $user = $this->user();
        if (!$user) {
            redirect('index.php');
        }

        return $user;
    }

    public function can(string $permission): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $role = $user['role'];
        $map = [
            'manage_settings' => ['admin'],
            'manage_users' => ['admin'],
            'manage_performance' => ['admin'],
            'manage_projects' => ['admin', 'editor', 'auditor'],
            'manage_documents' => ['admin', 'editor', 'auditor'],
            'view_reports' => ['admin', 'editor', 'auditor'],
            'export_reports' => ['admin', 'editor', 'auditor'],
            'view_logs' => ['admin', 'editor', 'auditor'],
        ];

        if (!isset($map[$permission])) {
            return false;
        }

        return in_array($role, $map[$permission], true);
    }

    public function log(int $userId, string $actionType, string $moduleName, string $description, string $ipAddress, array $context): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO activity_logs (user_id, action_type, module_name, description, ip_address, context_json) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $actionType,
            $moduleName,
            $description,
            $ipAddress,
            json_encode($context, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
