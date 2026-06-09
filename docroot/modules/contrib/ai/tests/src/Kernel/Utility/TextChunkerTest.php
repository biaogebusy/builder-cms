<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Utility;

use Drupal\Component\Serialization\Json;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ai\Kernel\TiktokenCacheIsolationTrait;

/**
 * The text chunker utility test class.
 *
 * @coversDefaultClass \Drupal\ai\Utility\TextChunker
 *
 * @group ai
 */
class TextChunkerTest extends KernelTestBase {

  use TiktokenCacheIsolationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['ai'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Extract models from our test data and isolate
    // the tiktoken cache to avoid race conditions
    // in the temp directory.
    $test_data = static::modelToChunkProvider();
    $models = $this->extractModelsFromTestData($test_data);
    $this->isolateTiktokenCache($models);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Clean up isolated cache directory.
    $this->cleanupTiktokenCache();

    parent::tearDown();
  }

  /**
   * Tests token chunk counts.
   *
   * @param string $model
   *   The parameter type.
   * @param string $file
   *   The markdown file to use.
   * @param int $expected_count
   *   The expected count.
   * @param int $max_size
   *   The maximum chunk size.
   * @param int $min_overlap
   *   The minimum chunk overlap.
   *
   * @dataProvider modelToChunkProvider
   */
  public function testTokenCount(
    string $model,
    string $file,
    int $expected_count,
    int $max_size,
    int $min_overlap,
  ): void {
    $contents = file_get_contents(__DIR__ . '/../../../assets/sample-texts/' . $file . '.md');
    /** @var \Drupal\ai\Utility\TextChunkerInterface $text_chunker */
    $text_chunker = \Drupal::service('ai.text_chunker');
    $text_chunker->setModel($model);
    $chunks = $text_chunker->chunkText(
      $contents,
      $max_size,
      $min_overlap,
    );

    // Json file containing expected content.
    $filename = $file . '-' . $model . '-' . $max_size . '-' . $min_overlap . '-expected-chunks.json';
    $directory = __DIR__ . '/../../../assets/sample-texts/';

    // Uncomment this line to rewrite the json files. If you do this, you must
    // check that the json files all contain logical chunks as expected.
    // phpcs:ignore
    //$this->writeExpectedJsonForTest($directory. $filename, $chunks);

    // Get the expected chunks from the json file.
    $expected_chunks = Json::decode(file_get_contents($directory . $filename));

    // Check that the expected results match.
    $this->assertCount($expected_count, $chunks);
    $this->assertSame($expected_chunks, $chunks);
  }

  /**
   * Helper method to write the expected json for testing.
   *
   * @param string $file
   *   The full filename of the json file.
   * @param array $chunks
   *   The chunks to encode.
   */
  protected function writeExpectedJsonForTest(string $file, array $chunks): void {
    // Same as core Json::encode() except adding JSON_PRETTY_PRINT as well
    // so we can easier check that new encodings are fine.
    $json = json_encode($chunks, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT);
    file_put_contents($file, $json);
  }

  /**
   * Provides model, expected count, max size, and min overlap.
   *
   * @return array
   *   Model, expected count, max size, and min overlap.
   */
  public static function modelToChunkProvider(): array {
    return [
      ['gpt-3.5', 'sample-1', 18, 64, 5],
      ['gpt-4o', 'sample-1', 18, 64, 5],
      ['gpt-4o', 'sample-1', 16, 64, 0],
      ['gpt-3.5', 'sample-1', 9, 128, 12],
      ['gpt-4o', 'sample-1', 9, 128, 12],
      ['gpt-3.5', 'sample-1', 10, 128, 24],
      ['gpt-4o', 'sample-1', 10, 128, 24],
      ['gpt-3.5', 'sample-2', 18, 64, 5],
      ['gpt-4o', 'sample-2', 18, 64, 5],
      ['gpt-3.5', 'sample-3', 5, 64, 5],
      ['gpt-4o', 'sample-3', 3, 64, 5],
      ['gpt-4o', 'sample-3', 2, 128, 24],
    ];
  }

  /**
   * Extract unique models from test data provider.
   *
   * @param array $test_data
   *   Test data array where first element of each case is the model name.
   *
   * @return array
   *   Array of unique model names.
   */
  protected function extractModelsFromTestData(array $test_data): array {
    $models = [];
    foreach ($test_data as $test_case) {
      if (isset($test_case[0]) && is_string($test_case[0])) {
        $models[] = $test_case[0];
      }
    }
    return array_unique($models);
  }

}
