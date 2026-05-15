<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;

final class Database
{
    private static $pdo;

    public static function boot(array $config): void
    {
        if (self::$pdo instanceof PDO) {
            return;
        }

        $db = $config['db'];
        $autoCreateDatabase = (bool) ($db['auto_create_database'] ?? true);
        $dsn = sprintf(
            'mysql:host=%s;port=%s;%scharset=%s',
            $db['host'],
            $db['port'],
            $autoCreateDatabase ? '' : sprintf('dbname=%s;', $db['database']),
            $db['charset']
        );

        self::$pdo = new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        if ($autoCreateDatabase) {
            self::$pdo->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s_unicode_ci',
                $db['database'],
                $db['charset'],
                $db['charset']
            ));
            self::$pdo->exec(sprintf('USE `%s`', $db['database']));
        }

        self::migrate();
        self::seed();
    }

    public static function connection(): PDO
    {
        if (!self::$pdo instanceof PDO) {
            throw new PDOException('数据库尚未初始化。');
        }

        return self::$pdo;
    }

    private static function migrate(): void
    {
        $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');
        if ($schema === false) {
            throw new PDOException('无法读取数据库结构文件。');
        }

        $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $schema) ?: []));
        foreach ($statements as $statement) {
            if ($statement !== '') {
                self::$pdo->exec($statement);
            }
        }
    }

    private static function seed(): void
    {
        $count = (int) self::$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count === 0) {
            $users = [
                ['admin', 'admin123', '系统管理员', 'admin'],
                ['editor', 'editor123', '项目编辑员', 'editor'],
                ['auditor', 'auditor123', '审核员', 'auditor'],
            ];
            $stmt = self::$pdo->prepare('INSERT INTO users (username, password_hash, display_name, role) VALUES (?, ?, ?, ?)');
            foreach ($users as $user) {
                $stmt->execute([$user[0], password_hash($user[1], PASSWORD_DEFAULT), $user[2], $user[3]]);
            }
        }

        $settingsCount = (int) self::$pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn();
        if ($settingsCount === 0) {
            $defaults = [
                'appearance_background' => '',
                'brand_subtitle' => '',
                'module_icons' => json_encode(app_config('default_icons'), JSON_UNESCAPED_UNICODE),
            ];
            $stmt = self::$pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)');
            foreach ($defaults as $key => $value) {
                $stmt->execute([$key, $value]);
            }
        }

        $legacySubtitleValues = [
            '项目执行、人员排班、过程文档、统计报表统一协同',
            '椤圭洰鎵ц銆佷汉鍛樻帓鐝€佽繃绋嬫枃妗ｃ€佺粺璁℃姤琛ㄧ粺涓€鍗忓悓',
        ];
        $stmt = self::$pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ? AND setting_value IN (?, ?)');
        $stmt->execute(['', 'brand_subtitle', $legacySubtitleValues[0], $legacySubtitleValues[1]]);

        $projectCount = (int) self::$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
        if ($projectCount === 0) {
            $adminId = (int) self::$pdo->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1")->fetchColumn();
            $projects = [
                [
                    '西南大学',
                    '成都市',
                    '向立',
                    '实施',
                    '余聪',
                    '2026-05-06',
                    '2026-05-06',
                    1,
                    '围绕教育护网项目开展系统梳理、网络安全分析、问题复盘和优化建议整理。',
                    "1. 完成现场支持；\n2. 后续跟进风险清单；\n3. 加强网络安全专项能力。",
                    0,
                    1,
                ],
                [
                    '人社局',
                    '南充市',
                    '陈明',
                    '实施',
                    '王天',
                    '2026-05-07',
                    '2026-05-07',
                    1,
                    '开展人社系统升级维护，包括数据库优化、安全加固和模块更新。',
                    "1. 系统升级完成；\n2. 待销售回访。",
                    1,
                    0,
                ],
                [
                    '西昌烟草',
                    '凉山州',
                    '李华',
                    '售前',
                    '孙大鹏、刘小花',
                    '2026-05-07',
                    '2026-05-09',
                    3,
                    '完成信息化建设项目前期支持、方案沟通、设备调研和实施交接准备。',
                    "1. 售前方案已确认；\n2. 实施阶段待排期。",
                    1,
                    0,
                ],
            ];
            $stmt = self::$pdo->prepare('INSERT INTO projects (
                project_name, project_region, project_sales, support_role, support_personnel,
                start_date, end_date, duration_days, task_summary, completion_feedback,
                transfer_flag, completion_flag, created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($projects as $project) {
                $stmt->execute(array_merge($project, [$adminId, $adminId]));
            }
        }
    }
}
