<?php

declare(strict_types=1);

/**
 * @file
 * Spreadsheet source checks in the same disposable Drupal site as integration.php.
 */

use Drupal\xinshi_office\OfficeInput;

$settings->set('write_types', ['word', 'sheet'])->save();
asUser($owner);
$sheet_content = value(['version' => 1, 'type' => 'workbook', 'dateSystem' => '1904',
  'styles' => (object) ['date' => ['numFmt' => 'yyyy-mm-dd']],
  'sheets' => [['id' => 'data', 'name' => 'Data', 'rows' => 1000, 'columns' => 26,
    'defaultRowHeight' => 15, 'defaultColumnWidth' => 8.43, 'rowSizes' => [], 'columnSizes' => [],
    'merges' => [], 'freeze' => ['rows' => 1, 'columns' => 0], 'cells' => [
      ['row' => 0, 'column' => 0, 'value' => ['type' => 'text', 'value' => '001234567890123456789']],
      ['row' => 1, 'column' => 0, 'value' => ['type' => 'date', 'value' => 0], 'style' => 'date'],
      ['row' => 2, 'column' => 0, 'value' => ['type' => 'formula', 'formula' => '=SUM(A2:A2)+1']],
    ]]]]);
$sheet_input = createInput(); $sheet_input->kind = 'sheet'; $sheet_input->content = $sheet_content;
$first_sheet = $documents->write(NULL, $sheet_input);
$sheet_id = $first_sheet['documentId'];
check(OfficeInput::encode($documents->read($sheet_id)['content']) === OfficeInput::encode($sheet_content),
  'Spreadsheet retains literal identifiers, formulas and the 1904 date system');
check($documents->write(NULL, $sheet_input) === $first_sheet, 'Spreadsheet create receipt is idempotent');
$sheet_update = updateInput(1); $sheet_update->content = clone $sheet_content;
$sheet_update->source = 'ai';
$second_sheet = $documents->write($sheet_id, $sheet_update);
check($second_sheet['revision'] === 2 && $documents->write($sheet_id, $sheet_update) === $second_sheet,
  'Spreadsheet CAS and exact AI receipt share the document transaction');
$stale = clone $sheet_update; $stale->operationId = id();
denied(fn() => $documents->write($sheet_id, $stale), 'revision_conflict', 'Spreadsheet stale source is refused');
asUser($other);
denied(fn() => $documents->read($sheet_id), 'document_unavailable', 'Other owners cannot read spreadsheet cells');
asUser($owner);
$settings->set('write_types', ['word'])->save();
check($documents->read($sheet_id)['revision'] === 2 && $documents->find($sheet_update->operationId) === $second_sheet,
  'Spreadsheet withdrawal preserves authorized reads and receipts');
$disabled = clone $sheet_input; $disabled->operationId = id();
denied(fn() => $documents->write(NULL, $disabled), 'write_disabled', 'Spreadsheet writes require explicit type enablement');
$settings->set('write_types', ['word', 'sheet'])->save();

foreach (['=WEBSERVICE("https://invalid")', '=INDIRECT("A1")', '=[book]Data!A1', '=SUM(A:A)',
  '=SUM(Table[Column])', '=1e999', '=1+', '=' . str_repeat('(', 40) . '1' . str_repeat(')', 40)] as $formula) {
  $bad = unserialize(serialize($sheet_input)); $bad->operationId = id();
  $bad->content->sheets[0]->cells[2]->value->formula = $formula;
  denied(fn() => $documents->write(NULL, $bad), 'invalid_formula', 'Unsupported formula never reaches persistence');
}
foreach (['reference', 'cache', 'duplicate', 'merge', 'style', 'grid', 'hidden', 'type'] as $case) {
  $bad = unserialize(serialize($sheet_input)); $bad->operationId = id();
  $sheet = $bad->content->sheets[0];
  switch ($case) {
    case 'reference': $sheet->cells[2]->value->formula = '=Missing!A1'; break;
    case 'cache': $sheet->cells[2]->value->value = 2; break;
    case 'duplicate': $sheet->cells[] = $sheet->cells[0]; break;
    case 'merge': $sheet->merges[] = (object) ['startRow' => 0, 'endRow' => 1, 'startColumn' => 0, 'endColumn' => 0]; break;
    case 'style': $sheet->cells[0]->style = 'missing'; break;
    case 'grid': $sheet->rows = 100000; break;
    case 'hidden': $sheet->hidden = TRUE; break;
    case 'type': $sheet->cells[1]->value->value = '0'; break;
  }
  $count = rows('revision');
  try { $documents->write(NULL, $bad); throw new RuntimeException('Invalid workbook accepted'); }
  catch (\Drupal\xinshi_office\OfficeException) { check(rows('revision') === $count, 'Invalid ' . $case . ' leaves storage unchanged'); }
}

$zip = new ZipArchive(); $zip->open('/tmp/office-test/source.xlsx', ZipArchive::CREATE);
$zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
$zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
$zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>');
$zip->close();
$xlsx = $files->upload((object) ['fileId' => id(), 'kind' => 'sheet', 'role' => 'original', 'name' => 'Source.xlsx'], '/tmp/office-test/source.xlsx');
$sheet_import = clone $sheet_input; $sheet_import->operationId = id(); $sheet_import->source = 'import';
$sheet_import->originalFileId = $xlsx['fileId'];
$sheet_import->importReport = (object) ['sourceSha256' => $xlsx['sha256'], 'importer' => 'sheet-v1', 'issues' => []];
$imported_sheet = $documents->write(NULL, $sheet_import);
check(hash_file('sha256', $documents->file($imported_sheet['documentId'], $xlsx['fileId'])['path']) === $xlsx['sha256'],
  'Spreadsheet original remains a separate exact private XLSX');
