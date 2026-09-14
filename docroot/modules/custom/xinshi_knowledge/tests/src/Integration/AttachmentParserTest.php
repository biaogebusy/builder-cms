<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_knowledge\Integration;

use Drupal\file\Entity\File;
use Drupal\Tests\xinshi_knowledge\DocumentFixtures;
use Drupal\xinshi_knowledge\Knowledge;
use Drupal\xinshi_knowledge\Service\AttachmentParser;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/DocumentFixtures.php';

final class AttachmentParserTest extends TestCase {

  public static function officeFormats(): array {
    return [['docx'], ['xlsx'], ['xls']];
  }

  #[DataProvider('officeFormats')]
  public function testRealOfficeDocumentsRetainFactsTablesAndLocations(string $extension): void {
    $uri = 'private://fixture-' . $extension . '.' . $extension;
    DocumentFixtures::office($extension, \Drupal::service('file_system')->realpath($uri));
    $parsed = \Drupal::service('xinshi_knowledge.parser')->parse($this->file($uri));
    $text = implode("\n", array_column($parsed['segments'], 'text'));
    $this->assertStringContainsString('Quasarengine', $text);
    $this->assertStringContainsString('750', $text);
    $this->assertSame(hash_file('sha256', $uri), $parsed['hash']);
    if ($extension === 'docx') {
      $this->assertStringContainsString('Mandatory earthing cable', $text);
      $this->assertStringContainsString('Nominal power | 750 W', $text);
      $this->assertNotEmpty(array_filter($parsed['segments'], fn($segment) =>
        ($segment['location']['section'] ?? '') === 'Equipment handbook'));
    }
    else {
      $sheets = array_column(array_column($parsed['segments'], 'location'), 'sheet');
      $this->assertContains('Specifications', $sheets);
      $this->assertContains('Warranty', $sheets);
      $this->assertStringContainsString('Power (W)', $text);
      $this->assertStringContainsString('Sparse row preserved', $text);
      $this->assertStringContainsString('24', $text);
    }
  }

  public function testRealPdfRetainsPagesAndRecognizesNoText(): void {
    $uri = 'private://manual.pdf';
    DocumentFixtures::pdf(\Drupal::service('file_system')->realpath($uri));
    $parser = \Drupal::service('xinshi_knowledge.parser');
    $parsed = $parser->parse($this->file($uri));
    $this->assertSame([['page' => 1], ['page' => 2]], array_column($parsed['segments'], 'location'));
    $this->assertStringContainsString('750 W', $parsed['segments'][0]['text']);
    $this->assertStringContainsString('180 days', $parsed['segments'][1]['text']);
    $empty = 'private://scan.pdf';
    DocumentFixtures::pdf(\Drupal::service('file_system')->realpath($empty), FALSE);
    $this->expectExceptionMessage('needs_ocr');
    $parser->parse($this->file($empty));
  }

  public function testLegacyWordBinaryCanBeRead(): void {
    $uri = 'private://word97.doc';
    copy(dirname(__DIR__, 2) . '/fixtures/testWORD.doc', $uri);
    $parsed = \Drupal::service('xinshi_knowledge.parser')->parse($this->file($uri));
    $text = implode("\n", array_column($parsed['segments'], 'text'));
    $this->assertStringContainsString('This is a sample Microsoft Word Document.', $text);
    $this->assertNotEmpty($parsed['segments'][0]['location']);
  }

  public static function encodings(): array {
    return [['UTF-8', ''], ['UTF-8', "\xEF\xBB\xBF"], ['UTF-16LE', "\xFF\xFE"],
      ['UTF-16BE', "\xFE\xFF"], ['GB18030', '']];
  }

  #[DataProvider('encodings')]
  public function testTextDecodingPreservesLineNumbers(string $encoding, string $bom): void {
    $uri = 'private://encoded.txt';
    file_put_contents($uri, $bom . mb_convert_encoding("设备说明\r\n\r\n额定功率 750 瓦", $encoding, 'UTF-8'));
    $parsed = \Drupal::service('xinshi_knowledge.parser')->parse($this->file($uri));
    $this->assertSame([
      ['location' => ['line' => 1], 'text' => '设备说明'],
      ['location' => ['line' => 3], 'text' => '额定功率 750 瓦'],
    ], $parsed['segments']);
  }

  public function testOversizedFileIsRejectedBeforeContactingParser(): void {
    $uri = 'private://oversize.pdf';
    $handle = fopen($uri, 'wb');
    ftruncate($handle, Knowledge::MAX_FILE_BYTES + 1);
    fclose($handle);
    $this->expectExceptionMessage('too_large');
    \Drupal::service('xinshi_knowledge.parser')->parse($this->file($uri));
  }

  public static function invalidResponses(): array {
    return [
      [200, '<!DOCTYPE html [<!ENTITY x SYSTEM "file:///etc/passwd">]><html><body>&x;</body></html>', [], 'invalid_document'],
      [200, 'not XML', [], 'invalid_document'],
      [200, '', ['Content-Length' => 9 * 1024 * 1024], 'too_large'],
      [302, '', ['Location' => 'http://untrusted.invalid'], 'parser_unavailable'],
      [422, '', [], 'invalid_document'],
      [413, '', [], 'too_large'],
      [503, '', [], 'parser_unavailable'],
    ];
  }

  #[DataProvider('invalidResponses')]
  public function testParserResponseLimitsAndErrors(int $status, string $body, array $headers, string $code): void {
    $uri = 'private://response.pdf';
    file_put_contents($uri, '%PDF-1.4');
    $client = new Client(['handler' => new MockHandler([new Response($status, $headers, $body)])]);
    $parser = new AttachmentParser($client, \Drupal::configFactory());
    $this->expectExceptionMessage($code);
    $parser->parse($this->file($uri));
  }

  public function testActiveMarkupNeverBecomesSourceText(): void {
    $uri = 'private://markup.docx';
    file_put_contents($uri, 'fixture');
    $xml = '<html xmlns="http://www.w3.org/1999/xhtml"><head><title>hidden metadata</title></head>' .
      '<body><h1>Facts</h1><p>Safe text</p><script>hidden script</script><style>hidden style</style></body></html>';
    $client = new Client(['handler' => new MockHandler([new Response(200, [], $xml)])]);
    $parser = new AttachmentParser($client, \Drupal::configFactory());
    $text = implode("\n", array_column($parser->parse($this->file($uri))['segments'], 'text'));
    $this->assertStringContainsString('Safe text', $text);
    $this->assertStringNotContainsString('hidden', $text);
  }

  private function file(string $uri): File {
    return File::create(['uri' => $uri, 'filename' => basename($uri)]);
  }

}
