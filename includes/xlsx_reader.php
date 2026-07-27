<?php
declare(strict_types=1);

/**
 * Lightweight XLSX Reader & Writer using PHP ZipArchive & SimpleXML
 * Đọc và Xuất file Excel chuẩn .xlsx không cần thư viện ngoài
 */

function parseXlsxFile(string $filePath): array {
    $rows = [];
    
    if (!class_exists('ZipArchive')) {
        return [];
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== TRUE) {
        return [];
    }

    $sharedStrings = [];
    $sharedStringsData = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedStringsData !== false) {
        $xml = simplexml_load_string($sharedStringsData);
        if ($xml) {
            foreach ($xml->si as $val) {
                if (isset($val->t)) {
                    $sharedStrings[] = (string)$val->t;
                } elseif (isset($val->r)) {
                    $text = '';
                    foreach ($val->r as $r) {
                        $text .= (string)$r->t;
                    }
                    $sharedStrings[] = $text;
                } else {
                    $sharedStrings[] = '';
                }
            }
        }
    }

    $sheetData = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if ($sheetData !== false) {
        $xml = simplexml_load_string($sheetData);
        if ($xml && isset($xml->sheetData)) {
            foreach ($xml->sheetData->row as $r) {
                $row = [];
                foreach ($r->c as $c) {
                    $cellValue = (string)$c->v;
                    $type = (string)$c['t'];

                    if ($type === 's') { // Shared String
                        $cellValue = $sharedStrings[(int)$cellValue] ?? '';
                    }
                    $row[] = trim($cellValue);
                }
                if (!empty(array_filter($row))) {
                    $rows[] = $row;
                }
            }
        }
    }

    return $rows;
}

/**
 * Tạo và tải về file Excel chuẩn .xlsx
 */
function downloadXlsxFile(string $filename, array $headers, array $dataRows): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_') . '.xlsx';
    
    $zip = new ZipArchive();
    if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        die("Không thể tạo file tạm Excel.");
    }

    // 1. Phân tích chuỗi dùng chung (sharedStrings)
    $strings = [];
    $stringIndexMap = [];
    
    $getStringIndex = function($str) use (&$strings, &$stringIndexMap) {
        $str = (string)$str;
        if (!isset($stringIndexMap[$str])) {
            $stringIndexMap[$str] = count($strings);
            $strings[] = htmlspecialchars($str, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        return $stringIndexMap[$str];
    };

    // 2. Tạo sheetData XML
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n";
    $sheetXml .= '  <sheetData>' . "\n";

    $cols = ['A','B','C','D','E','F','G','H','I','J','K','L'];

    // Dòng 1: Header
    $sheetXml .= '    <row r="1">' . "\n";
    foreach ($headers as $colIdx => $hText) {
        $idx = $getStringIndex($hText);
        $colLetter = $cols[$colIdx] ?? 'A';
        $sheetXml .= '      <c r="' . $colLetter . '1" t="s"><v>' . $idx . '</v></c>' . "\n";
    }
    $sheetXml .= '    </row>' . "\n";

    // Các dòng dữ liệu
    $rowNum = 2;
    foreach ($dataRows as $row) {
        $sheetXml .= '    <row r="' . $rowNum . '">' . "\n";
        foreach ($row as $colIdx => $val) {
            $idx = $getStringIndex($val);
            $colLetter = $cols[$colIdx] ?? 'A';
            $sheetXml .= '      <c r="' . $colLetter . $rowNum . '" t="s"><v>' . $idx . '</v></c>' . "\n";
        }
        $sheetXml .= '    </row>' . "\n";
        $rowNum++;
    }

    $sheetXml .= '  </sheetData>' . "\n";
    $sheetXml .= '</worksheet>';

    // 3. Tạo sharedStrings XML
    $sstXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $sstXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">' . "\n";
    foreach ($strings as $s) {
        $sstXml .= '  <si><t>' . $s . '</t></si>' . "\n";
    }
    $sstXml .= '</sst>';

    // 4. Các file cấu trúc cơ bản của XLSX
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Sheet1" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';

    // Đóng gói các file vào zip archive .xlsx
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/sharedStrings.xml', $sstXml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    // Xuất dữ liệu ra browser
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tempFile));
    header('Cache-Control: max-age=0');

    readfile($tempFile);
    @unlink($tempFile);
    exit;
}
