<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_knowledge;

use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpWord\IOFactory as WordFactory;
use PhpOffice\PhpWord\PhpWord;

/** Small generated documents with independently asserted facts and locations. */
final class DocumentFixtures {

  public static function office(string $extension, string $path): void {
    if ($extension === 'docx') {
      $word = new PhpWord();
      $word->addTitleStyle(1, ['bold' => TRUE]);
      $section = $word->addSection();
      $section->addTitle('Equipment handbook', 1);
      $section->addText('Quasarengine operating temperature 70 C');
      $section->addListItem('Mandatory earthing cable');
      $table = $section->addTable();
      foreach ([['Parameter', 'Value'], ['Nominal power', '750 W']] as $row) {
        $table->addRow();
        foreach ($row as $cell) {
          $table->addCell(3000)->addText($cell);
        }
      }
      WordFactory::createWriter($word, 'Word2007')->save($path);
      return;
    }
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet()->setTitle('Specifications');
    $sheet->fromArray([['Product', 'Power (W)'], ['Quasarengine', 750]]);
    $sheet->setCellValue('A5', 'Sparse row preserved');
    $book->createSheet()->setTitle('Warranty')->fromArray([['Term', 'Months'], ['Standard', 24]]);
    SpreadsheetFactory::createWriter($book, $extension === 'xlsx' ? 'Xlsx' : 'Xls')->save($path);
    $book->disconnectWorksheets();
  }

  public static function pdf(string $path, bool $withText = TRUE): void {
    $streams = $withText ? [
      'BT /F1 12 Tf 60 700 Td (Thermal output 750 W) Tj ET',
      'BT /F1 12 Tf 60 700 Td (Maintenance every 180 days) Tj ET',
    ] : ['', ''];
    $objects = [
      '<< /Type /Catalog /Pages 2 0 R >>',
      '<< /Type /Pages /Kids [3 0 R 5 0 R] /Count 2 >>',
      '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> >> /Contents 4 0 R >>',
      '<< /Length ' . strlen($streams[0]) . ">>\nstream\n" . $streams[0] . "\nendstream",
      '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> >> /Contents 6 0 R >>',
      '<< /Length ' . strlen($streams[1]) . ">>\nstream\n" . $streams[1] . "\nendstream",
      '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $i => $object) {
      $offsets[] = strlen($pdf);
      $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 8\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
      $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    file_put_contents($path, $pdf);
  }

}
