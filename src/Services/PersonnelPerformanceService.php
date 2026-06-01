<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class PersonnelPerformanceService
{
    private const LEGACY_PROJECT_KEY = '__legacy_daily__';

    private $pdo;
    private $projectService;
    private $auth;

    public function __construct(PDO $pdo, ProjectService $projectService, AuthService $auth)
    {
        $this->pdo = $pdo;
        $this->projectService = $projectService;
        $this->auth = $auth;
        $this->ensureTable();
    }

    public function board(string $period, string $anchorDate): array
    {
        $range = $this->resolvePeriod($period, $anchorDate);
        $people = $this->allPeople();
        $assignments = $this->assignmentMap($range['start'], $range['end']);
        $overrides = $this->overrideMap($range['start'], $range['end']);
        $cards = [];

        foreach ($people as $person) {
            $activeDays = 0;
            $totalScore = 0.0;
            $cursor = strtotime($range['start']);
            $end = strtotime($range['end']);

            while ($cursor <= $end) {
                $date = date('Y-m-d', $cursor);
                $tasks = $assignments[$person][$date] ?? [];
                if ($tasks !== []) {
                    $activeDays++;
                    $totalScore += $this->sumTaskScores($tasks, $overrides[$person][$date] ?? []);
                }

                $cursor = strtotime('+1 day', $cursor);
            }

            $cards[] = [
                'name' => $person,
                'active_days' => $activeDays,
                'total_score' => $totalScore,
            ];
        }

        usort($cards, static function (array $left, array $right): int {
            if ($left['total_score'] === $right['total_score']) {
                return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
            }

            return $left['total_score'] < $right['total_score'] ? 1 : -1;
        });

        return [
            'period' => $range['period'],
            'anchor_date' => $range['anchor_date'],
            'label' => $range['label'],
            'cards' => $cards,
        ];
    }

    public function detail(string $personName, string $period, string $anchorDate): ?array
    {
        $personName = trim($personName);
        if ($personName === '') {
            return null;
        }

        $people = $this->allPeople();
        if (!in_array($personName, $people, true)) {
            return null;
        }

        $range = $this->resolvePeriod($period, $anchorDate);
        $assignments = $this->assignmentMap($range['start'], $range['end'], $personName);
        $overrides = $this->overrideMap($range['start'], $range['end'], $personName);
        $taskMap = $assignments[$personName] ?? [];
        $days = [];
        $cursor = strtotime($range['start']);
        $end = strtotime($range['end']);
        $totalScore = 0.0;
        $activeDays = 0;

        while ($cursor <= $end) {
            $date = date('Y-m-d', $cursor);
            $tasks = $taskMap[$date] ?? [];
            $hasTask = $tasks !== [];

            if ($hasTask) {
                $tasks = $this->hydrateTaskScores($tasks, $overrides[$personName][$date] ?? []);
                $activeDays++;
                $totalScore += $this->sumHydratedTaskScores($tasks);
            }

            $days[$date] = [
                'date' => $date,
                'day_number' => date('j', $cursor),
                'month_number' => date('n', $cursor),
                'month_label' => date('Y年n月', $cursor),
                'weekday' => weekday_label($date),
                'has_task' => $hasTask,
                'tasks' => $tasks,
            ];

            $cursor = strtotime('+1 day', $cursor);
        }

        return [
            'person_name' => $personName,
            'period' => $range['period'],
            'anchor_date' => $range['anchor_date'],
            'label' => $range['label'],
            'active_days' => $activeDays,
            'total_score' => $totalScore,
            'calendar_groups' => $this->calendarGroups($range['period'], $range['start'], $range['end'], $days),
        ];
    }

    public function saveScores(string $personName, array $scores, array $user, string $ipAddress): void
    {
        $personName = trim($personName);
        if ($personName === '') {
            throw new RuntimeException('请选择需要保存绩效的技术人员。');
        }

        if ($scores === []) {
            return;
        }

        $dates = array_keys($scores);
        sort($dates);
        $startDate = (string) $dates[0];
        $endDate = (string) $dates[count($dates) - 1];
        $assignments = $this->assignmentMap($startDate, $endDate, $personName);
        $taskMap = $assignments[$personName] ?? [];

        $replace = $this->pdo->prepare(
            'INSERT INTO personnel_performance_scores (person_name, work_date, project_id, score, updated_by) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE score = VALUES(score), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP'
        );
        $delete = $this->pdo->prepare(
            'DELETE FROM personnel_performance_scores WHERE person_name = ? AND work_date = ? AND project_id = ?'
        );
        $deleteLegacy = $this->pdo->prepare(
            'DELETE FROM personnel_performance_scores WHERE person_name = ? AND work_date = ? AND project_id IS NULL'
        );
        $savedDates = [];

        foreach ($scores as $date => $dateScores) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
                continue;
            }
            if (empty($taskMap[$date])) {
                continue;
            }

            $validTasks = [];
            foreach ($taskMap[$date] as $task) {
                $projectId = (int) ($task['project_id'] ?? 0);
                if ($projectId <= 0) {
                    continue;
                }

                $validTasks[$projectId] = round((float) ($task['default_score'] ?? 0), 2);
            }

            if ($validTasks === []) {
                continue;
            }

            $deleteLegacy->execute([$personName, $date]);

            foreach ((array) $dateScores as $projectId => $rawScore) {
                $projectId = (int) $projectId;
                if ($projectId <= 0 || !array_key_exists($projectId, $validTasks)) {
                    continue;
                }

                $score = round((float) $rawScore, 2);
                if ($score < 0) {
                    $score = 0.0;
                }

                if (abs($score - $validTasks[$projectId]) < 0.00001) {
                    $delete->execute([$personName, $date, $projectId]);
                } else {
                    $replace->execute([$personName, $date, $projectId, $score, (int) $user['id']]);
                }
            }

            $savedDates[] = $date;
        }

        $this->auth->log((int) $user['id'], 'update', 'personnel', '更新人员绩效：' . $personName, $ipAddress, [
            'person_name' => $personName,
            'dates' => $savedDates,
        ]);
    }

    public function exportRows(string $period, string $anchorDate, ?string $personName = null): array
    {
        $range = $this->resolvePeriod($period, $anchorDate);
        $personName = $personName !== null ? trim($personName) : null;
        $people = $personName !== null && $personName !== '' ? [$personName] : $this->allPeople();
        $assignments = $this->assignmentMap($range['start'], $range['end'], $personName !== '' ? $personName : null);
        $overrides = $this->overrideMap($range['start'], $range['end'], $personName !== '' ? $personName : null);
        $rows = [];

        if ($personName !== null && $personName !== '' && !in_array($personName, $this->allPeople(), true)) {
            throw new RuntimeException('未找到对应的技术人员。');
        }

        foreach ($people as $person) {
            $cursor = strtotime($range['start']);
            $end = strtotime($range['end']);

            while ($cursor <= $end) {
                $date = date('Y-m-d', $cursor);
                $tasks = $assignments[$person][$date] ?? [];

                if ($tasks === []) {
                    $rows[] = [
                        'month' => (int) date('n', $cursor),
                        'day' => (int) date('j', $cursor),
                        'person_name' => '',
                        'department' => '',
                        'project_sales' => '',
                        'project_name' => '',
                        'task_summary' => '',
                        'score' => '',
                    ];
                    $cursor = strtotime('+1 day', $cursor);
                    continue;
                }

                $tasks = $this->hydrateTaskScores($tasks, $overrides[$person][$date] ?? []);
                foreach ($tasks as $task) {
                    $rows[] = [
                        'month' => (int) date('n', $cursor),
                        'day' => (int) date('j', $cursor),
                        'person_name' => $person,
                        'department' => '技术支撑事业部',
                        'project_sales' => (string) ($task['project_sales'] ?? ''),
                        'project_name' => (string) ($task['project_name'] ?? ''),
                        'task_summary' => (string) ($task['task_summary'] ?? ''),
                        'score' => $task['score'] ?? '',
                    ];
                }

                $cursor = strtotime('+1 day', $cursor);
            }
        }

        return [
            'period' => $range['period'],
            'anchor_date' => $range['anchor_date'],
            'label' => $range['label'],
            'person_name' => $personName !== '' ? $personName : null,
            'rows' => $rows,
        ];
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS personnel_performance_scores (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                person_name VARCHAR(100) NOT NULL,
                work_date DATE NOT NULL,
                project_id INT UNSIGNED NULL,
                score DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                updated_by INT UNSIGNED NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_person_date_project (person_name, work_date, project_id),
                CONSTRAINT fk_personnel_scores_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $columns = $this->pdo->query('SHOW COLUMNS FROM personnel_performance_scores')->fetchAll() ?: [];
        $columnNames = [];
        foreach ($columns as $column) {
            $columnNames[] = $column['Field'];
        }

        if (!in_array('project_id', $columnNames, true)) {
            $this->pdo->exec('ALTER TABLE personnel_performance_scores ADD COLUMN project_id INT UNSIGNED NULL AFTER work_date');
        }

        $this->pdo->exec('ALTER TABLE personnel_performance_scores MODIFY COLUMN score DECIMAL(8,2) NOT NULL DEFAULT 0.00');

        $indexes = $this->pdo->query('SHOW INDEX FROM personnel_performance_scores')->fetchAll() ?: [];
        $hasLegacyIndex = false;
        $hasProjectIndex = false;

        foreach ($indexes as $index) {
            $keyName = (string) ($index['Key_name'] ?? '');
            if ($keyName === 'uk_person_date') {
                $hasLegacyIndex = true;
            }
            if ($keyName === 'uk_person_date_project') {
                $hasProjectIndex = true;
            }
        }

        if ($hasLegacyIndex) {
            $this->pdo->exec('ALTER TABLE personnel_performance_scores DROP INDEX uk_person_date');
        }

        if (!$hasProjectIndex) {
            $this->pdo->exec(
                'ALTER TABLE personnel_performance_scores ADD UNIQUE KEY uk_person_date_project (person_name, work_date, project_id)'
            );
        }
    }

    private function resolvePeriod(string $period, string $anchorDate): array
    {
        $period = in_array($period, ['year', 'month', 'week'], true) ? $period : 'month';
        $anchorTimestamp = strtotime($anchorDate);
        if ($anchorTimestamp === false) {
            $anchorTimestamp = time();
        }
        $anchorDate = date('Y-m-d', $anchorTimestamp);

        if ($period === 'year') {
            $year = date('Y', $anchorTimestamp);
            return [
                'period' => $period,
                'anchor_date' => $anchorDate,
                'start' => $year . '-01-01',
                'end' => $year . '-12-31',
                'label' => $year . '年',
            ];
        }

        if ($period === 'week') {
            $weekday = (int) date('N', $anchorTimestamp);
            $start = date('Y-m-d', strtotime('-' . ($weekday - 1) . ' days', $anchorTimestamp));
            $end = date('Y-m-d', strtotime('+6 days', strtotime($start)));
            return [
                'period' => $period,
                'anchor_date' => $anchorDate,
                'start' => $start,
                'end' => $end,
                'label' => $start . ' 至 ' . $end,
            ];
        }

        return [
            'period' => $period,
            'anchor_date' => $anchorDate,
            'start' => date('Y-m-01', $anchorTimestamp),
            'end' => date('Y-m-t', $anchorTimestamp),
            'label' => date('Y年m月', $anchorTimestamp),
        ];
    }

    private function allPeople(): array
    {
        $people = [];
        foreach ($this->projectService->list() as $project) {
            foreach (support_people((string) $project['support_personnel']) as $person) {
                $personName = trim((string) $person);
                if ($personName === '') {
                    continue;
                }

                $people['person:' . $personName] = $personName;
            }
        }

        $names = array_values($people);
        sort($names);

        return $names;
    }

    private function assignmentMap(string $startDate, string $endDate, ?string $personFilter = null): array
    {
        $map = [];
        foreach ($this->projectService->list() as $project) {
            $projectRangeStart = max($startDate, (string) ($project['start_date'] ?? ''));
            $projectRangeEnd = min($endDate, (string) ($project['end_date'] ?? ''));
            if ($projectRangeStart > $projectRangeEnd) {
                continue;
            }

            $dailyScores = project_daily_workload_scores((string) ($project['start_at'] ?? ''), (string) ($project['end_at'] ?? ''));
            $people = support_people((string) $project['support_personnel']);
            foreach ($people as $person) {
                if ($personFilter !== null && $person !== $personFilter) {
                    continue;
                }

                $cursor = strtotime($projectRangeStart);
                $end = strtotime($projectRangeEnd);
                while ($cursor <= $end) {
                    $date = date('Y-m-d', $cursor);
                    $map[$person][$date][] = [
                        'project_id' => (int) ($project['id'] ?? 0),
                        'project_sales' => (string) ($project['project_sales'] ?? ''),
                        'project_name' => (string) ($project['project_name'] ?? ''),
                        'task_summary' => trim((string) ($project['task_summary'] ?? '')),
                        'default_score' => round((float) ($dailyScores[$date] ?? 0.0), 2),
                    ];
                    $cursor = strtotime('+1 day', $cursor);
                }
            }
        }

        return $map;
    }

    private function overrideMap(string $startDate, string $endDate, ?string $personFilter = null): array
    {
        $sql = 'SELECT person_name, work_date, project_id, score FROM personnel_performance_scores WHERE work_date BETWEEN ? AND ?';
        $params = [$startDate, $endDate];

        if ($personFilter !== null) {
            $sql .= ' AND person_name = ?';
            $params[] = $personFilter;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];
        $map = [];

        foreach ($rows as $row) {
            $person = (string) $row['person_name'];
            $date = (string) $row['work_date'];
            $projectId = $row['project_id'] === null ? null : (int) $row['project_id'];

            if ($projectId === null) {
                $map[$person][$date][self::LEGACY_PROJECT_KEY] = (float) $row['score'];
                continue;
            }

            $map[$person][$date][$projectId] = (float) $row['score'];
        }

        return $map;
    }

    private function hydrateTaskScores(array $tasks, array $overrideRow): array
    {
        $legacyScore = array_key_exists(self::LEGACY_PROJECT_KEY, $overrideRow)
            ? (float) $overrideRow[self::LEGACY_PROJECT_KEY]
            : null;
        $hasProjectOverrides = false;

        foreach ($overrideRow as $projectKey => $value) {
            if ($projectKey === self::LEGACY_PROJECT_KEY) {
                continue;
            }

            $hasProjectOverrides = true;
            break;
        }

        foreach ($tasks as $index => $task) {
            $projectId = (int) ($task['project_id'] ?? 0);
            $defaultScore = round((float) ($task['default_score'] ?? 0.0), 2);

            if ($projectId > 0 && array_key_exists($projectId, $overrideRow)) {
                $tasks[$index]['score'] = (float) $overrideRow[$projectId];
                continue;
            }

            if (!$hasProjectOverrides && $legacyScore !== null && $index === 0) {
                $tasks[$index]['score'] = $legacyScore;
                continue;
            }

            if (!$hasProjectOverrides && $legacyScore !== null) {
                $tasks[$index]['score'] = '';
                continue;
            }

            $tasks[$index]['score'] = $defaultScore;
        }

        return $tasks;
    }

    private function sumTaskScores(array $tasks, array $overrideRow): float
    {
        return $this->sumHydratedTaskScores($this->hydrateTaskScores($tasks, $overrideRow));
    }

    private function sumHydratedTaskScores(array $tasks): float
    {
        $total = 0.0;
        foreach ($tasks as $task) {
            $score = $task['score'] ?? '';
            if ($score === '') {
                continue;
            }

            $total += (float) $score;
        }

        return $total;
    }

    private function calendarGroups(string $period, string $startDate, string $endDate, array $days): array
    {
        if ($period === 'week') {
            return [[
                'title' => date('Y年n月j日', strtotime($startDate)) . ' - ' . date('Y年n月j日', strtotime($endDate)),
                'compact' => false,
                'weeks' => [$this->buildWeekCells($startDate, $days)],
            ]];
        }

        if ($period === 'year') {
            $groups = [];
            $cursor = strtotime($startDate);
            for ($month = 1; $month <= 12; $month++) {
                $monthStart = date(
                    'Y-m-01',
                    strtotime(date('Y', $cursor) . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '-01')
                );
                $monthEnd = date('Y-m-t', strtotime($monthStart));
                $groups[] = [
                    'title' => date('Y年n月', strtotime($monthStart)),
                    'compact' => true,
                    'weeks' => $this->buildMonthWeeks($monthStart, $monthEnd, $days),
                ];
            }

            return $groups;
        }

        return [[
            'title' => date('Y年n月', strtotime($startDate)),
            'compact' => false,
            'weeks' => $this->buildMonthWeeks($startDate, $endDate, $days),
        ]];
    }

    private function buildWeekCells(string $weekStart, array $days): array
    {
        $cells = [];
        $cursor = strtotime($weekStart);
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', $cursor);
            $cells[] = $days[$date] ?? $this->emptyDay($date);
            $cursor = strtotime('+1 day', $cursor);
        }

        return $cells;
    }

    private function buildMonthWeeks(string $monthStart, string $monthEnd, array $days): array
    {
        $gridStart = strtotime('-' . ((int) date('N', strtotime($monthStart)) - 1) . ' days', strtotime($monthStart));
        $gridEnd = strtotime('+' . (7 - (int) date('N', strtotime($monthEnd))) . ' days', strtotime($monthEnd));
        $weeks = [];
        $week = [];

        for ($cursor = $gridStart; $cursor <= $gridEnd; $cursor = strtotime('+1 day', $cursor)) {
            $date = date('Y-m-d', $cursor);
            $cell = $days[$date] ?? $this->emptyDay($date);
            $cell['in_current_month'] = date('Y-m', strtotime($date)) === date('Y-m', strtotime($monthStart));
            $week[] = $cell;

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        return $weeks;
    }

    private function emptyDay(string $date): array
    {
        return [
            'date' => $date,
            'day_number' => date('j', strtotime($date)),
            'month_number' => date('n', strtotime($date)),
            'month_label' => date('Y年n月', strtotime($date)),
            'weekday' => weekday_label($date),
            'has_task' => false,
            'tasks' => [],
            'in_current_month' => false,
        ];
    }
}
