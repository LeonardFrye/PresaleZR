<?php
$background = trim((string) ($settings['appearance_background'] ?? ''));
$subtitle = trim((string) ($settings['brand_subtitle'] ?? ''));
$flashSuccess = flash('success');
$flashError = flash('error');
$userInitial = isset($currentUser)
    ? (function_exists('mb_substr') ? mb_substr((string) $currentUser['display_name'], 0, 1) : substr((string) $currentUser['display_name'], 0, 1))
    : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(app_config('app_name')) ?> - <?= e($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    <?php if (isset($currentUser)): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
    <?php endif; ?>
</head>
<body class="<?= isset($currentUser) ? 'bg-gray-50 min-h-screen flex flex-col' : '' ?>" style="<?= $background !== '' ? 'background-image:url(' . e($background) . '); background-size:cover; background-position:center;' : '' ?>">
<?php if (!isset($currentUser)): ?>
    <main class="login-shell">
        <?php if ($flashSuccess || $flashError): ?>
            <div class="login-flash">
                <?php if ($flashSuccess): ?><div class="flash success"><?= e($flashSuccess) ?></div><?php endif; ?>
                <?php if ($flashError): ?><div class="flash error"><?= e($flashError) ?></div><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php require $contentTemplate; ?>
    </main>
<?php else: ?>
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="w-full px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <button id="sidebar-toggle" class="p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="ml-4 flex items-center text-left">
                        <i class="fas fa-project-diagram text-blue-600 text-2xl mr-3"></i>
                        <div>
                            <h1 class="text-xl font-bold text-gray-900"><?= e(app_config('app_name')) ?> V1.0</h1>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right hidden md:block">
                        <div class="text-sm text-gray-500">当前时间</div>
                        <div id="live-clock" class="text-sm font-semibold text-gray-700"><?= e(date('Y-m-d H:i:s')) ?></div>
                    </div>
                    <div class="flex items-center">
                        <div class="h-8 w-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-semibold"><?= e($userInitial) ?></div>
                        <div class="ml-2">
                            <div class="text-sm font-medium text-gray-700"><?= e($currentUser['display_name']) ?></div>
                            <div class="text-xs text-gray-500"><?= e(role_label($currentUser['role'])) ?></div>
                        </div>
                    </div>
                    <a class="text-sm text-blue-600 hover:text-blue-800" href="index.php?action=logout">退出</a>
                </div>
            </div>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <aside id="sidebar" class="sidebar bg-white w-64 shadow-md border-r border-gray-200 flex-shrink-0 overflow-y-auto">
            <div class="px-4 py-5">
                <div class="space-y-1">
                    <?php
                    $navItems = [
                        'dashboard' => '数据概览',
                        'projects' => '项目管理',
                        'personnel' => '人员绩效',
                        'attendance' => '出勤管理',
                        'documents' => '项目文件',
                        'reports' => '统计报表',
                    ];
                    foreach ($navItems as $key => $label):
                    ?>
                        <a href="index.php?view=<?= e($key) ?>#<?= e($key) ?>" class="nav-link flex items-center px-4 py-3 <?= $view === $key ? 'active-nav text-blue-600 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>" data-nav-target="<?= e($key) ?>">
                            <i class="<?= e($icons[$key] ?? 'fa-solid fa-circle') ?> w-5 h-5 mr-3"></i>
                            <span><?= e($label) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="mt-8">
                    <h3 class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">系统管理</h3>
                    <div class="mt-3 space-y-1">
                        <?php if ($auth->can('manage_settings')): ?>
                            <a href="index.php?view=settings#settings" class="nav-link flex items-center px-4 py-3 <?= $view === 'settings' ? 'active-nav text-blue-600 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>" data-nav-target="settings">
                                <i class="<?= e($icons['settings'] ?? 'fa-solid fa-cog') ?> w-5 h-5 mr-3"></i>
                                <span>系统设置</span>
                            </a>
                        <?php endif; ?>
                        <a href="index.php?view=logs#logs" class="nav-link flex items-center px-4 py-3 <?= $view === 'logs' ? 'active-nav text-blue-600 font-medium' : 'text-gray-600 hover:bg-gray-50' ?>" data-nav-target="logs">
                            <i class="<?= e($icons['logs'] ?? 'fa-solid fa-history') ?> w-5 h-5 mr-3"></i>
                            <span>操作日志</span>
                        </a>
                    </div>
                </div>
            </div>
        </aside>
        <div id="sidebar-overlay" class="sidebar-overlay hidden" aria-hidden="true"></div>

        <main id="main-content" class="content-area flex-1 overflow-y-auto bg-gray-50 p-6" data-current-view="<?= e($view) ?>">
            <?php if ($flashSuccess || $flashError): ?>
                <div class="flash-stack mb-6">
                    <?php if ($flashSuccess): ?><div class="flash success"><?= e($flashSuccess) ?></div><?php endif; ?>
                    <?php if ($flashError): ?><div class="flash error"><?= e($flashError) ?></div><?php endif; ?>
                </div>
            <?php endif; ?>

            <?php require $contentTemplate; ?>
        </main>
    </div>

    <script>
        window.dashboardPayload = <?= json_encode($dashboard ?? [], JSON_UNESCAPED_UNICODE) ?>;
        window.activeView = <?= json_encode($view, JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="assets/js/app.js"></script>
<?php endif; ?>
</body>
</html>
