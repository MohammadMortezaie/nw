<?php
/**
 * Convert an .xlsx workbook (first sheet) into CSV bytes.
 *
 * Uses ZipArchive + SimpleXML — no PhpSpreadsheet dependency.
 *
 * @package NW_Fuel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Read the first worksheet of an .xlsx file and return CSV text.
 */
function nw_fuel_xlsx_to_csv(string $path): string|WP_Error
{
    if (! is_readable($path)) {
        return new WP_Error('nw_fuel_xlsx_unreadable', __('Unable to read the Excel file.', 'nw-fuel'), ['status' => 400]);
    }

    if (! class_exists(ZipArchive::class)) {
        return new WP_Error(
            'nw_fuel_xlsx_zip',
            __('PHP ZipArchive is required to read Excel files. Upload a CSV instead.', 'nw-fuel'),
            ['status' => 500]
        );
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return new WP_Error('nw_fuel_xlsx_open', __('Unable to open the Excel file. Save as .xlsx or upload a CSV.', 'nw-fuel'), ['status' => 400]);
    }

    $shared = [];
    $ss     = $zip->getFromName('xl/sharedStrings.xml');
    if (is_string($ss) && $ss !== '') {
        $parsed = nw_fuel_xlsx_parse_shared_strings($ss);
        if (is_wp_error($parsed)) {
            $zip->close();
            return $parsed;
        }
        $shared = $parsed;
    }

    $sheet_xml = nw_fuel_xlsx_first_sheet_xml($zip);
    $zip->close();

    if ($sheet_xml === '') {
        return new WP_Error('nw_fuel_xlsx_no_sheet', __('Excel file has no worksheet.', 'nw-fuel'), ['status' => 400]);
    }

    $grid = nw_fuel_xlsx_sheet_to_grid($sheet_xml, $shared);
    if ($grid === []) {
        return new WP_Error('nw_fuel_xlsx_empty', __('Excel sheet is empty.', 'nw-fuel'), ['status' => 400]);
    }

    $csv = nw_fuel_xlsx_grid_to_csv($grid);
    if ($csv === '') {
        return new WP_Error('nw_fuel_xlsx_csv', __('Unable to convert Excel sheet to CSV.', 'nw-fuel'), ['status' => 500]);
    }

    return $csv;
}

/**
 * @return list<string>|WP_Error
 */
function nw_fuel_xlsx_parse_shared_strings(string $xml): array|WP_Error
{
    $sx = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
    if ($sx === false) {
        return new WP_Error('nw_fuel_xlsx_sst', __('Invalid Excel shared-strings table.', 'nw-fuel'), ['status' => 400]);
    }

    $nodes = $sx->xpath('//*[local-name()="si"]');
    if (! is_array($nodes)) {
        return [];
    }

    $out = [];
    foreach ($nodes as $si) {
        $out[] = nw_fuel_xlsx_plain_text($si);
    }

    return $out;
}

function nw_fuel_xlsx_first_sheet_xml(ZipArchive $zip): string
{
    $wb   = $zip->getFromName('xl/workbook.xml');
    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

    if (is_string($wb) && $wb !== '' && is_string($rels) && $rels !== '') {
        $path = nw_fuel_xlsx_first_sheet_path($wb, $rels);
        if ($path !== '') {
            $xml = $zip->getFromName($path);
            if (is_string($xml) && $xml !== '') {
                return $xml;
            }
        }
    }

    foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet.xml'] as $cand) {
        $xml = $zip->getFromName($cand);
        if (is_string($xml) && $xml !== '') {
            return $xml;
        }
    }

    return '';
}

function nw_fuel_xlsx_first_sheet_path(string $workbook_xml, string $rels_xml): string
{
    $wb = simplexml_load_string($workbook_xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
    if ($wb === false) {
        return '';
    }

    $sheets = $wb->xpath('//*[local-name()="sheet"]');
    if (! is_array($sheets) || $sheets === []) {
        return '';
    }

    $rid = '';
    foreach ($sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships') as $name => $value) {
        if ((string) $name === 'id') {
            $rid = (string) $value;
            break;
        }
    }
    if ($rid === '') {
        $rid = (string) ($sheets[0]['id'] ?? '');
    }
    if ($rid === '') {
        return '';
    }

    $rels = simplexml_load_string($rels_xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
    if ($rels === false) {
        return '';
    }

    $target = '';
    foreach ($rels->xpath('//*[local-name()="Relationship"]') ?: [] as $rel) {
        if ((string) ($rel['Id'] ?? '') === $rid) {
            $target = (string) ($rel['Target'] ?? '');
            break;
        }
    }
    if ($target === '') {
        return '';
    }

    $target = ltrim(str_replace('\\', '/', $target), '/');
    if (str_starts_with($target, 'xl/')) {
        return $target;
    }

    return 'xl/' . $target;
}

/**
 * @param list<string> $shared
 * @return list<list<string>>
 */
function nw_fuel_xlsx_sheet_to_grid(string $xml, array $shared): array
{
    $sx = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
    if ($sx === false) {
        return [];
    }

    $rows = $sx->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]');
    if (! is_array($rows) || $rows === []) {
        return [];
    }

    $grid    = [];
    $max_col = 0;

    foreach ($rows as $row) {
        $cells = $row->xpath('./*[local-name()="c"]');
        if (! is_array($cells) || $cells === []) {
            continue;
        }

        $line = [];
        $col  = 0;
        foreach ($cells as $c) {
            $ref = (string) ($c['r'] ?? '');
            $col = $ref !== '' ? nw_fuel_xlsx_col_index($ref) : $col;
            $line[$col] = nw_fuel_xlsx_cell_value($c, $shared);
            $max_col    = max($max_col, $col);
            ++$col;
        }

        $r_attr = (int) ($row['r'] ?? 0);
        $idx    = $r_attr > 0 ? $r_attr - 1 : count($grid);
        $grid[$idx] = $line;
    }

    if ($grid === []) {
        return [];
    }

    ksort($grid);
    $normalized = [];
    foreach ($grid as $line) {
        $out = [];
        for ($i = 0; $i <= $max_col; $i++) {
            $out[] = isset($line[$i]) ? (string) $line[$i] : '';
        }
        if (implode('', $out) === '') {
            continue;
        }
        $normalized[] = $out;
    }

    return $normalized;
}

/**
 * @param list<string> $shared
 */
function nw_fuel_xlsx_cell_value(SimpleXMLElement $cell, array $shared): string
{
    $type = (string) ($cell['t'] ?? '');

    if ($type === 'inlineStr') {
        $inline = $cell->xpath('./*[local-name()="is"]');
        if (isset($inline[0])) {
            return trim(nw_fuel_xlsx_plain_text($inline[0]));
        }

        return '';
    }

    $v_nodes = $cell->xpath('./*[local-name()="v"]');
    $v       = isset($v_nodes[0]) ? trim((string) $v_nodes[0]) : '';

    if ($type === 's') {
        $i = (int) $v;

        return (string) ($shared[$i] ?? '');
    }

    if ($type === 'b') {
        return $v === '1' ? '1' : '0';
    }

    return $v;
}

function nw_fuel_xlsx_plain_text(SimpleXMLElement $node): string
{
    $texts = $node->xpath('.//*[local-name()="t"]');
    if (! is_array($texts) || $texts === []) {
        return trim((string) $node);
    }

    $out = '';
    foreach ($texts as $t) {
        $out .= (string) $t;
    }

    return $out;
}

function nw_fuel_xlsx_col_index(string $cell_ref): int
{
    $letters = strtoupper((string) preg_replace('/[^A-Z]/i', '', $cell_ref));
    if ($letters === '') {
        return 0;
    }

    $n   = 0;
    $len = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $n = ($n * 26) + (ord($letters[$i]) - 64);
    }

    return max(0, $n - 1);
}

/**
 * @param list<list<string>> $grid
 */
function nw_fuel_xlsx_grid_to_csv(array $grid): string
{
    $stream = fopen('php://temp', 'r+');
    if ($stream === false) {
        return '';
    }

    foreach ($grid as $row) {
        fputcsv($stream, $row);
    }
    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);

    return is_string($csv) ? $csv : '';
}
