<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel;

use Symfony\Component\Filesystem\Filesystem;
use Yethee\Tiktoken\EncoderProvider;

/**
 * Trait for isolating Tiktoken cache in tests.
 *
 * This trait provides methods to create isolated
 * Tiktoken cache directories for each test to prevent
 * cache conflicts during parallel test execution.
 */
trait TiktokenCacheIsolationTrait {

  /**
   * The isolated Tiktoken cache directory for this test.
   *
   * @var string|null
   */
  protected ?string $isolatedTiktokenCacheDir = NULL;

  /**
   * Creates an isolated Tiktoken cache directory for the current test.
   *
   * This method creates a test-specific cache directory and optionally
   * pre-warms it with vocabulary files for specified models.
   *
   * @param array $models
   *   (optional) Array of model names to pre-warm the cache for.
   *   Examples: ['gpt-4', 'gpt-3.5-turbo', 'text-davinci-003'].
   */
  protected function isolateTiktokenCache(array $models = []): void {
    // Ensure the cache directory is created and configured.
    $this->ensureTiktokenCacheDir();

    // Pre-download vocabulary files to avoid
    // download issues during tests.
    if (!empty($models)) {
      try {
        $provider = new EncoderProvider();
        foreach ($models as $model) {
          try {
            $provider->getForModel($model);
          }
          catch (\Exception $e) {
            // Log but continue with other models.
            error_log(sprintf("Failed to pre-warm Tiktoken cache for model '%s': %s", $model, $e->getMessage()));
          }
        }
      }
      catch (\Exception $e) {
        // If Tiktoken fails to initialize entirely, log but don't break tests.
        error_log(sprintf("Failed to initialize Tiktoken provider: %s", $e->getMessage()));
      }
    }
  }

  /**
   * Ensures the isolated Tiktoken cache directory exists and is configured.
   *
   * Creates the directory if it doesn't exist and sets up environment
   * variables for Tiktoken to use it.
   *
   * @return string
   *   The path to the isolated cache directory.
   */
  protected function ensureTiktokenCacheDir(): string {
    if ($this->isolatedTiktokenCacheDir === NULL) {
      // Use a unique identifier for this test instance.
      $test_id = method_exists($this, 'sortId') ? $this->sortId() : uniqid('test_', TRUE);

      // Create a hash for a shorter, unique directory name.
      $hash = substr(hash('sha256', $test_id), 0, 12);

      $cache_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tiktoken_' . $hash;

      if (!is_dir($cache_dir)) {
        if (!mkdir($cache_dir, 0755, TRUE) && !is_dir($cache_dir)) {
          throw new \RuntimeException(sprintf('Failed to create Tiktoken cache directory: %s', $cache_dir));
        }
      }

      // Set environment variable so Tiktoken uses this directory.
      putenv("TIKTOKEN_CACHE_DIR=$cache_dir");
      $_ENV['TIKTOKEN_CACHE_DIR'] = $cache_dir;

      $this->isolatedTiktokenCacheDir = $cache_dir;
    }

    return $this->isolatedTiktokenCacheDir;
  }

  /**
   * Cleans up the isolated Tiktoken cache directory.
   *
   * This should be called in tearDown() or similar cleanup methods.
   */
  protected function cleanupTiktokenCache(): void {
    if (!$this->isolatedTiktokenCacheDir || !is_dir($this->isolatedTiktokenCacheDir)) {
      $this->isolatedTiktokenCacheDir = NULL;
      return;
    }

    // Verify it's in temp directory.
    $cache_path = realpath($this->isolatedTiktokenCacheDir);
    $temp_path = realpath(sys_get_temp_dir());

    if ($cache_path && $temp_path && str_starts_with($cache_path, $temp_path)) {
      $filesystem = new Filesystem();
      $filesystem->remove($cache_path);
    }

    $this->isolatedTiktokenCacheDir = NULL;
    putenv('TIKTOKEN_CACHE_DIR');
    unset($_ENV['TIKTOKEN_CACHE_DIR']);
  }

}
