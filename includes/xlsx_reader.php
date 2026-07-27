<?php
declare(strict_types=1);

/**
 * Lightweight XLSX Reader using PHP ZipArchive & SimpleXMLElement
 * Đọc file Excel .xlsx thành mảng dữ liệu không cần thư viện bên thứ 3
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

    // 1. Đọc chuỗi dùng chung (sharedStrings.xml)
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

    // 2. Đọc dữ liệu trang 1 (sheet1.xml)
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
