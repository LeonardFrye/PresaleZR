<?php

declare(strict_types=1);

namespace App\Services;

final class ExcelExporter
{
    public function download(array $projects): void
    {
        $sharedStrings = [];
        $stringIndex = [];
        $feedbackTagOptions = ProjectService::feedbackTagOptions();

        $columns = [
            ['title' => '序号', 'width' => 5.75],
            ['title' => '项目区域', 'width' => 9.75],
            ['title' => '项目名称', 'width' => 19.25],
            ['title' => '项目销售', 'width' => 9.625],
            ['title' => '支撑岗位', 'width' => 10.75],
            ['title' => '支撑人员', 'width' => 10.75],
            ['title' => '开始时间', 'width' => 10.5],
            ['title' => '结束时间', 'width' => 14.25],
            ['title' => '工作任务【简述】', 'width' => 18.625],
            ['title' => '技术完成回执单', 'width' => 38.5],
            ['title' => '反馈标签', 'width' => 12.5],
            ['title' => '完成评价【销售反馈简述】', 'width' => 27.5],
        ];

        $rows = [];
        $rows[] = $this->buildRow(1, [
            $this->stringCell('A1', '技术支撑事业部项目管理平台', 13, $sharedStrings, $stringIndex),
            $this->emptyCell('B1', 13),
            $this->emptyCell('C1', 13),
            $this->emptyCell('D1', 13),
            $this->emptyCell('E1', 13),
            $this->emptyCell('F1', 13),
            $this->emptyCell('G1', 13),
            $this->emptyCell('H1', 13),
            $this->emptyCell('I1', 13),
            $this->emptyCell('J1', 13),
            $this->emptyCell('K1', 13),
            $this->emptyCell('L1', 13),
        ], 26.25);

        $headerCells = [];
        foreach ($columns as $index => $column) {
            $ref = chr(65 + $index) . '2';
            $style = $index >= 8 ? 2 : 1;
            if ($index === 10 || $index === 11) {
                $style = 12;
            }
            $headerCells[] = $this->stringCell($ref, $column['title'], $style, $sharedStrings, $stringIndex);
        }
        $rows[] = $this->buildRow(2, $headerCells);

        $line = 3;
        foreach ($projects as $project) {
            $feedbackKey = (string) ($project['feedback_tag'] ?? ProjectService::FEEDBACK_NORMAL);
            $feedbackLabel = $feedbackTagOptions[$feedbackKey] ?? $feedbackTagOptions[ProjectService::FEEDBACK_NORMAL];
            $cells = [
                $this->numberCell('A' . $line, (string) ($line - 2), 9),
                $this->stringCell('B' . $line, (string) $project['project_region'], 9, $sharedStrings, $stringIndex),
                $this->stringCell('C' . $line, (string) $project['project_name'], 9, $sharedStrings, $stringIndex),
                $this->stringCell('D' . $line, (string) $project['project_sales'], 9, $sharedStrings, $stringIndex),
                $this->stringCell('E' . $line, (string) $project['support_role'], 11, $sharedStrings, $stringIndex),
                $this->stringCell('F' . $line, (string) $project['support_personnel'], 11, $sharedStrings, $stringIndex),
                $this->stringCell('G' . $line, date('Y.n.j', strtotime($project['start_date'])), 10, $sharedStrings, $stringIndex),
                $this->stringCell('H' . $line, date('Y.n.j', strtotime($project['end_date'])), 10, $sharedStrings, $stringIndex),
                $this->stringCell('I' . $line, (string) $project['task_summary'], 6, $sharedStrings, $stringIndex),
                $this->stringCell('J' . $line, (string) ($project['receipt_name'] ?? ''), 3, $sharedStrings, $stringIndex),
                $this->stringCell('K' . $line, (string) $feedbackLabel, 11, $sharedStrings, $stringIndex),
                $this->stringCell('L' . $line, (string) ($project['completion_feedback'] ?? ''), 4, $sharedStrings, $stringIndex),
            ];

            $rows[] = $this->buildRow($line, $cells, $this->rowHeight($project, $feedbackLabel));
            $line++;
        }

        $sheetXml = $this->sheetXml($rows, $columns);
        $sharedXml = $this->sharedStringsXml($sharedStrings);
        $zip = new ZipBuilder();
        $zip->add('[Content_Types].xml', $this->contentTypesXml());
        $zip->add('_rels/.rels', $this->rootRelsXml());
        $zip->add('docProps/app.xml', $this->appXml());
        $zip->add('docProps/core.xml', $this->coreXml());
        $zip->add('xl/workbook.xml', $this->workbookXml());
        $zip->add('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->add('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->add('xl/styles.xml', $this->stylesXml());
        $zip->add('xl/sharedStrings.xml', $sharedXml);
        $zip->add('xl/theme/theme1.xml', $this->themeXml());

        $filename = 'project_plan_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $zip->output();
        exit;
    }

    private function rowHeight(array $project, string $feedbackLabel): float
    {
        $maxLength = max(
            strlen((string) $project['task_summary']),
            strlen((string) ($project['receipt_name'] ?? '')),
            strlen((string) ($project['completion_feedback'] ?? '')),
            strlen($feedbackLabel)
        );

        return $maxLength > 60 ? 58.0 : 39.95;
    }

    private function buildRow(int $index, array $cells, ?float $height = null): string
    {
        $attributes = sprintf('r="%d" spans="1:12"', $index);
        if ($height !== null) {
            $attributes .= sprintf(' ht="%.2f" customHeight="1"', $height);
        }
        $attributes .= ' x14ac:dyDescent="0.15"';

        return '<row ' . $attributes . '>' . implode('', $cells) . '</row>';
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
        return sprintf('<c r="%s" s="%d" />', $ref, $style);
    }

    private function sheetXml(array $rows, array $columns): string
    {
        $colsXml = '';
        foreach ($columns as $index => $column) {
            $style = 5;
            if ($index <= 2) {
                $style = 7;
            }
            if ($index === 6 || $index === 7) {
                $style = 8;
            }
            $min = $index + 1;
            $max = $index + 1;
            $colsXml .= sprintf(
                '<col min="%d" max="%d" width="%s" style="%d" customWidth="1"/>',
                $min,
                $max,
                $column['width'],
                $style
            );
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
            . 'xmlns:x14ac="http://schemas.microsoft.com/office/spreadsheetml/2009/9/ac">'
            . '<sheetPr/><dimension ref="A1:L' . count($rows) . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15" x14ac:dyDescent="0.15"/>'
            . '<cols>' . $colsXml . '</cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . '<mergeCells count="1"><mergeCell ref="A1:L1"/></mergeCells>'
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
            . '<Override PartName="/xl/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>'
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
            . '<sheets><sheet name="项目计划" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>'
            . '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="16"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="14">'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function themeXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Office Theme">'
            . '<a:themeElements><a:clrScheme name="Office"><a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1><a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1><a:dk2><a:srgbClr val="1F497D"/></a:dk2><a:lt2><a:srgbClr val="EEECE1"/></a:lt2><a:accent1><a:srgbClr val="4F81BD"/></a:accent1><a:accent2><a:srgbClr val="C0504D"/></a:accent2><a:accent3><a:srgbClr val="9BBB59"/></a:accent3><a:accent4><a:srgbClr val="8064A2"/></a:accent4><a:accent5><a:srgbClr val="4BACC6"/></a:accent5><a:accent6><a:srgbClr val="F79646"/></a:accent6><a:hlink><a:srgbClr val="0000FF"/></a:hlink><a:folHlink><a:srgbClr val="800080"/></a:folHlink></a:clrScheme><a:fontScheme name="Office"><a:majorFont><a:latin typeface="Calibri"/></a:majorFont><a:minorFont><a:latin typeface="Calibri"/></a:minorFont></a:fontScheme><a:fmtScheme name="Office"><a:fillStyleLst/><a:lnStyleLst/><a:effectStyleLst/><a:bgFillStyleLst/></a:fmtScheme></a:themeElements></a:theme>';
    }
}
