<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\AnalyticsService;
use App\Services\AuthService;
use App\Services\DocumentService;
use App\Services\ExcelExporter;
use App\Services\ExcelImportService;
use App\Services\PersonnelPerformanceExporter;
use App\Services\PersonnelPerformanceService;
use App\Services\ProjectService;
use App\Support\Database;

$pdo = db();
if (!Database::isInstalled($pdo)) {
    redirect('install.php');
}
function delete_user(array $payload, array $operator, string $ipAddress, AuthService $auth): void
{
    $userId = (int) ($payload['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new RuntimeException('Please select a user to delete.');
    }

    if ($userId === (int) $operator['id']) {
        throw new RuntimeException('You cannot delete the currently logged-in administrator.');
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $target = $stmt->fetch();
    if (!$target) {
        throw new RuntimeException('User account not found.');
    }

    if (($target['role'] ?? '') === 'admin') {
        $adminCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($adminCount <= 1) {
            throw new RuntimeException('At least one administrator account must remain.');
        }
    }

    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    $auth->log((int) $operator['id'], 'delete', 'users', 'Delete user: ' . ($target['username'] ?? ''), $ipAddress, ['user_id' => $userId]);
}

$auth = new AuthService($pdo);
$projectService = new ProjectService($pdo, $auth);
$documentService = new DocumentService($pdo, $auth, $projectService);
$excelImportService = new ExcelImportService($pdo, $projectService, $auth);
$personnelPerformanceService = new PersonnelPerformanceService($pdo, $projectService, $auth);
$analytics = new AnalyticsService($projectService);
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'logout') {
    $auth->logout($clientIp);
    flash('success', 'Logged out successfully.');
    redirect('index.php');
}

function project_filters_from_request(array $source): array
{
    return [
        'feedback_tag' => trim((string) ($source['feedback_tag'] ?? '')),
        'person' => trim((string) ($source['person'] ?? '')),
        'month' => trim((string) ($source['month'] ?? '')),
        'sales' => trim((string) ($source['sales'] ?? '')),
        'tag' => trim((string) ($source['tag'] ?? '')),
        'page' => max((int) ($source['page'] ?? 1), 1),
    ];
}

function build_projects_url(array $params = []): string
{
    $query = ['view' => 'projects'];
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }

        $query[$key] = $value;
    }

    return 'index.php?' . http_build_query($query) . '#projects';
}

$currentUser = $auth->user();

if (!$currentUser && is_post() && $action === 'login') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($auth->attempt($username, $password, $clientIp)) {
        flash('success', 'Login successful.');
        redirect('index.php');
    }

    flash('error', 'Invalid username or password, or the account is disabled.');
    redirect('index.php');
}

if (!$currentUser) {
    $pageTitle = '璐﹀彿鐧诲綍';
    $contentTemplate = __DIR__ . '/views/login.php';
    require __DIR__ . '/views/layout.php';
    exit;
}

$currentUser = $auth->requireLogin();

if ($action === 'download_document' || $action === 'preview_document') {
    $documentService->stream((int) ($_GET['id'] ?? 0), $action === 'preview_document');
}

if ($action === 'export_report') {
    if (!$auth->can('export_reports')) {
        permission_denied();
    }
    $projects = $projectService->list([
        'feedback_tag' => $_GET['feedback_tag'] ?? '',
        'person' => $_GET['person'] ?? '',
        'month' => $_GET['month'] ?? '',
        'sales' => $_GET['sales'] ?? '',
        'tag' => $_GET['tag'] ?? '',
    ]);
    (new ExcelExporter())->download($projects);
}

if ($action === 'export_personnel_performance') {
    if (!$auth->can('export_reports')) {
        permission_denied();
    }

    $period = trim((string) ($_GET['personnel_period'] ?? 'month'));
    $anchorDate = trim((string) ($_GET['personnel_date'] ?? date('Y-m-d')));
    $dataset = $personnelPerformanceService->exportRows($period, $anchorDate);
    $auth->log((int) $currentUser['id'], 'export', 'personnel', '瀵煎嚭浜哄憳缁╂晥', $clientIp, [
        'period' => $dataset['period'],
        'anchor_date' => $dataset['anchor_date'],
    ]);
    (new PersonnelPerformanceExporter())->download($dataset);
}

if ($action === 'export_personnel_detail_performance') {
    if (!$auth->can('export_reports')) {
        permission_denied();
    }

    $period = trim((string) ($_GET['personnel_period'] ?? 'month'));
    $anchorDate = trim((string) ($_GET['personnel_date'] ?? date('Y-m-d')));
    $personName = trim((string) ($_GET['person_name'] ?? ''));
    $dataset = $personnelPerformanceService->exportRows($period, $anchorDate, $personName);
    $auth->log((int) $currentUser['id'], 'export', 'personnel', '瀵煎嚭涓汉缁╂晥', $clientIp, [
        'period' => $dataset['period'],
        'anchor_date' => $dataset['anchor_date'],
        'person_name' => $dataset['person_name'],
    ]);
    (new PersonnelPerformanceExporter())->download($dataset);
}

if (is_post()) {
    verify_csrf();

    try {
        if ($action === 'save_project') {
            $projectId = (int) ($_POST['project_id'] ?? 0);
            if ($projectId > 0) {
                if (!$auth->can('manage_projects')) {
                    permission_denied();
                }
            } else {
                if (!$auth->can('create_projects')) {
                    permission_denied();
                }
            }

            $projectId = $projectService->save($_POST, $currentUser, $clientIp);

            if (!empty($_FILES['receipt_file']['name'])) {
                $documentService->upload($_FILES['receipt_file'], $projectId, 'receipt', '鎶€鏈畬鎴愬洖鎵у崟', $currentUser, $clientIp);
            }
            if (!empty($_FILES['attachment_file']['name'])) {
                $documentService->upload(
                    $_FILES['attachment_file'],
                    $projectId,
                    'attachment',
                    trim((string) ($_POST['attachment_description'] ?? '椤圭洰闄勪欢')),
                    $currentUser,
                    $clientIp
                );
            }

            flash('success', 'Project saved successfully.');
            redirect('index.php?view=projects&edit=' . $projectId . '#projects');
        }

        if ($action === 'delete_project') {
            if (!$auth->can('manage_projects')) {
                permission_denied();
            }

            $projectService->delete((int) ($_POST['project_id'] ?? 0), $currentUser, $clientIp);
            flash('success', 'Project deleted successfully.');
            redirect(build_projects_url(project_filters_from_request($_POST)));
        }

        if ($action === 'batch_delete_projects') {
            if (!$auth->can('manage_projects')) {
                permission_denied();
            }
            if (trim((string) ($_POST['batch_action'] ?? '')) !== 'delete') {
                throw new RuntimeException('Please select a valid batch action.');
            }

            $selectedIds = array_map('intval', (array) ($_POST['project_ids'] ?? []));
            $selectedIds = array_values(array_filter($selectedIds, static function (int $id): bool {
                return $id > 0;
            }));

            if ($selectedIds === []) {
                throw new RuntimeException('Please select at least one project.');
            }

            $deletedCount = $projectService->bulkDelete($selectedIds, $currentUser, $clientIp);
            flash('success', sprintf('Deleted %d project(s).', $deletedCount));
            redirect(build_projects_url(project_filters_from_request($_POST)));
        }

        if ($action === 'import_projects') {
            if (!$auth->can('manage_projects')) {
                permission_denied();
            }

            $summary = $excelImportService->import($_FILES['import_excel_file'] ?? [], $currentUser, $clientIp);
            flash('success', sprintf('Excel import completed: inserted %d row(s), skipped %d row(s).', $summary['inserted'], $summary['skipped']));

            if ($summary['errors'] !== []) {
                $errorMessage = implode('; ', array_slice($summary['errors'], 0, 5));
                if (count($summary['errors']) > 5) {
                    $errorMessage .= sprintf('; and %d more error(s). Please check the source file.', count($summary['errors']) - 5);
                }
                flash('error', $errorMessage);
            }

            redirect('index.php?view=projects#projects');
        }

        if ($action === 'upload_document') {
            if (!$auth->can('manage_documents')) {
                permission_denied();
            }

            $documentService->upload(
                $_FILES['document_file'] ?? [],
                (int) ($_POST['project_id'] ?? 0),
                (string) ($_POST['category'] ?? 'attachment'),
                trim((string) ($_POST['description'] ?? '')),
                $currentUser,
                $clientIp
            );

            flash('success', 'File uploaded successfully.');
            redirect('index.php?view=documents&project_id=' . (int) ($_POST['project_id'] ?? 0) . '#documents');
        }

        if ($action === 'save_settings') {
            if (!$auth->can('manage_settings')) {
                permission_denied();
            }

            save_settings($_POST, $currentUser, $clientIp, $auth);
            flash('success', 'Settings updated successfully.');
            redirect('index.php?view=settings#settings');
        }

        if ($action === 'save_personnel_performance') {
            if (!$auth->can('manage_performance')) {
                permission_denied();
            }

            $personName = trim((string) ($_POST['person_name'] ?? ''));
            $period = trim((string) ($_POST['personnel_period'] ?? 'month'));
            $anchorDate = trim((string) ($_POST['personnel_date'] ?? date('Y-m-d')));
            $personnelPerformanceService->saveScores($personName, (array) ($_POST['scores'] ?? []), $currentUser, $clientIp);
            flash('success', 'Performance saved successfully.');
            redirect('index.php?view=personnel&personnel_period=' . urlencode($period) . '&personnel_date=' . urlencode($anchorDate) . '&personnel_person=' . urlencode($personName) . '#personnel');
        }

        if ($action === 'save_attendance_override') {
            if (!$auth->can('manage_performance')) {
                permission_denied();
            }

            $personName = trim((string) ($_POST['person_name'] ?? ''));
            $workDate = trim((string) ($_POST['work_date'] ?? ''));
            $status = trim((string) ($_POST['attendance_status'] ?? ''));
            $analytics->saveAttendanceOverride($personName, $workDate, $status, (int) $currentUser['id']);
            $auth->log((int) $currentUser['id'], 'update', 'attendance', 'Update attendance status: ' . $personName . ' ' . $workDate, $clientIp, [
                'person_name' => $personName,
                'work_date' => $workDate,
                'status' => $status === '' ? 'auto' : $status,
            ]);
            flash('success', 'Attendance status updated successfully.');
            redirect('index.php?view=attendance#attendance');
        }

        if ($action === 'save_user') {
            if (!$auth->can('manage_users')) {
                permission_denied();
            }

            save_user($_POST, $currentUser, $clientIp, $auth);
            flash('success', 'User saved successfully.');
            redirect('index.php?view=settings#settings');
        }

        if ($action === 'delete_user') {
            if (!$auth->can('manage_users')) {
                permission_denied();
            }

            delete_user($_POST, $currentUser, $clientIp, $auth);
            flash('success', 'User deleted successfully.');
            redirect('index.php?view=settings#settings');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }
}

$settings = $analytics->settings();
$customIcons = json_decode((string) ($settings['module_icons'] ?? '{}'), true);
$icons = array_merge(app_config('default_icons', []), is_array($customIcons) ? $customIcons : []);

$view = $_GET['view'] ?? 'dashboard';
$allowedViews = ['dashboard', 'projects', 'personnel', 'attendance', 'documents', 'reports', 'settings', 'logs'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'dashboard';
}

if ($view === 'settings' && !$auth->can('manage_settings')) {
    permission_denied();
}
if ($view === 'logs' && !$auth->can('view_logs')) {
    permission_denied();
}
if ($view === 'projects' && !$auth->can('view_projects')) {
    permission_denied();
}
if ($view === 'documents' && !$auth->can('manage_documents')) {
    permission_denied();
}
if ($view === 'reports' && !$auth->can('view_reports')) {
    permission_denied();
}
if ($view === 'attendance' && !$auth->can('view_attendance')) {
    permission_denied();
}
if ($view === 'personnel' && !$auth->can('view_personnel')) {
    permission_denied();
}

$filters = [
    'feedback_tag' => trim((string) ($_GET['feedback_tag'] ?? '')),
    'person' => trim((string) ($_GET['person'] ?? '')),
    'month' => trim((string) ($_GET['month'] ?? '')),
    'sales' => trim((string) ($_GET['sales'] ?? '')),
    'tag' => trim((string) ($_GET['tag'] ?? '')),
];
$projectPage = max((int) ($_GET['page'] ?? 1), 1);
$personnelPeriod = trim((string) ($_GET['personnel_period'] ?? 'month'));
$personnelAnchorDate = trim((string) ($_GET['personnel_date'] ?? date('Y-m-d')));
$selectedPersonnelName = trim((string) ($_GET['personnel_person'] ?? ''));

$projectPagination = $projectService->paginate($filters, $projectPage, 20);
$projects = $projectPagination['items'];
$projectListQuery = array_merge(['view' => 'projects'], $filters, ['page' => $projectPagination['page']]);
$projectEditId = (int) ($_GET['edit'] ?? 0);
$editingProject = $auth->can('manage_projects') && $projectEditId > 0 ? $projectService->find($projectEditId) : null;
$projectDocuments = $editingProject ? $projectService->documents((int) $editingProject['id']) : [];
$projectPreviewId = (int) ($_GET['preview'] ?? 0);
$previewingProject = $projectPreviewId > 0 ? $projectService->find($projectPreviewId) : null;
$previewProjectDocuments = $previewingProject ? $projectService->documents((int) $previewingProject['id']) : [];
$dashboard = $analytics->dashboard();
$attendanceBoard = $analytics->attendanceBoard();
$personnelBoard = $personnelPerformanceService->board($personnelPeriod, $personnelAnchorDate);
$personnelDetail = $selectedPersonnelName !== ''
    ? $personnelPerformanceService->detail($selectedPersonnelName, $personnelBoard['period'], $personnelBoard['anchor_date'])
    : null;
$documents = $documentService->list([
    'project_id' => $_GET['project_id'] ?? '',
    'category' => $_GET['category'] ?? '',
    'keyword' => $_GET['keyword'] ?? '',
]);
$logs = $analytics->logs($currentUser, ['module' => $_GET['module'] ?? '']);
$users = $analytics->users();
$editingUserId = (int) ($_GET['user_edit'] ?? 0);
$editingUser = null;
foreach ($users as $userItem) {
    if ((int) ($userItem['id'] ?? 0) === $editingUserId) {
        $editingUser = $userItem;
        break;
    }
}
$regions = $projectService->regions();

$pageTitleMap = [
    'dashboard' => '鏁版嵁姒傝',
    'projects' => '椤圭洰绠＄悊',
    'personnel' => '浜哄憳缁╂晥',
    'attendance' => '鍑哄嫟绠＄悊',
    'documents' => '椤圭洰鏂囦欢',
    'reports' => '缁熻鎶ヨ〃',
    'settings' => '绯荤粺璁剧疆',
    'logs' => '鎿嶄綔鏃ュ織',
];

$pageTitle = $pageTitleMap[$view];
$contentTemplate = __DIR__ . '/views/app.php';

require __DIR__ . '/views/layout.php';

function save_settings(array $payload, array $user, string $ipAddress, AuthService $auth): void
{
    $icons = [];
    foreach (app_config('default_icons', []) as $key => $defaultIcon) {
        $icons[$key] = trim((string) ($payload['icon_' . $key] ?? $defaultIcon));
    }

    $items = [
        'appearance_background' => trim((string) ($payload['appearance_background'] ?? '')),
        'brand_subtitle' => trim((string) ($payload['brand_subtitle'] ?? '')),
        'module_icons' => json_encode($icons, JSON_UNESCAPED_UNICODE),
    ];

    $stmt = db()->prepare('REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)');
    foreach ($items as $key => $value) {
        $stmt->execute([$key, $value]);
    }

    $auth->log((int) $user['id'], 'update', 'settings', '鏇存柊绯荤粺璁剧疆', $ipAddress, $items);
}

function save_user(array $payload, array $operator, string $ipAddress, AuthService $auth): void
{
    $userId = (int) ($payload['user_id'] ?? 0);
    $username = trim((string) ($payload['username'] ?? ''));
    $displayName = trim((string) ($payload['display_name'] ?? ''));
    $role = (string) ($payload['role'] ?? 'editor');
    $password = (string) ($payload['password'] ?? '');
    $isActive = 1;

    if ($username === '' || $displayName === '') {
        throw new RuntimeException('Username and display name are required.');
    }
    if (!in_array($role, ['admin', 'editor'], true)) {
        throw new RuntimeException('Invalid role.');
    }

    if ($userId > 0) {
        $existing = db()->prepare('SELECT * FROM users WHERE id = ?');
        $existing->execute([$userId]);
        $current = $existing->fetch();
        if (!$current) {
            throw new RuntimeException('User account not found.');
        }

        $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : $current['password_hash'];
        $stmt = db()->prepare('UPDATE users SET username = ?, display_name = ?, role = ?, password_hash = ?, is_active = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$username, $displayName, $role, $passwordHash, $isActive, $userId]);
        $auth->log((int) $operator['id'], 'update', 'users', 'Update user: ' . $username, $ipAddress, ['user_id' => $userId]);
        return;
    }

    if ($password === '') {
        throw new RuntimeException('A password is required when creating a new user.');
    }

    $stmt = db()->prepare('INSERT INTO users (username, password_hash, display_name, role, is_active) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $displayName, $role, $isActive]);
    $auth->log((int) $operator['id'], 'create', 'users', 'Create user: ' . $username, $ipAddress, ['role' => $role]);
}
