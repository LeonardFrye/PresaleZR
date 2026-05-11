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
            'items' => $this->fetchProjects($filters, $perPage, $offset),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projects WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $project = $stmt->fetch();

        return $project ?: null;
    }

    public function save(array $data, array $user, string $ipAddress): int
    {
        $projectId = (int) ($data['project_id'] ?? 0);
        $payload = [
            trim((string) ($data['project_name'] ?? '')),
            trim((string) ($data['project_region'] ?? '')),
            trim((string) ($data['project_sales'] ?? '')),
            (string) ($data['support_role'] ?? ''),
            trim((string) ($data['support_personnel'] ?? '')),
            (string) ($data['start_date'] ?? ''),
            (string) ($data['end_date'] ?? ''),
            $this->durationDays((string) ($data['start_date'] ?? ''), (string) ($data['end_date'] ?? '')),
            trim((string) ($data['task_summary'] ?? '')),
            trim((string) ($data['completion_feedback'] ?? '')),
            empty($data['transfer_flag']) ? 0 : 1,
            empty($data['completion_flag']) ? 0 : 1,
            (string) ($data['work_order_status'] ?? self::STATUS_SALES_TASK),
            (string) ($data['feedback_tag'] ?? self::FEEDBACK_NORMAL),
        ];

        $this->validate($payload);

        if ($projectId > 0) {
            $sql = 'UPDATE projects SET
                    project_name = ?, project_region = ?, project_sales = ?, support_role = ?, support_personnel = ?,
                    start_date = ?, end_date = ?, duration_days = ?, task_summary = ?, completion_feedback = ?,
                    transfer_flag = ?, completion_flag = ?, work_order_status = ?, feedback_tag = ?, updated_by = ?, updated_at = NOW()
                    WHERE id = ?';
            $params = array_merge($payload, [(int) $user['id'], $projectId]);
            $this->pdo->prepare($sql)->execute($params);
            $this->auth->log((int) $user['id'], 'update', 'projects', '更新项目：' . $payload[0], $ipAddress, ['project_id' => $projectId]);

            return $projectId;
        }

        $sql = 'INSERT INTO projects (
                project_name, project_region, project_sales, support_role, support_personnel,
                start_date, end_date, duration_days, task_summary, completion_feedback,
                transfer_flag, completion_flag, work_order_status, feedback_tag, created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $params = array_merge($payload, [(int) $user['id'], (int) $user['id']]);
        $this->pdo->prepare($sql)->execute($params);
        $projectId = (int) $this->pdo->lastInsertId();
        $this->auth->log((int) $user['id'], 'create', 'projects', '新增项目：' . $payload[0], $ipAddress, ['project_id' => $projectId]);

        return $projectId;
    }

    public function delete(int $id, array $user, string $ipAddress): void
    {
        $project = $this->find($id);
        if (!$project) {
            return;
        }

        $this->pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
        $this->auth->log((int) $user['id'], 'delete', 'projects', '删除项目：' . $project['project_name'], $ipAddress, ['project_id' => $id]);
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
                if (!$this->datesOverlap($left['start_date'], $left['end_date'], $right['start_date'], $right['end_date'])) {
                    continue;
                }

                $shared = array_values(array_intersect(
                    support_people($left['support_personnel']),
                    support_people($right['support_personnel'])
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

    private function fetchProjects(array $filters = [], ?int $limit = null, ?int $offset = null): array
    {
        [$whereSql, $params] = $this->buildFilterClause($filters);
        $sql = 'SELECT p.*, d.original_name AS receipt_name, u.display_name AS creator_name
                FROM projects p
                LEFT JOIN documents d ON d.id = p.receipt_document_id
                LEFT JOIN users u ON u.id = p.created_by
                WHERE 1=1'
            . $whereSql
            . ' ORDER BY p.start_date DESC, p.id DESC';

        if ($limit !== null) {
            $sql .= ' LIMIT ' . max($limit, 1);
            if ($offset !== null && $offset > 0) {
                $sql .= ' OFFSET ' . $offset;
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $projects = $stmt->fetchAll() ?: [];

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
        $projectName = (string) ($payload[0] ?? '');
        $projectRegion = (string) ($payload[1] ?? '');
        $projectSales = (string) ($payload[2] ?? '');
        $supportRole = (string) ($payload[3] ?? '');
        $supportPersonnel = (string) ($payload[4] ?? '');
        $startDate = (string) ($payload[5] ?? '');
        $endDate = (string) ($payload[6] ?? '');
        $durationDays = (int) ($payload[7] ?? 0);
        $taskSummary = (string) ($payload[8] ?? '');
        $workOrderStatus = (string) ($payload[12] ?? self::STATUS_SALES_TASK);
        $feedbackTag = (string) ($payload[13] ?? self::FEEDBACK_NORMAL);

        if ($projectName === '' || $projectRegion === '' || $projectSales === '' || $supportPersonnel === '' || $taskSummary === '') {
            throw new RuntimeException('请完整填写项目名称、区域、销售、支撑人员和工作任务。');
        }
        if (!in_array($supportRole, ['售前', '实施'], true)) {
            throw new RuntimeException('支撑岗位仅支持售前或实施。');
        }
        if (!$this->isDate($startDate) || !$this->isDate($endDate)) {
            throw new RuntimeException('开始时间和结束时间格式不正确。');
        }
        if (strtotime($endDate) < strtotime($startDate) || $durationDays < 1) {
            throw new RuntimeException('结束时间不能早于开始时间。');
        }
        if (!in_array($workOrderStatus, array_keys(self::statusOptions()), true)) {
            throw new RuntimeException('工单标签状态不合法。');
        }
        if (!in_array($feedbackTag, array_keys(self::feedbackTagOptions()), true)) {
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

    private function isDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    private function datesOverlap(string $leftStart, string $leftEnd, string $rightStart, string $rightEnd): bool
    {
        return strtotime($leftStart) <= strtotime($rightEnd) && strtotime($rightStart) <= strtotime($leftEnd);
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
}
