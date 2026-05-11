<?php

declare(strict_types=1);

namespace App\Services;

final class PersonnelPerformanceExporter
{
    public function download(array $dataset): void
    {
        $sharedStrings = [];
        $stringIndex = [];
        $columns = [
            ['title' => '月份', 'width' => 8.5],
            ['title' => '日期', 'width' => 8.5],
            ['title' => '支持技术', 'width' => 12.5],
            ['title' => '所属部门', 'width' => 18.5],
            ['title' => '对口销售', 'width' => 12.5],
            ['title' => '项目名称', 'width' => 36],
            ['title' => '工作内容及完成情况', 'width' => 34],
            ['title' => '当天事项工作量', 'width' => 13],
            ['title' => '负责人确认工时', 'width' => 14],
            ['title' => '销售确认工时', 'width' => 14],
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
        foreach ($dataset['rows'] as $row) {
            $cells = [
                $this->numberCell($this->cellRef(0, $line), (string) $row['month'], 3),
                $this->numberCell($this->cellRef(1, $line), (string) $row['day'], 3),
                $row['person_name'] === ''
                    ? $this->emptyCell($this->cellRef(2, $line), 2)
                    : $this->stringCell($this->cellRef(2, $line), (string) $row['person_name'], 2, $sharedStrings, $stringIndex),
                $row['department'] === ''
                    ? $this->emptyCell($this->cellRef(3, $line), 2)
                    : $this->stringCell($this->cellRef(3, $line), (string) $row['department'], 2, $sharedStrings, $stringIndex),
                $row['project_sales'] === ''
                    ? $this->emptyCell($this->cellRef(4, $line), 2)
                    : $this->stringCell($this->cellRef(4, $line), (string) $row['project_sales'], 2, $sharedStrings, $stringIndex),
                $row['project_name'] === ''
                    ? $this->emptyCell($this->cellRef(5, $line), 2)
                    : $this->stringCell($this->cellRef(5, $line), (string) $row['project_name'], 2, $sharedStrings, $stringIndex),
                $row['task_summary'] === ''
                    ? $this->emptyCell($this->cellRef(6, $line), 2)
                    : $this->stringCell($this->cellRef(6, $line), (string) $row['task_summary'], 2, $sharedStrings, $stringIndex),
                $row['score'] === ''
                    ? $this->emptyCell($this->cellRef(7, $line), 4)
                    : $this->numberCell($this->cellRef(7, $line), $this->scoreValue($row['score']), 4),
                $this->emptyCell($this->cellRef(8, $line), 4),
                $this->emptyCell($this->cellRef(9, $line), 4),
            ];

            $rows[] = $this->buildRow($line, $cells, 20.0);
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

        $filename = 'personnel_performance_';
        if (!empty($dataset['person_name'])) {
            $filename .= $this->sanitizeFilename((string) $dataset['person_name']) . '_';
        }
        $filename .= $dataset['period'] . '_' . str_replace('-', '', (string) $dataset['anchor_date']) . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $zip->output();
        exit;
    }

    private function buildRow(int $index, array $cells, float $height): string
    {
        return sprintf(
            '<row r="%d" spans="1:10" ht="%.2f" customHeight="1" x14ac:dyDescent="0.15">%s</row>',
            $index,
            $height,
            implode('', $cells)
        );
    }

    private function cellRef(int $columnIndex, int $rowIndex): string
    {
        return chr(65 + $columnIndex) . $rowIndex;
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

    private function emptyCell(string $ref, int $style): string
    {
        return sprintf('<c r="%s" s="%d"/>', $ref, $style);
    }

    private function scoreValue($score): string
    {
        $number = round((float) $score, 2);
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function sanitizeFilename(string $value): string
    {
        $value = preg_replace('/[\\\\\\/:*?"<>|]+/', '_', $value) ?? 'person';
        $value = trim($value, " .\t\n\r\0\x0B");

        return $value !== '' ? $value : 'person';
    }

    private function sheetXml(array $rows, array $columns): string
    {
        $colsXml = '';
        foreach ($columns as $index => $column) {
            $style = $index <= 1 || $index >= 7 ? 3 : 2;
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
            . '<dimension ref="A1:J' . count($rows) . '"/>'
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
            . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>绩效统计</vt:lpstr></vt:vector></TitlesOfParts>'
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
            . '<sheets><sheet name="绩效统计" sheetId="1" r:id="rId1"/></sheets>'
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
            . '<font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Microsoft YaHei"/><family val="2"/></font>'
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
            . '<cellXfs count="5">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}
