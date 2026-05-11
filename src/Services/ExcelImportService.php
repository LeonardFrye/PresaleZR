<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMXPath;
use PDO;
use RuntimeException;

final class ExcelImportService
{
    private const HEADER_ALIASES = [
        '序号' => '',
        '项目名称' => '项目名称',
        '项目区域' => '项目区域',
        '区域' => '项目区域',
        '项目销售' => '项目销售',
        '销售' => '项目销售',
        '支撑岗位' => '支撑岗位',
        '岗位' => '支撑岗位',
        '支撑人员' => '支撑人员',
        '开始时间' => '开始时间',
        '开始日期' => '开始时间',
        '结束时间' => '结束时间',
        '结束日期' => '结束时间',
        '工作任务简述' => '工作任务简述',
        '工作任务【简述】' => '工作任务简述',
        '工作任务' => '工作任务简述',
        '任务简述' => '工作任务简述',
        '技术完成回执单' => '技术完成回执单',
        '反馈标签' => '反馈标签',
        '完成评价销售反馈简述' => '完成评价',
        '完成评价【销售反馈简述】' => '完成评价',
        '完成评价' => '完成评价',
        '销售反馈简述' => '完成评价',
        '工单标签' => '工单标签状态',
        '工单标签状态' => '工单标签状态',
        '项目状态工单标签' => '工单标签状态',
        '转接延续' => '转接延续',
        '项目完成' => '项目完成',
    ];

    private $pdo;
    private $projectService;
    private $auth;

    public function __construct(PDO $pdo, ProjectService $projectService, AuthService $auth)
    {
        $this->pdo = $pdo;
        $this->projectService = $projectService;
        $this->auth = $auth;
    }

    public function import(array $file, array $user, string $ipAddress): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('请选择要导入的 Excel 文件。');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName)) {
            throw new RuntimeException('上传文件无效，请重新选择后再试。');
        }

        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'csv'], true)) {
            throw new RuntimeException('当前仅支持导入 .xlsx 或 .csv 文件。');
        }

        $rows = $extension === 'csv'
            ? $this->parseCsv($tmpName)
            : $this->parseXlsx($tmpName);

        if ($rows === []) {
            throw new RuntimeException('Excel 中没有可导入的数据。');
        }

        [$headers, $dataRows] = $this->splitHeaderAndRows($rows);
        $mappedHeaders = $this->normalizeHeaders($headers);
        $summary = [
            'inserted' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($dataRows as $index => $row) {
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            try {
                $payload = $this->mapRowToProject($mappedHeaders, $row);
                if ($payload === null) {
                    $summary['skipped']++;
                    continue;
                }

                if ($this->isDuplicate($payload)) {
                    $summary['skipped']++;
                    continue;
                }

                $this->projectService->save($payload, $user, $ipAddress);
                $summary['inserted']++;
            } catch (RuntimeException $exception) {
                $summary['errors'][] = sprintf('第 %d 行：%s', $index + 2, $exception->getMessage());
            }
        }

        $this->auth->log((int) $user['id'], 'import', 'projects', '导入 Excel 项目数据', $ipAddress, [
            'file' => $originalName,
            'inserted' => $summary['inserted'],
            'skipped' => $summary['skipped'],
            'errors' => count($summary['errors']),
        ]);

        return $summary;
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('无法读取 CSV 文件。');
        }

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(function ($value): string {
                return $this->sanitizeCell((string) $value);
            }, $row);
        }

        fclose($handle);

        return $rows;
    }

    private function parseXlsx(string $path): array
    {
        $zip = new ZipReader($path);
        $sharedStrings = $zip->has('xl/sharedStrings.xml')
            ? $this->readSharedStrings($zip->get('xl/sharedStrings.xml'))
            : [];

        $sheetPath = $this->resolveFirstSheetPath($zip);
        $dom = new DOMDocument();
        if (!@$dom->loadXML($zip->get($sheetPath))) {
            throw new RuntimeException('Excel 工作表内容解析失败。');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];

        foreach ($xpath->query('//a:sheetData/a:row') as $rowNode) {
            $row = [];
            foreach ($xpath->query('a:c', $rowNode) as $cellNode) {
                $ref = (string) $cellNode->getAttribute('r');
                $columnIndex = $this->columnToIndex($ref);
                $cellType = (string) $cellNode->getAttribute('t');
                $value = '';

                if ($cellType === 'inlineStr') {
                    foreach ($xpath->query('a:is//a:t', $cellNode) as $textNode) {
                        $value .= $textNode->textContent;
                    }
                } else {
                    $valueNode = $xpath->query('a:v', $cellNode)->item(0);
                    $rawValue = $valueNode ? (string) $valueNode->textContent : '';
                    $value = $cellType === 's'
                        ? ($sharedStrings[(int) $rawValue] ?? '')
                        : $rawValue;
                }

                $row[$columnIndex] = $this->sanitizeCell($value);
            }

            if ($row === []) {
                continue;
            }

            ksort($row);
            $maxIndex = max(array_keys($row));
            $denseRow = [];
            for ($index = 0; $index <= $maxIndex; $index++) {
                $denseRow[] = $row[$index] ?? '';
            }
            $rows[] = $denseRow;
        }

        return $rows;
    }

    private function resolveFirstSheetPath(ZipReader $zip): string
    {
        if (!$zip->has('xl/workbook.xml') || !$zip->has('xl/_rels/workbook.xml.rels')) {
            throw new RuntimeException('未找到 Excel 工作簿结构。');
        }

        $workbookDom = new DOMDocument();
        $relationshipsDom = new DOMDocument();
        if (!@$workbookDom->loadXML($zip->get('xl/workbook.xml')) || !@$relationshipsDom->loadXML($zip->get('xl/_rels/workbook.xml.rels'))) {
            throw new RuntimeException('Excel 工作簿关系解析失败。');
        }

        $workbookXPath = new DOMXPath($workbookDom);
        $workbookXPath->registerNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $firstSheet = $workbookXPath->query('//a:sheets/a:sheet')->item(0);
        if ($firstSheet === null) {
            throw new RuntimeException('未找到 Excel 的工作表。');
        }

        $relationshipId = (string) $firstSheet->getAttributeNS(
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
            'id'
        );
        if ($relationshipId === '') {
            throw new RuntimeException('未找到首个工作表的关系标识。');
        }

        $relationshipsXPath = new DOMXPath($relationshipsDom);
        $relationshipsXPath->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        foreach ($relationshipsXPath->query('//r:Relationship') as $relationship) {
            if ((string) $relationship->getAttribute('Id') !== $relationshipId) {
                continue;
            }

            $target = (string) $relationship->getAttribute('Target');
            $fullPath = 'xl/' . ltrim(str_replace('\\', '/', $target), '/');
            if ($zip->has($fullPath)) {
                return $fullPath;
            }
        }

        throw new RuntimeException('未找到首个工作表文件。');
    }

    private function readSharedStrings(string $xml): array
    {
        $dom = new DOMDocument();
        if (!@$dom->loadXML($xml)) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];
        foreach ($xpath->query('//a:si') as $item) {
            $buffer = '';
            foreach ($xpath->query('.//a:t', $item) as $textNode) {
                $buffer .= $textNode->textContent;
            }
            $strings[] = $this->sanitizeCell($buffer);
        }

        return $strings;
    }

    private function splitHeaderAndRows(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $normalized = array_map([$this, 'normalizeHeaderText'], $row);
            if (in_array('项目名称', $normalized, true) || in_array('项目区域', $normalized, true)) {
                return [$row, array_slice($rows, $index + 1)];
            }
        }

        throw new RuntimeException('未识别到有效表头，请确保 Excel 包含“项目名称、项目区域、项目销售、支撑岗位、支撑人员、开始时间、结束时间”等列。');
    }

    private function normalizeHeaders(array $headers): array
    {
        return array_map([$this, 'normalizeHeaderText'], $headers);
    }

    private function normalizeHeaderText(string $value): string
    {
        $value = trim(str_replace(["\r", "\n"], '', $this->sanitizeCell($value)));
        $value = preg_replace('/\s+/u', '', $value) ?? $value;
        $value = str_replace(['：', ':', '【', '】', '（', '）', '(', ')'], '', $value);

        return self::HEADER_ALIASES[$value] ?? $value;
    }

    private function mapRowToProject(array $headers, array $row): ?array
    {
        $data = [];
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $data[$header] = $this->sanitizeCell((string) ($row[$index] ?? ''));
        }

        if (($data['项目名称'] ?? '') === '' && ($data['项目区域'] ?? '') === '' && ($data['项目销售'] ?? '') === '') {
            return null;
        }

        $supportRole = $data['支撑岗位'] ?? '实施';
        $supportRole = strpos($supportRole, '售前') !== false ? '售前' : '实施';

        return [
            'project_name' => $data['项目名称'] ?? '',
            'project_region' => $data['项目区域'] ?? '',
            'project_sales' => $data['项目销售'] ?? '',
            'support_role' => $supportRole,
            'support_personnel' => $data['支撑人员'] ?? '',
            'start_date' => $this->normalizeDate($data['开始时间'] ?? ''),
            'end_date' => $this->normalizeDate($data['结束时间'] ?? ''),
            'task_summary' => $data['工作任务简述'] ?? '',
            'completion_feedback' => $data['完成评价'] ?? '',
            'feedback_tag' => $this->normalizeFeedbackTag($data['反馈标签'] ?? ''),
            'transfer_flag' => $this->normalizeBoolean($data['转接延续'] ?? ''),
            'completion_flag' => $this->normalizeBoolean($data['项目完成'] ?? ''),
            'work_order_status' => $this->normalizeWorkOrderStatus($data['工单标签状态'] ?? ''),
        ];
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new RuntimeException('开始时间或结束时间不能为空。');
        }

        if (is_numeric($value) && (float) $value > 1000) {
            $timestamp = ((int) round((float) $value) - 25569) * 86400;
            return gmdate('Y-m-d', $timestamp);
        }

        $value = str_replace(['.', '/', '年', '月'], ['-', '-', '-', '-'], $value);
        $value = str_replace('日', '', $value);
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException('无法识别日期格式：' . $value);
        }

        return date('Y-m-d', $timestamp);
    }

    private function normalizeBoolean(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $valueLower = strtolower($value);
        return in_array($value, ['1', '是', '完成', '已完成', '有'], true)
            || in_array($valueLower, ['true', 'yes', 'y'], true)
            ? 1
            : 0;
    }

    private function normalizeWorkOrderStatus(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return ProjectService::STATUS_SALES_TASK;
        }

        $normalized = preg_replace('/\s+/u', '', $value) ?? $value;
        if ($normalized === ProjectService::STATUS_MANAGER_REVIEW || $normalized === '技术管理审核') {
            return ProjectService::STATUS_MANAGER_REVIEW;
        }
        if ($normalized === ProjectService::STATUS_TECH_EXECUTION || $normalized === '技术人员实施') {
            return ProjectService::STATUS_TECH_EXECUTION;
        }

        return ProjectService::STATUS_SALES_TASK;
    }

    private function normalizeFeedbackTag(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return ProjectService::FEEDBACK_NORMAL;
        }

        $normalized = preg_replace('/\s+/u', '', $value) ?? $value;
        if ($normalized === ProjectService::FEEDBACK_BONUS || $normalized === '加分') {
            return ProjectService::FEEDBACK_BONUS;
        }
        if ($normalized === ProjectService::FEEDBACK_COMPLAINT || $normalized === '投诉') {
            return ProjectService::FEEDBACK_COMPLAINT;
        }

        return ProjectService::FEEDBACK_NORMAL;
    }

    private function sanitizeCell(string $value): string
    {
        $value = str_replace(["\xEF\xBB\xBF", "\0"], '', $value);
        if ($value !== '' && !preg_match('//u', $value) && function_exists('iconv')) {
            $converted = @iconv('GB18030', 'UTF-8//IGNORE', $value);
            if (is_string($converted) && $converted !== '' && preg_match('//u', $converted)) {
                $value = $converted;
            }
        }

        $value = trim($value);
        return preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function isDuplicate(array $payload): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM projects WHERE project_name = ? AND support_personnel = ? AND start_date = ? AND end_date = ? LIMIT 1'
        );
        $stmt->execute([
            $payload['project_name'],
            $payload['support_personnel'],
            $payload['start_date'],
            $payload['end_date'],
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function columnToIndex(string $cellReference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($cellReference)) ?: 'A';
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }
}
