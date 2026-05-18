<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class AnalyticsService
{
    private $projectService;
    private $projects;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
        $this->ensureAttendanceOverrideTable();
        $this->projects = $projectService->list();
    }

    public function dashboard(): array
    {
        $projects = $this->projects;
        $totalProjects = count($projects);
        $regions = [];
        $roles = ['售前' => 0, '实施' => 0];
        $sales = [];
        $monthly = [];
        $gantt = [];
        $hours = 0;
        $completionCount = 0;
        $transferCount = 0;
        $ongoingCount = 0;
        $today = date('Y-m-d');

        foreach ($projects as $project) {
            $regions[$project['project_region']] = ($regions[$project['project_region']] ?? 0) + 1;
            $roles[$project['support_role']] = ($roles[$project['support_role']] ?? 0) + 1;
            $sales[$project['project_sales']] = ($sales[$project['project_sales']] ?? 0) + 1;
            $month = date('Y-m', strtotime($project['start_date']));
            $monthly[$month] = ($monthly[$month] ?? 0) + 1;
            $hours += (int) $project['duration_days'] * 8;
            $completionCount += (int) $project['completion_flag'];
            $transferCount += (int) $project['transfer_flag'];
            if (in_date_range($today, $project['start_date'], $project['end_date'])) {
                $ongoingCount++;
            }
            $gantt[] = [
                'name' => $project['project_name'],
                'start' => $project['start_date'],
                'end' => $project['end_date'],
                'role' => $project['support_role'],
                'person' => $project['support_personnel'],
            ];
        }

        ksort($monthly);

        $conflicts = array_values(array_filter($projects, static function ($project): bool {
            return !empty($project['conflict_flag']);
        }));

        $attendance = $this->attendanceBoard();

        return [
            'metrics' => [
                'total_projects' => $totalProjects,
                'total_hours' => $hours,
                'total_days' => round($hours / 8, 1),
                'avg_days' => $totalProjects > 0 ? round(array_sum(array_column($projects, 'duration_days')) / $totalProjects, 1) : 0,
                'attendance_rate' => $attendance['attendance_rate'],
                'completion_count' => $completionCount,
                'transfer_count' => $transferCount,
                'conflict_count' => count($conflicts),
                'ongoing_count' => $ongoingCount,
            ],
            'charts' => [
                'regions' => $regions,
                'roles' => $roles,
                'sales' => $sales,
                'monthly' => $monthly,
            ],
            'gantt' => $this->buildGantt($gantt),
            'conflicts' => $conflicts,
            'recent_projects' => array_slice($projects, 0, 6),
            'attendance' => $attendance,
        ];
    }

    public function attendanceBoard(): array
    {
        $days = week_range();
        $overrideMap = $this->attendanceOverrideMap($days);
        $persons = [];

        foreach ($this->projects as $project) {
            foreach (support_people((string) $project['support_personnel']) as $person) {
                $personName = trim((string) $person);
                if ($personName === '') {
                    continue;
                }

                $personKey = 'person:' . $personName;
                $persons[$personKey] = $persons[$personKey] ?? [
                    'name' => $personName,
                    'days' => [],
                    'load_days' => 0,
                    'conflict_days' => 0,
                ];
                foreach ($days as $day) {
                    $date = $day['date'];
                    if (!in_date_range($date, (string) $project['start_date'], (string) $project['end_date'])) {
                        continue;
                    }
                    $persons[$personKey]['days'][$date][] = $project['project_name'];
                }
            }
        }

        $rows = [];
        $busyCells = 0;
        foreach ($persons as $data) {
            $personName = (string) ($data['name'] ?? '');
            $row = ['name' => $personName, 'days' => [], 'load_days' => 0, 'conflict_days' => 0];
            foreach ($days as $day) {
                $date = $day['date'];
                $overrideStatus = $overrideMap[$personName][$date] ?? '';
                $items = $data['days'][$date] ?? [];

                if ($overrideStatus === 'rest') {
                    $row['days'][] = [
                        'date' => $date,
                        'status' => 'rest',
                        'text' => '调休',
                        'override_status' => $overrideStatus,
                    ];
                    continue;
                }

                if ($items === []) {
                    $row['days'][] = [
                        'date' => $date,
                        'status' => 'idle',
                        'text' => '空闲',
                        'override_status' => $overrideStatus,
                    ];
                    continue;
                }

                $busyCells++;
                $row['load_days']++;
                if (count($items) > 1) {
                    $row['conflict_days']++;
                    $row['days'][] = [
                        'date' => $date,
                        'status' => 'conflict',
                        'text' => implode(' / ', $items),
                        'override_status' => $overrideStatus,
                    ];
                } else {
                    $row['days'][] = [
                        'date' => $date,
                        'status' => 'busy',
                        'text' => $items[0],
                        'override_status' => $overrideStatus,
                    ];
                }
            }
            $rows[] = $row;
        }

        usort($rows, static function ($left, $right): int {
            return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        $totalCells = max(count($rows) * count($days), 1);
        $attendanceRate = round($busyCells / $totalCells * 100, 1);

        return [
            'days' => $days,
            'rows' => $rows,
            'attendance_rate' => $attendanceRate,
        ];
    }

    public function saveAttendanceOverride(string $personName, string $workDate, string $status, int $userId): void
    {
        $personName = trim($personName);
        $status = trim($status);

        if ($personName === '') {
            throw new RuntimeException('请选择技术人员。');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate) !== 1) {
            throw new RuntimeException('出勤日期格式不正确。');
        }
        if (!in_array($status, ['', 'rest'], true)) {
            throw new RuntimeException('出勤状态不合法。');
        }

        if ($status === '') {
            $stmt = db()->prepare('DELETE FROM attendance_status_overrides WHERE person_name = ? AND work_date = ?');
            $stmt->execute([$personName, $workDate]);
            return;
        }

        $stmt = db()->prepare(
            'INSERT INTO attendance_status_overrides (person_name, work_date, status, updated_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$personName, $workDate, $status, $userId]);
    }

    public function logs(array $user, array $filters = []): array
    {
        $sql = 'SELECT l.*, u.display_name, u.username
                FROM activity_logs l
                LEFT JOIN users u ON u.id = l.user_id
                WHERE 1=1';
        $params = [];

        if (($user['role'] ?? '') === 'editor') {
            $sql .= ' AND l.user_id = ?';
            $params[] = (int) $user['id'];
        }
        if (!empty($filters['module'])) {
            $sql .= ' AND l.module_name = ?';
            $params[] = $filters['module'];
        }

        $sql .= ' ORDER BY l.created_at DESC LIMIT 200';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function users(): array
    {
        return db()->query('SELECT * FROM users ORDER BY id ASC')->fetchAll() ?: [];
    }

    public function settings(): array
    {
        $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll() ?: [];
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    private function buildGantt(array $gantt): array
    {
        if ($gantt === []) {
            return [];
        }

        $starts = array_map(static function ($item): int {
            return strtotime($item['start']);
        }, $gantt);
        $ends = array_map(static function ($item): int {
            return strtotime($item['end']);
        }, $gantt);

        $min = min($starts);
        $max = max($ends);
        $span = max((int) floor(($max - $min) / 86400) + 1, 1);

        foreach ($gantt as &$item) {
            $item['offset'] = round((strtotime($item['start']) - $min) / 86400 / $span * 100, 2);
            $item['width'] = round((((strtotime($item['end']) - strtotime($item['start'])) / 86400) + 1) / $span * 100, 2);
        }
        unset($item);

        return $gantt;
    }

    private function attendanceOverrideMap(array $days): array
    {
        if ($days === []) {
            return [];
        }

        $dates = array_column($days, 'date');
        $stmt = db()->prepare(
            'SELECT person_name, work_date, status
             FROM attendance_status_overrides
             WHERE work_date BETWEEN ? AND ?'
        );
        $stmt->execute([min($dates), max($dates)]);

        $map = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $map[$row['person_name']][$row['work_date']] = $row['status'];
        }

        return $map;
    }

    private function ensureAttendanceOverrideTable(): void
    {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS attendance_status_overrides (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                person_name VARCHAR(100) NOT NULL,
                work_date DATE NOT NULL,
                status ENUM('rest') NOT NULL DEFAULT 'rest',
                updated_by INT UNSIGNED NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_person_work_date (person_name, work_date),
                CONSTRAINT fk_attendance_override_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}
