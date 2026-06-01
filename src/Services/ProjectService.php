<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class ProjectService
{
    public const STATUS_SALES_TASK = 'sales_task';
    public const STATUS_MANAGER_REVIEW = 'manager_review';
    public const STATUS_TECH_EXECUTION = 'tech_execution';

    public const FEEDBACK_NORMAL = 'normal';
    public const FEEDBACK_BONUS = 'bonus';
    public const FEEDBACK_COMPLAINT = 'complaint';

    private $pdo;
    private $auth;

    public function __construct(PDO $pdo, AuthService $auth)
    {
        $this->pdo = $pdo;
        $this->auth = $auth;
        $this->ensureWorkOrderStatusColumn();
        $this->ensureFeedbackTagColumn();
        $this->ensureTemplateColumns();
        $this->ensureDatetimeColumns();
    }

    public function list(array $filters = []): array
    {
        return $this->fetchProjects($filters);
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max($page, 1);
        $perPage = max($perPage, 1);
        [$whereSql, $params] = $this->buildFilterClause($filters);

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM projects p WHERE 1=1' . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $totalPages = max((int) ceil($total / $perPage), 1);
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;

        return [
            'items' => $this->fetchProjects($filters, $perPage, $offset, 'personnel'),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    public function listByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function (int $id): bool {
            return $id > 0;
        })));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT p.*, d.original_name AS receipt_name, u.display_name AS creator_name
                FROM projects p
                LEFT JOIN documents d ON d.id = p.receipt_document_id
                LEFT JOIN users u ON u.id = p.created_by
                WHERE p.id IN (' . $placeholders . ')';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ids);
        $projects = $stmt->fetchAll() ?: [];
        $projects = array_map([$this, 'decorateProject'], $projects);

        $orderMap = array_flip($ids);
        usort($projects, static function (array $left, array $right) use ($orderMap): int {
            $leftOrder = $orderMap[(int) ($left['id'] ?? 0)] ?? PHP_INT_MAX;
            $rightOrder = $orderMap[(int) ($right['id'] ?? 0)] ?? PHP_INT_MAX;

            return $leftOrder <=> $rightOrder;
        });

        return $projects;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projects WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $project = $stmt->fetch();

        return $project ? $this->decorateProject($project) : null;
    }

    public function save(array $data, array $user, string $ipAddress): int
    {
        $projectId = (int) ($data['project_id'] ?? 0);
        $startAt = normalize_datetime_value((string) ($data['start_at'] ?? $data['start_date'] ?? ''));
        $endAt = normalize_datetime_value((string) ($data['end_at'] ?? $data['end_date'] ?? ''));
        $startDate = $startAt !== '' ? date('Y-m-d', strtotime($startAt)) : '';
        $endDate = $endAt !== '' ? date('Y-m-d', strtotime($endAt)) : '';
        $durationDays = $this->durationDays($startDate, $endDate);
        $projectHours = $this->projectHours($startAt, $endAt);

        $payload = [
            'project_type' => trim((string) ($data['project_type'] ?? '')),
            'project_name' => trim((string) ($data['project_name'] ?? '')),
            'project_region' => trim((string) ($data['project_region'] ?? '')),
            'project_priority' => trim((string) ($data['project_priority'] ?? '普通')),
            'project_sales' => trim((string) ($data['project_sales'] ?? '')),
            'support_department' => trim((string) ($data['support_department'] ?? '技术支撑事业部')),
            'cross_department' => trim((string) ($data['cross_department'] ?? '')),
            'support_role' => trim((string) ($data['support_role'] ?? '')),
            'support_personnel' => trim((string) ($data['support_personnel'] ?? '')),
            'start_at' => $startAt,
            'end_at' => $endAt,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'duration_days' => $durationDays,
            'project_hours' => $projectHours,
            'task_summary' => trim((string) ($data['task_summary'] ?? '')),
            'completion_feedback' => trim((string) ($data['completion_feedback'] ?? '')),
            'transfer_flag' => empty($data['transfer_flag']) ? 0 : 1,
            'completion_flag' => empty($data['completion_flag']) ? 0 : 1,
            'work_order_status' => (string) ($data['work_order_status'] ?? self::STATUS_SALES_TASK),
            'feedback_tag' => (string) ($data['feedback_tag'] ?? self::FEEDBACK_NORMAL),
        ];

        $this->validate($payload);

        if ($projectId > 0) {
            $sql = 'UPDATE projects SET
                    project_type = ?, project_name = ?, project_region = ?, project_priority = ?, project_sales = ?,
                    support_department = ?, cross_department = ?, support_role = ?, support_personnel = ?,
                    start_at = ?, end_at = ?, start_date = ?, end_date = ?, duration_days = ?, project_hours = ?,
                    task_summary = ?, completion_feedback = ?, transfer_flag = ?, completion_flag = ?,
                    work_order_status = ?, feedback_tag = ?, updated_by = ?, updated_at = NOW()
                    WHERE id = ?';
            $params = [
                $payload['project_type'],
                $payload['project_name'],
                $payload['project_region'],
                $payload['project_priority'],
                $payload['project_sales'],
                $payload['support_department'],
                $payload['cross_department'],
                $payload['support_role'],
                $payload['support_personnel'],
                $payload['start_at'],
                $payload['end_at'],
                $payload['start_date'],
                $payload['end_date'],
                $payload['duration_days'],
                $payload['project_hours'],
                $payload['task_summary'],
                $payload['completion_feedback'],
                $payload['transfer_flag'],
                $payload['completion_flag'],
                $payload['work_order_status'],
                $payload['feedback_tag'],
                (int) $user['id'],
                $projectId,
            ];
            $this->pdo->prepare($sql)->execute($params);
            $this->auth->log((int) $user['id'], 'update', 'projects', '更新项目：' . $payload['project_name'], $ipAddress, [
                'project_id' => $projectId,
            ]);

            return $projectId;
        }

        $sql = 'INSERT INTO projects (
                project_type, project_name, project_region, project_priority, project_sales,
                support_department, cross_department, support_role, support_personnel,
                start_at, end_at, start_date, end_date, duration_days, project_hours,
                task_summary, completion_feedback, transfer_flag, completion_flag,
                work_order_status, feedback_tag, created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $params = [
            $payload['project_type'],
            $payload['project_name'],
            $payload['project_region'],
            $payload['project_priority'],
            $payload['project_sales'],
            $payload['support_department'],
            $payload['cross_department'],
            $payload['support_role'],
            $payload['support_personnel'],
            $payload['start_at'],
            $payload['end_at'],
            $payload['start_date'],
            $payload['end_date'],
            $payload['duration_days'],
            $payload['project_hours'],
            $payload['task_summary'],
            $payload['completion_feedback'],
            $payload['transfer_flag'],
            $payload['completion_flag'],
            $payload['work_order_status'],
            $payload['feedback_tag'],
            (int) $user['id'],
            (int) $user['id'],
        ];
        $this->pdo->prepare($sql)->execute($params);
        $projectId = (int) $this->pdo->lastInsertId();
        $this->auth->log((int) $user['id'], 'create', 'projects', '新增项目：' . $payload['project_name'], $ipAddress, [
            'project_id' => $projectId,
        ]);

        return $projectId;
    }

    public function delete(int $id, array $user, string $ipAddress): void
    {
        $project = $this->find($id);
        if (!$project) {
            return;
        }

        $this->pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
        $this->auth->log((int) $user['id'], 'delete', 'projects', '删除项目：' . $project['project_name'], $ipAddress, [
            'project_id' => $id,
        ]);
    }

    public function bulkDelete(array $ids, array $user, string $ipAddress): int
    {
        $deleted = 0;
        foreach ($ids as $id) {
            $projectId = (int) $id;
            if ($projectId <= 0) {
                continue;
            }

            if (!$this->find($projectId)) {
                continue;
            }

            $this->delete($projectId, $user, $ipAddress);
            $deleted++;
        }

        return $deleted;
    }

    public function regions(): array
    {
        $rows = $this->pdo->query('SELECT DISTINCT project_region FROM projects ORDER BY project_region')->fetchAll();

        return array_values(array_filter(array_column($rows ?: [], 'project_region')));
    }

    public function documents(int $projectId): array
    {
        $stmt = $this->pdo->prepare('SELECT d.*, u.display_name AS uploader_name
            FROM documents d
            LEFT JOIN users u ON u.id = d.uploaded_by
            WHERE d.project_id = ?
            ORDER BY d.category ASC, d.created_at DESC, d.id DESC');
        $stmt->execute([$projectId]);

        return $stmt->fetchAll() ?: [];
    }

    public function conflictMap(array $projects): array
    {
        $map = [];
        $count = count($projects);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $left = $projects[$i];
                $right = $projects[$j];
                if (!$this->datesOverlap((string) $left['start_date'], (string) $left['end_date'], (string) $right['start_date'], (string) $right['end_date'])) {
                    continue;
                }

                $shared = array_values(array_intersect(
                    support_people((string) $left['support_personnel']),
                    support_people((string) $right['support_personnel'])
                ));

                if ($shared === []) {
                    continue;
                }

                $sharedText = implode('、', $shared);
                $map[$left['id']] = [
                    'note' => sprintf('与项目【%s】存在人员冲突：%s', $right['project_name'], $sharedText),
                ];
                $map[$right['id']] = [
                    'note' => sprintf('与项目【%s】存在人员冲突：%s', $left['project_name'], $sharedText),
                ];
            }
        }

        return $map;
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_SALES_TASK => '销售发布任务',
            self::STATUS_MANAGER_REVIEW => '技术管理审核',
            self::STATUS_TECH_EXECUTION => '技术人员实施',
        ];
    }

    public static function feedbackTagOptions(): array
    {
        return [
            self::FEEDBACK_NORMAL => '正常',
            self::FEEDBACK_BONUS => '加分',
            self::FEEDBACK_COMPLAINT => '投诉',
        ];
    }

    private function fetchProjects(array $filters = [], ?int $limit = null, ?int $offset = null, string $sortMode = 'default'): array
    {
        [$whereSql, $params] = $this->buildFilterClause($filters);
        $orderSql = ' ORDER BY p.start_at DESC, p.id DESC';
        if ($sortMode === 'personnel') {
            $orderSql = ' ORDER BY p.support_personnel ASC, p.start_at DESC, p.id DESC';
        }

        $sql = 'SELECT p.*, d.original_name AS receipt_name, u.display_name AS creator_name
                FROM projects p
                LEFT JOIN documents d ON d.id = p.receipt_document_id
                LEFT JOIN users u ON u.id = p.created_by
                WHERE 1=1'
            . $whereSql
            . $orderSql;

        if ($limit !== null) {
            $sql .= ' LIMIT ' . max($limit, 1);
            if ($offset !== null && $offset > 0) {
                $sql .= ' OFFSET ' . $offset;
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $projects = $stmt->fetchAll() ?: [];
        $projects = array_map([$this, 'decorateProject'], $projects);

        $conflictMap = $this->conflictMap($projects);
        foreach ($projects as &$project) {
            $project['conflict_flag'] = isset($conflictMap[$project['id']]);
            $project['conflict_note'] = $conflictMap[$project['id']]['note'] ?? '';
        }
        unset($project);

        return $projects;
    }

    private function buildFilterClause(array $filters): array
    {
        $sql = '';
        $params = [];

        if (!empty($filters['person'])) {
            $sql .= ' AND p.support_personnel LIKE ?';
            $params[] = '%' . $filters['person'] . '%';
        }
        if (!empty($filters['month']) && preg_match('/^\d{4}-\d{2}$/', (string) $filters['month']) === 1) {
            $sql .= " AND DATE_FORMAT(p.start_date, '%Y-%m') = ?";
            $params[] = $filters['month'];
        }
        if (!empty($filters['sales'])) {
            $sql .= ' AND p.project_sales LIKE ?';
            $params[] = '%' . $filters['sales'] . '%';
        }
        if (!empty($filters['feedback_tag'])) {
            $sql .= ' AND p.feedback_tag = ?';
            $params[] = $filters['feedback_tag'];
        }
        if (!empty($filters['tag'])) {
            $sql .= ' AND p.work_order_status = ?';
            $params[] = $filters['tag'];
        }

        return [$sql, $params];
    }

    private function validate(array $payload): void
    {
        if (
            $payload['project_name'] === ''
            || $payload['project_region'] === ''
            || $payload['project_sales'] === ''
            || $payload['support_personnel'] === ''
            || $payload['task_summary'] === ''
        ) {
            throw new RuntimeException('请完整填写项目名称、项目区域、项目销售、支撑人员和工作任务。');
        }

        if (!in_array($payload['support_role'], ['售前', '实施'], true)) {
            throw new RuntimeException('支撑岗位仅支持“售前”或“实施”。');
        }

        if ($payload['start_at'] === '' || $payload['end_at'] === '') {
            throw new RuntimeException('开始时间和结束时间不能为空。');
        }

        if (strtotime($payload['end_at']) <= strtotime($payload['start_at'])) {
            throw new RuntimeException('结束时间必须晚于开始时间。');
        }

        if ((int) $payload['duration_days'] < 1) {
            throw new RuntimeException('项目工期至少需要 1 天。');
        }

        if ((float) $payload['project_hours'] < 0) {
            throw new RuntimeException('项目工时计算异常，请检查开始和结束时间。');
        }

        if (!in_array($payload['work_order_status'], array_keys(self::statusOptions()), true)) {
            throw new RuntimeException('工单标签状态不合法。');
        }

        if (!in_array($payload['feedback_tag'], array_keys(self::feedbackTagOptions()), true)) {
            throw new RuntimeException('反馈标签不合法。');
        }
    }

    private function durationDays(string $startDate, string $endDate): int
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        if (!$start || !$end || $end < $start) {
            return 0;
        }

        return (int) floor(($end - $start) / 86400) + 1;
    }

    private function projectHours(string $startAt, string $endAt): float
    {
        return round(project_workload_score($startAt, $endAt), 2);
    }

    private function datesOverlap(string $leftStart, string $leftEnd, string $rightStart, string $rightEnd): bool
    {
        return strtotime($leftStart) <= strtotime($rightEnd) && strtotime($rightStart) <= strtotime($leftEnd);
    }

    private function decorateProject(array $project): array
    {
        $project['project_type'] = (string) ($project['project_type'] ?? '');
        $project['project_priority'] = (string) ($project['project_priority'] ?? '普通');
        $project['support_department'] = (string) ($project['support_department'] ?? '技术支撑事业部');
        $project['cross_department'] = (string) ($project['cross_department'] ?? '');

        if (empty($project['start_at']) && !empty($project['start_date'])) {
            $project['start_at'] = $project['start_date'] . ' 09:00:00';
        }
        if (empty($project['end_at']) && !empty($project['end_date'])) {
            $project['end_at'] = $project['end_date'] . ' 18:00:00';
        }
        if (empty($project['start_date']) && !empty($project['start_at'])) {
            $project['start_date'] = date('Y-m-d', strtotime((string) $project['start_at']));
        }
        if (empty($project['end_date']) && !empty($project['end_at'])) {
            $project['end_date'] = date('Y-m-d', strtotime((string) $project['end_at']));
        }

        $project['duration_days'] = $this->durationDays((string) ($project['start_date'] ?? ''), (string) ($project['end_date'] ?? ''));
        $storedHours = isset($project['project_hours']) ? (float) $project['project_hours'] : 0.0;
        $computedHours = $this->projectHours((string) ($project['start_at'] ?? ''), (string) ($project['end_at'] ?? ''));
        $project['project_hours'] = $storedHours > 0 ? round($storedHours, 2) : $computedHours;

        return $project;
    }

    private function ensureWorkOrderStatusColumn(): void
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM projects LIKE 'work_order_status'");
        $column = $stmt ? $stmt->fetch() : false;
        if ($column) {
            return;
        }

        $this->pdo->exec(
            "ALTER TABLE projects
             ADD COLUMN work_order_status ENUM('sales_task','manager_review','tech_execution')
             NOT NULL DEFAULT 'sales_task' AFTER completion_flag"
        );
        $this->pdo->exec(
            "UPDATE projects
             SET work_order_status = 'tech_execution'
             WHERE support_personnel IS NOT NULL AND TRIM(support_personnel) <> ''"
        );
    }

    private function ensureFeedbackTagColumn(): void
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM projects LIKE 'feedback_tag'");
        $column = $stmt ? $stmt->fetch() : false;
        if ($column) {
            return;
        }

        $this->pdo->exec(
            "ALTER TABLE projects
             ADD COLUMN feedback_tag ENUM('normal','bonus','complaint')
             NOT NULL DEFAULT 'normal' AFTER work_order_status"
        );
    }

    private function ensureTemplateColumns(): void
    {
        $this->ensureColumn(
            'project_type',
            "ALTER TABLE projects ADD COLUMN project_type VARCHAR(50) NOT NULL DEFAULT '' AFTER id"
        );
        $this->ensureColumn(
            'project_priority',
            "ALTER TABLE projects ADD COLUMN project_priority VARCHAR(50) NOT NULL DEFAULT '普通' AFTER project_name"
        );
        $this->ensureColumn(
            'support_department',
            "ALTER TABLE projects ADD COLUMN support_department VARCHAR(100) NOT NULL DEFAULT '技术支撑事业部' AFTER project_sales"
        );
        $this->ensureColumn(
            'cross_department',
            "ALTER TABLE projects ADD COLUMN cross_department VARCHAR(100) NOT NULL DEFAULT '' AFTER support_department"
        );
        $this->ensureColumn(
            'project_hours',
            "ALTER TABLE projects ADD COLUMN project_hours DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER duration_days"
        );
    }

    private function ensureDatetimeColumns(): void
    {
        $this->ensureColumn(
            'start_at',
            "ALTER TABLE projects ADD COLUMN start_at DATETIME NULL AFTER support_personnel"
        );
        $this->ensureColumn(
            'end_at',
            "ALTER TABLE projects ADD COLUMN end_at DATETIME NULL AFTER start_at"
        );

        $this->pdo->exec(
            "UPDATE projects
             SET start_at = CONCAT(start_date, ' 09:00:00')
             WHERE start_at IS NULL AND start_date IS NOT NULL"
        );
        $this->pdo->exec(
            "UPDATE projects
             SET end_at = CONCAT(end_date, ' 18:00:00')
             WHERE end_at IS NULL AND end_date IS NOT NULL"
        );
        $this->pdo->exec(
            "UPDATE projects
             SET project_hours = duration_days
             WHERE (project_hours IS NULL OR project_hours = 0) AND duration_days IS NOT NULL"
        );
    }

    private function ensureColumn(string $columnName, string $sql): void
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM projects LIKE '{$columnName}'");
        $column = $stmt ? $stmt->fetch() : false;
        if ($column) {
            return;
        }

        $this->pdo->exec($sql);
    }
}
