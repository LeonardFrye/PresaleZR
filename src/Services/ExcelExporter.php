<?php

declare(strict_types=1);

namespace App\Services;

final class ExcelExporter
{
    public function download(array $projects): void
    {
        $sharedStrings = [];
        $stringIndex = [];
        $statusOptions = ProjectService::statusOptions();

        $columns = [
            ['title' => '项目类型', 'width' => 11],
            ['title' => '序号', 'width' => 7],
            ['title' => '项目区域', 'width' => 12],
            ['title' => '项目名称', 'width' => 36],
            ['title' => '项目重要程度', 'width' => 12],
            ['title' => '项目销售', 'width' => 12],
            ['title' => '支撑事业部', 'width' => 15],
            ['title' => '跨部门协调', 'width' => 12],
            ['title' => '支撑岗位', 'width' => 11],
            ['title' => '支撑人员', 'width' => 12],
            ['title' => '开始时间', 'width' => 21],
            ['title' => '结束时间', 'width' => 21],
            ['title' => '项目工时', 'width' => 11],
            ['title' => '工作任务【简述】', 'width' => 32],
            ['title' => '标签', 'width' => 14],
            ['title' => '技术完成回执单', 'width' => 24],
            ['title' => '完成评价【销售反馈简述】', 'width' => 28],
        ];

        $rows = [];
        $headerCells = [];
        foreach ($columns as $index => $column) {
            $headerCells[] = $this->stringCell(
                $this->cellRef($index, 1),
                $column['title'],
                1,
                $sharedStrings,
                $stringIndex
            );
        }
        $rows[] = $this->buildRow(1, $headerCells, 24.0);

        $line = 2;
        foreach ($projects as $index => $project) {
            $statusKey = (string) ($project['work_order_status'] ?? ProjectService::STATUS_SALES_TASK);
            $statusLabel = $statusOptions[$statusKey] ?? $statusOptions[ProjectService::STATUS_SALES_TASK];
            $cells = [
                $this->stringCell($this->cellRef(0, $line), (string) ($project['project_type'] ?? ''), 2, $sharedStrings, $stringIndex),
                $this->numberCell($this->cellRef(1, $line), (string) ($index + 1), 3),
                $this->stringCell($this->cellRef(2, $line), (string) ($project['project_region'] ?? ''), 2, $sharedStrings, $stringIndex),
                $this->stringCell($this->cellRef(3, $line), (string) ($project['project_name'] ?? ''), 2, $sharedStrings, $stringIndex),
                $this->stringCell($this->cellRef(4, $line), (string) ($project['project_priority'] ?? '普通'), 2, $sharedStrings, $stringIndex),
                $this->stringCell($this->cellRef(5, $line), (string) ($project['project_sales'] ?? ''), 2, $sharedStrings, $stringIndex),
                $this->stringCell($this->cellRef(6, $line), (string) ($project['support_department'] ?? '技术支撑事业部'), 2, $sharedStrings, $stringIndex),
                $this->stringCell($this->cellRef(7, $line), (string) ($project['cross_department'] ?? ''), 2, $sharedStrings, $stringIndex),
                $this->stringCell($this->cellRef(8, $line), (string) ($project['support_role'] ?? ''), 2, $sharedStrings, $stringIndex),
                $this->stringCell($this->cellRef(9, $line), (string) ($project['support_personnel'] ?? ''), 2, $sharedStrings, $stringIndex),
                $this->stringCell($this->cellRef(10, $line), $this->datetimeValue((string) ($project['start_at'] ?? '')), 2, $sharedStrings, $stringIndex),
                $this->stringCell($this->cellRef(11, $line), $this->datetimeValue((string) ($project['end_at'] ?? '')), 2, $sharedStrings, $stringIndex),
                $this->numberCell($this->cellRef(12, $line), $this->workloadValue($project['project_hours'] ?? 0), 3),
                $this->stringCell($this->cellRef(13, $line), (string) ($project['task_summary'] ?? ''), 2, $sharedStrings, $stringIndex),
                $this->stringCell($this->cellRef(14, $line), $statusLabel, 2, $sharedStrings, $stringIndex),
                $this->stringCell($this->cellRef(15, $line), (string) ($project['receipt_name'] ?? ''), 2, $sharedStrings, $stringIndex),
                $this->stringCell($this->cellRef(16, $line), (string) ($project['completion_feedback'] ?? ''), 2, $sharedStrings, $stringIndex),
            ];

            $rows[] = $this->buildRow($line, $cells, $this->rowHeight($project));
            $line++;
        }

        $zip = new ZipBuilder();
        $zip->add('[Content_Types].xml', $this->contentTypesXml());
        $zip->add('_rels/.rels', $this->rootRelsXml());
        $zip->add('docProps/app.xml', $this->appXml());
        $zip->add('docProps/core.xml', $this->coreXml());
        $zip->add('xl/workbook.xml', $this->workbookXml());
        $zip->add('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->add('xl/worksheets/sheet1.xml', $this->sheetXml($rows, $columns));
        $zip->add('xl/styles.xml', $this->stylesXml());
        $zip->add('xl/sharedStrings.xml', $this->sharedStringsXml($sharedStrings));

        $filename = 'project_plan_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $zip->output();
        exit;
    }

    private function rowHeight(array $project): float
    {
        $length = static function (string $value): int {
            return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        };

        $maxLength = max(
            $length((string) ($project['project_name'] ?? '')),
            $length((string) ($project['task_summary'] ?? '')),
            $length((string) ($project['completion_feedback'] ?? ''))
        );

        return $maxLength > 60 ? 56.0 : 36.0;
    }

    private function datetimeValue(string $value): string
    {
        $value = normalize_datetime_value($value);
        return $value !== '' ? $value : '';
    }

    private function workloadValue($value): string
    {
        $number = round((float) $value, 2);
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function buildRow(int $index, array $cells, float $height): string
    {
        return sprintf(
            '<row r="%d" spans="1:17" ht="%.2f" customHeight="1" x14ac:dyDescent="0.15">%s</row>',
            $index,
            $height,
            implode('', $cells)
        );
    }

    private function cellRef(int $columnIndex, int $rowIndex): string
    {
        $columnIndex++;
        $letters = '';
        while ($columnIndex > 0) {
            $mod = ($columnIndex - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $columnIndex = (int) floor(($columnIndex - 1) / 26);
        }

        return $letters . $rowIndex;
    }

    private function stringCell(string $ref, string $value, int $style, array &$sharedStrings, array &$stringIndex): string
    {
        if (!array_key_exists($value, $stringIndex)) {
            $stringIndex[$value] = count($sharedStrings);
            $sharedStrings[] = $value;
        }

        return sprintf('<c r="%s" s="%d" t="s"><v>%d</v></c>', $ref, $style, $stringIndex[$value]);
    }

    private function numberCell(string $ref, string $value, int $style): string
    {
        return sprintf('<c r="%s" s="%d"><v>%s</v></c>', $ref, $style, $value);
    }

    private function sheetXml(array $rows, array $columns): string
    {
        $colsXml = '';
        foreach ($columns as $index => $column) {
            $style = $index === 1 || $index === 12 ? 3 : 2;
            $colsXml .= sprintf(
                '<col min="%d" max="%d" width="%s" style="%d" customWidth="1"/>',
                $index + 1,
                $index + 1,
                $column['width'],
                $style
            );
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
            . 'xmlns:x14ac="http://schemas.microsoft.com/office/spreadsheetml/2009/9/ac">'
            . '<dimension ref="A1:Q' . count($rows) . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18" x14ac:dyDescent="0.15"/>'
            . '<cols>' . $colsXml . '</cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . '</worksheet>';
    }

    private function sharedStringsXml(array $strings): string
    {
        $items = '';
        foreach ($strings as $string) {
            $items .= '<si><t xml:space="preserve">' . htmlspecialchars($string, ENT_XML1) . '</t></si>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">'
            . $items
            . '</sst>';
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function appXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Codex</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>项目计划</vt:lpstr></vt:vector></TitlesOfParts>'
            . '<Company>phpstudy</Company><LinksUpToDate>false</LinksUpToDate><SharedDoc>false</SharedDoc><HyperlinksChanged>false</HyperlinksChanged><AppVersion>16.0300</AppVersion>'
            . '</Properties>';
    }

    private function coreXml(): string
    {
        $created = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>Codex</dc:creator><cp:lastModifiedBy>Codex</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="项目计划" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Microsoft YaHei"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Microsoft YaHei"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right><top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}
