<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class Database
{
    private static $pdo;

    public static function boot(array $config): void
    {
        if (self::$pdo instanceof PDO) {
            return;
        }

        self::$pdo = self::connect($config['db'] ?? []);
        self::ensureUserRoleModel(self::$pdo);
    }

    public static function connection(): PDO
    {
        if (!self::$pdo instanceof PDO) {
            throw new PDOException('数据库尚未初始化。');
        }

        return self::$pdo;
    }

    public static function disconnect(): void
    {
        self::$pdo = null;
    }

    public static function connect(array $dbConfig, bool $allowCreateDatabase = false): PDO
    {
        $database = trim((string) ($dbConfig['database'] ?? ''));
        if ($database === '') {
            throw new PDOException('数据库名称不能为空。');
        }

        if ($allowCreateDatabase || !empty($dbConfig['auto_create_database'])) {
            $pdo = self::connectServer($dbConfig);
            self::createDatabaseIfMissing($pdo, $database, (string) ($dbConfig['charset'] ?? 'utf8mb4'));
            $pdo->exec('USE ' . self::quoteIdentifier($database));
            return $pdo;
        }

        return new PDO(
            self::databaseDsn($dbConfig),
            (string) ($dbConfig['username'] ?? ''),
            (string) ($dbConfig['password'] ?? ''),
            self::pdoOptions()
        );
    }

    public static function connectServer(array $dbConfig): PDO
    {
        return new PDO(
            self::serverDsn($dbConfig),
            (string) ($dbConfig['username'] ?? ''),
            (string) ($dbConfig['password'] ?? ''),
            self::pdoOptions()
        );
    }

    public static function databaseExists(string $database, ?PDO $pdo = null): bool
    {
        if ($database === '') {
            return false;
        }

        $pdo = $pdo ?: self::connection();
        $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1');
        $stmt->execute([$database]);

        return (bool) $stmt->fetchColumn();
    }

    public static function isInstalled(?PDO $pdo = null): bool
    {
        try {
            $pdo = $pdo ?: self::connection();
            foreach (['users', 'settings', 'projects', 'documents', 'activity_logs'] as $table) {
                if (!self::hasTable($pdo, $table)) {
                    return false;
                }
            }

            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    public static function migrate(?PDO $pdo = null): void
    {
        $pdo = $pdo ?: self::connection();
        $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');
        if ($schema === false) {
            throw new PDOException('无法读取数据库结构文件。');
        }

        $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $schema) ?: []));
        foreach ($statements as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    public static function ensureBaseSettings(?PDO $pdo = null): void
    {
        $pdo = $pdo ?: self::connection();
        $defaults = [
            'appearance_background' => '',
            'brand_subtitle' => '',
            'module_icons' => json_encode(app_config('default_icons'), JSON_UNESCAPED_UNICODE),
        ];

        $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_key = setting_key');
        foreach ($defaults as $key => $value) {
            $stmt->execute([$key, $value]);
        }

        $legacySubtitles = [
            '项目执行、人员排班、过程文档、统计报表统一协同',
            '椤圭洰鎵ц銆佷汉鍛樻帓鐝€佽繃绋嬫枃妗ｃ€佺粺璁℃姤琛ㄧ粺涓€鍗忓悓',
        ];
        $placeholders = implode(', ', array_fill(0, count($legacySubtitles), '?'));
        $cleanup = $pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ? AND setting_value IN (' . $placeholders . ')');
        $cleanup->execute(array_merge(['', 'brand_subtitle'], $legacySubtitles));
    }

    public static function countUsers(?PDO $pdo = null): int
    {
        $pdo = $pdo ?: self::connection();
        if (!self::hasTable($pdo, 'users')) {
            return 0;
        }

        return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public static function createAdminUser(array $adminData, ?PDO $pdo = null): void
    {
        $pdo = $pdo ?: self::connection();

        $username = trim((string) ($adminData['username'] ?? ''));
        $displayName = trim((string) ($adminData['display_name'] ?? ''));
        $password = (string) ($adminData['password'] ?? '');

        if ($username === '' || $displayName === '' || $password === '') {
            throw new RuntimeException('管理员账号、显示名称和密码不能为空。');
        }

        $existing = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $existing->execute([$username]);
        if ($existing->fetch()) {
            throw new RuntimeException('管理员账号已存在，请更换账号名称。');
        }

        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, display_name, role, is_active) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $displayName, 'admin', 1]);
    }

    public static function install(array $dbConfig, array $adminData = []): array
    {
        $serverPdo = self::connectServer($dbConfig);
        $database = trim((string) ($dbConfig['database'] ?? ''));
        $databaseExists = self::databaseExists($database, $serverPdo);
        $createdDatabase = false;

        if (!$databaseExists) {
            if (empty($dbConfig['auto_create_database'])) {
                throw new RuntimeException('目标数据库不存在，请先创建数据库或勾选自动创建数据库。');
            }

            self::createDatabaseIfMissing($serverPdo, $database, (string) ($dbConfig['charset'] ?? 'utf8mb4'));
            $createdDatabase = true;
        }

        $pdo = self::connect($dbConfig);
        self::migrate($pdo);
        self::ensureUserRoleModel($pdo);
        self::ensureBaseSettings($pdo);

        $createdAdmin = false;
        if (self::countUsers($pdo) === 0) {
            self::createAdminUser($adminData, $pdo);
            $createdAdmin = true;
        }

        return [
            'created_database' => $createdDatabase,
            'created_admin' => $createdAdmin,
        ];
    }

    private static function hasTable(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    }

    private static function ensureUserRoleModel(PDO $pdo): void
    {
        if (!self::hasTable($pdo, 'users')) {
            return;
        }

        try {
            $pdo->exec("UPDATE users SET role = 'editor' WHERE role = 'auditor'");
        } catch (Throwable $exception) {
        }

        try {
            $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin', 'editor') NOT NULL DEFAULT 'editor'");
        } catch (Throwable $exception) {
        }
    }

    private static function createDatabaseIfMissing(PDO $pdo, string $database, string $charset): void
    {
        if ($database === '') {
            throw new RuntimeException('数据库名称不能为空。');
        }

        $charset = trim($charset) !== '' ? trim($charset) : 'utf8mb4';
        $quotedDatabase = self::quoteIdentifier($database);
        $pdo->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET %s COLLATE %s_unicode_ci',
            $quotedDatabase,
            $charset,
            $charset
        ));
    }

    private static function serverDsn(array $dbConfig): string
    {
        $host = trim((string) ($dbConfig['host'] ?? '127.0.0.1'));
        $port = max((int) ($dbConfig['port'] ?? 3306), 1);
        $charset = trim((string) ($dbConfig['charset'] ?? 'utf8mb4'));
        if ($charset === '') {
            $charset = 'utf8mb4';
        }

        return sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $charset);
    }

    private static function databaseDsn(array $dbConfig): string
    {
        $host = trim((string) ($dbConfig['host'] ?? '127.0.0.1'));
        $port = max((int) ($dbConfig['port'] ?? 3306), 1);
        $database = trim((string) ($dbConfig['database'] ?? ''));
        $charset = trim((string) ($dbConfig['charset'] ?? 'utf8mb4'));
        if ($charset === '') {
            $charset = 'utf8mb4';
        }

        return sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);
    }

    private static function quoteIdentifier(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    private static function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
    }
}
