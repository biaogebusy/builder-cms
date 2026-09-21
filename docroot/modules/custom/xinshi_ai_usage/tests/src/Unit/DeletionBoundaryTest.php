<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Deleting content never deletes usage facts (UB2.7).
 *
 * The facts reference jobs, runs, media and assets by string identifiers with
 * no foreign key, and no hook in either module reacts to a content deletion
 * by touching them. These are structural guards: they fail the build when a
 * cascade is introduced, instead of a reviewer having to notice.
 */
final class DeletionBoundaryTest extends TestCase {

  private const USAGE_TABLES = ['ai_usage_event', 'ai_provider_attempt', 'ai_usage_rollup_hour',
    'ai_usage_cost_entry', 'ai_price_book_version', 'ai_usage_watermark', 'ai_usage_delivery'];

  public function testUsageTablesReferenceContentWithoutForeignKeys(): void {
    $schema = xinshi_ai_usage_schema();
    foreach (self::USAGE_TABLES as $table) {
      $this->assertArrayHasKey($table, $schema);
      $this->assertArrayNotHasKey('foreign keys', $schema[$table], "$table must not cascade from content");
    }
  }

  public function testNoHookDeletesUsageFactsWhenContentIsDeleted(): void {
    $usage = dirname(__DIR__, 3);
    $runner = dirname($usage) . '/xinshi_ai';
    foreach (["$usage/xinshi_ai_usage.module", "$usage/xinshi_ai_usage.install"] as $file) {
      $this->assertDoesNotMatchRegularExpression('/function xinshi_ai_usage_[a-z_]*(pre)?delete\(/',
        (string) file_get_contents($file), basename($file));
    }
    // The task runner reacts to node deletion only to drop its own credential
    // entry; it never names a usage table.
    $module = (string) file_get_contents("$runner/xinshi_ai.module");
    foreach (self::USAGE_TABLES as $table) {
      $this->assertStringNotContainsString($table, $module);
    }
    $this->assertStringContainsString('xinshi_ai.image_job_credentials', $module);
    // Nothing under the runner's src/ deletes from the usage tables either.
    foreach ($this->phpFiles("$runner/src") as $file) {
      $source = (string) file_get_contents($file);
      foreach (self::USAGE_TABLES as $table) {
        $this->assertDoesNotMatchRegularExpression('/->delete\(\s*[\'"]' . preg_quote($table, '/') . '[\'"]/',
          $source, basename($file));
      }
    }
  }

  /**
   * @return list<string>
   */
  private function phpFiles(string $directory): array {
    $files = [];
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory,
      \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
      if ($file->getExtension() === 'php') {
        $files[] = $file->getPathname();
      }
    }
    sort($files);
    return $files;
  }

}
