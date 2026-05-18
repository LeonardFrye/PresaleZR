<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Support\Database;

$dbDefaults = app_config('db', []);
$form = [
    'host' => trim((string) ($_POST['host'] ?? $dbDefaults['host'] ?? '127.0.0.1')),
    'port' => trim((string) ($_POST['port'] ?? $dbDefaults['port'] ?? '3306')),
    'database' => trim((string) ($_POST['database'] ?? $dbDefaults['database'] ?? '')),
    'username' => trim((string) ($_POST['username'] ?? $dbDefaults['username'] ?? '')),
    'password' => (string) ($_POST['password'] ?? $dbDefaults['password'] ?? ''),
    'charset' => trim((string) ($_POST['charset'] ?? $dbDefaults['charset'] ?? 'utf8mb4')),
    'auto_create_database' => !empty($_POST['auto_create_database']) || (!is_post() && !empty($dbDefaults['auto_create_database'])),
    'admin_username' => trim((string) ($_POST['admin_username'] ?? 'admin')),
    'admin_display_name' => trim((string) ($_POST['admin_display_name'] ?? '系统管理员')),
    'admin_password' => (string) ($_POST['admin_password'] ?? ''),
    'admin_password_confirm' => (string) ($_POST['admin_password_confirm'] ?? ''),
];

$flashSuccess = '';
$flashError = '';
$statusSummary = [];
$installedState = inspect_current_installation($dbDefaults);

if (is_post()) {
    verify_csrf();

    try {
        $dbConfig = build_db_config($form);
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'test_connection') {
            $statusSummary = test_database_connection($dbConfig);
            $flashSuccess = '数据库连接检测完成，可以继续执行安装。';
        } elseif ($action === 'run_install') {
            if ($form['admin_password'] === '' || $form['admin_password'] !== $form['admin_password_confirm']) {
                throw new RuntimeException('两次输入的管理员密码不一致。');
            }

            $result = Database::install($dbConfig, [
                'username' => $form['admin_username'],
                'display_name' => $form['admin_display_name'],
                'password' => $form['admin_password'],
            ]);

            write_config_file($dbConfig);
            Database::disconnect();
            $installedState = inspect_current_installation($dbConfig);

            $messages = ['安装完成，数据库连接与表结构已经准备就绪。'];
            $messages[] = $result['created_database'] ? '已创建目标数据库。' : '目标数据库已存在。';
            $messages[] = $result['created_admin'] ? '已创建首个系统管理员账号。' : '检测到已有账号，本次未新建管理员。';
            $flashSuccess = implode(' ', $messages);
            $statusSummary = test_database_connection($dbConfig);
        }
    } catch (Throwable $exception) {
        $flashError = $exception->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装 - 技术支撑事业部项目管理平台 V1.3</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background:
                radial-gradient(circle at top left, rgba(59, 130, 246, 0.12), transparent 28%),
                radial-gradient(circle at right top, rgba(16, 185, 129, 0.1), transparent 24%),
                linear-gradient(180deg, #f8fbff 0%, #eff6ff 50%, #f8fafc 100%);
        }
    </style>
</head>
<body class="min-h-screen text-slate-800">
<main class="mx-auto flex min-h-screen max-w-6xl items-center px-4 py-8 sm:px-6 lg:px-8">
    <div class="grid w-full gap-6 lg:grid-cols-[1.05fr_1.35fr]">
        <section class="rounded-[28px] border border-slate-200 bg-white/90 p-7 shadow-[0_18px_50px_rgba(15,23,42,0.08)] backdrop-blur">
            <div class="mb-8">
                <div class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-600 text-2xl text-white shadow-lg shadow-blue-200">
                    <span>安</span>
                </div>
                <p class="text-sm font-semibold tracking-[0.2em] text-blue-600">INSTALLER</p>
                <h1 class="mt-3 text-3xl font-bold text-slate-900">技术支撑事业部项目管理平台 V1.3</h1>
                <p class="mt-3 text-sm leading-7 text-slate-500">项目部署完成后，请先访问 <code class="rounded bg-slate-100 px-2 py-1 text-slate-700">/install.php</code> 检测数据库连接并初始化系统。安装过程只会建立表结构和基础配置，不会写入任何测试项目数据。</p>
            </div>

            <div class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm font-semibold text-slate-900">当前配置状态</div>
                    <div class="mt-3 grid gap-3 text-sm text-slate-600">
                        <div class="flex items-center justify-between rounded-xl bg-white px-3 py-2">
                            <span>数据库服务器</span>
                            <span class="<?= $installedState['server_ok'] ? 'text-emerald-600' : 'text-rose-600' ?> font-semibold"><?= $installedState['server_ok'] ? '连接正常' : '连接失败' ?></span>
                        </div>
                        <div class="flex items-center justify-between rounded-xl bg-white px-3 py-2">
                            <span>目标数据库</span>
                            <span class="<?= $installedState['database_exists'] ? 'text-emerald-600' : 'text-amber-600' ?> font-semibold"><?= $installedState['database_exists'] ? '已存在' : '未创建' ?></span>
                        </div>
                        <div class="flex items-center justify-between rounded-xl bg-white px-3 py-2">
                            <span>系统表结构</span>
                            <span class="<?= $installedState['installed'] ? 'text-emerald-600' : 'text-amber-600' ?> font-semibold"><?= $installedState['installed'] ? '已安装' : '未安装' ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($statusSummary !== []): ?>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        <div class="font-semibold text-slate-900">最近一次检测结果</div>
                        <ul class="mt-3 space-y-2">
                            <li>服务器连接：<?= $statusSummary['server_ok'] ? '正常' : '失败' ?></li>
                            <li>数据库存在：<?= $statusSummary['database_exists'] ? '是' : '否' ?></li>
                            <li>当前已安装：<?= $statusSummary['installed'] ? '是' : '否' ?></li>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-7 text-amber-900">
                    <div class="font-semibold">安装说明</div>
                    <p class="mt-2">1. 先填写数据库信息并测试连接。</p>
                    <p>2. 首次安装时，如数据库尚未创建，可勾选自动创建数据库。</p>
                    <p>3. 安装完成后会自动写入配置文件，并创建首个系统管理员账号。</p>
                </div>
            </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white p-7 shadow-[0_18px_50px_rgba(15,23,42,0.08)]">
            <?php if ($flashSuccess !== ''): ?>
                <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError !== ''): ?>
                <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($flashError) ?></div>
            <?php endif; ?>

            <form method="post" class="space-y-8">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

                <section>
                    <div class="mb-4">
                        <h2 class="text-xl font-semibold text-slate-900">数据库配置</h2>
                        <p class="mt-1 text-sm text-slate-500">填写部署服务器实际使用的数据库连接信息。</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <?= render_input('host', '数据库地址', $form['host']) ?>
                        <?= render_input('port', '端口', $form['port']) ?>
                        <?= render_input('database', '数据库名称', $form['database']) ?>
                        <?= render_input('charset', '字符集', $form['charset']) ?>
                        <?= render_input('username', '数据库账号', $form['username']) ?>
                        <?= render_input('password', '数据库密码', $form['password'], 'password') ?>
                    </div>
                    <label class="mt-4 flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        <input type="checkbox" name="auto_create_database" value="1" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" <?= $form['auto_create_database'] ? 'checked' : '' ?>>
                        <span>如果数据库不存在，安装时自动创建数据库</span>
                    </label>
                </section>

                <section>
                    <div class="mb-4">
                        <h2 class="text-xl font-semibold text-slate-900">首个系统管理员账号</h2>
                        <p class="mt-1 text-sm text-slate-500">首次安装时由你自行配置系统管理员账号和密码。</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <?= render_input('admin_username', '系统管理员账号', $form['admin_username']) ?>
                        <?= render_input('admin_display_name', '显示名称', $form['admin_display_name']) ?>
                        <?= render_input('admin_password', '系统管理员密码', $form['admin_password'], 'password') ?>
                        <?= render_input('admin_password_confirm', '确认密码', $form['admin_password_confirm'], 'password') ?>
                    </div>
                </section>

                <div class="flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-6">
                    <button type="submit" name="action" value="test_connection" class="inline-flex h-12 items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">测试连接</button>
                    <button type="submit" name="action" value="run_install" class="inline-flex h-12 items-center justify-center rounded-2xl bg-blue-600 px-6 text-sm font-semibold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700">执行安装</button>
                    <a href="index.php" class="inline-flex h-12 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-semibold text-white transition hover:bg-slate-800">进入系统</a>
                </div>
            </form>
        </section>
    </div>
</main>
</body>
</html>
<?php

function build_db_config(array $form): array
{
    return [
        'host' => trim((string) $form['host']),
        'port' => (int) $form['port'],
        'database' => trim((string) $form['database']),
        'username' => trim((string) $form['username']),
        'password' => (string) $form['password'],
        'charset' => trim((string) $form['charset']) !== '' ? trim((string) $form['charset']) : 'utf8mb4',
        'auto_create_database' => !empty($form['auto_create_database']),
    ];
}

function inspect_current_installation(array $dbConfig): array
{
    $state = [
        'server_ok' => false,
        'database_exists' => false,
        'installed' => false,
    ];

    try {
        $serverPdo = Database::connectServer($dbConfig);
        $state['server_ok'] = true;

        $database = trim((string) ($dbConfig['database'] ?? ''));
        if ($database !== '' && Database::databaseExists($database, $serverPdo)) {
            $state['database_exists'] = true;
            $dbPdo = Database::connect($dbConfig);
            $state['installed'] = Database::isInstalled($dbPdo);
        }
    } catch (Throwable $exception) {
        return $state;
    }

    return $state;
}

function test_database_connection(array $dbConfig): array
{
    $serverPdo = Database::connectServer($dbConfig);
    $databaseExists = Database::databaseExists((string) $dbConfig['database'], $serverPdo);
    $installed = false;

    if ($databaseExists) {
        $dbPdo = Database::connect($dbConfig);
        $installed = Database::isInstalled($dbPdo);
    }

    return [
        'server_ok' => true,
        'database_exists' => $databaseExists,
        'installed' => $installed,
    ];
}

function render_input(string $name, string $label, string $value, string $type = 'text'): string
{
    return sprintf(
        '<label class="block"><span class="mb-2 block text-sm font-medium text-slate-700">%s</span><input type="%s" name="%s" value="%s" class="h-12 w-full rounded-2xl border border-slate-300 bg-white px-4 text-sm text-slate-800 shadow-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"></label>',
        e($label),
        e($type),
        e($name),
        e($value)
    );
}

function write_config_file(array $dbConfig): void
{
    $configPath = __DIR__ . '/config/app.php';
    $content = <<<PHP
<?php

return [
    'app_name' => '技术支撑事业部项目管理平台',
    'timezone' => 'Asia/Shanghai',
    'base_url' => '',
    'db' => [
        'host' => %s,
        'port' => %d,
        'database' => %s,
        'username' => %s,
        'password' => %s,
        'charset' => %s,
        'auto_create_database' => %s,
    ],
    'storage' => [
        'uploads' => __DIR__ . '/../storage/uploads',
    ],
    'uploads' => [
        'max_size' => 20 * 1024 * 1024,
        'allowed_extensions' => [
            'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp',
            'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'zip', 'rar',
        ],
    ],
    'roles' => [
        'admin' => '系统管理员',
        'editor' => '普通用户',
    ],
    'default_icons' => [
        'dashboard' => 'fa-solid fa-chart-line',
        'projects' => 'fa-solid fa-diagram-project',
        'personnel' => 'fa-solid fa-users',
        'attendance' => 'fa-solid fa-calendar-check',
        'documents' => 'fa-solid fa-folder-open',
        'reports' => 'fa-solid fa-file-export',
        'settings' => 'fa-solid fa-gear',
        'logs' => 'fa-solid fa-clock-rotate-left',
    ],
];
PHP;

    $rendered = sprintf(
        $content,
        var_export((string) $dbConfig['host'], true),
        (int) $dbConfig['port'],
        var_export((string) $dbConfig['database'], true),
        var_export((string) $dbConfig['username'], true),
        var_export((string) $dbConfig['password'], true),
        var_export((string) $dbConfig['charset'], true),
        !empty($dbConfig['auto_create_database']) ? 'true' : 'false'
    );

    if (file_put_contents($configPath, $rendered) === false) {
        throw new RuntimeException('配置文件写入失败，请检查 config 目录权限。');
    }
}
