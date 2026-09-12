<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Utility;

use Drupal\ai\Utility\Tokenizer;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ai\Kernel\TiktokenCacheIsolationTrait;

/**
 * Tests the Tokenizer utility.
 *
 * @coversDefaultClass \Drupal\ai\Utility\Tokenizer
 *
 * @group ai
 */
class TokenizerTest extends KernelTestBase {

  use TiktokenCacheIsolationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ai'];

  /**
   * The tokenizer service.
   *
   * @var \Drupal\ai\Utility\Tokenizer
   */
  protected Tokenizer $tokenizer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->isolateTiktokenCache(['gpt-4']);
    $this->tokenizer = $this->container->get('ai.tokenizer');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->cleanupTiktokenCache();
    parent::tearDown();
  }

  /**
   * Tests that tokenizing the same string always returns the same tokens.
   *
   * @covers ::getTokens
   * @covers ::setModel
   */
  public function testTokenizationIsConsistent(): void {
    $this->tokenizer->setModel('gpt-4');

    $text = 'Hello world, this is a test of the tokenizer.';
    $first_run = $this->tokenizer->getTokens($text);
    $second_run = $this->tokenizer->getTokens($text);

    $this->assertNotEmpty($first_run);
    $this->assertSame($first_run, $second_run, 'Tokenizing the same string must produce identical tokens.');
  }

  /**
   * Tests that different strings produce different tokens.
   *
   * @covers ::getTokens
   */
  public function testDifferentInputsProduceDifferentTokens(): void {
    $this->tokenizer->setModel('gpt-4');

    $tokens_a = $this->tokenizer->getTokens('Drupal is a content management system.');
    $tokens_b = $this->tokenizer->getTokens('PHP is a programming language.');

    $this->assertNotSame($tokens_a, $tokens_b);
  }

  /**
   * Tests that countTokens returns a positive integer.
   *
   * @covers ::countTokens
   */
  public function testCountTokensReturnsPositiveInt(): void {
    $this->tokenizer->setModel('gpt-4');

    $count = $this->tokenizer->countTokens('Hello world');
    $this->assertGreaterThan(0, $count);
    $this->assertSame(count($this->tokenizer->getTokens('Hello world')), $count);
  }

  /**
   * Tests that an empty string produces zero tokens.
   *
   * @covers ::countTokens
   */
  public function testEmptyStringProducesZeroTokens(): void {
    $this->tokenizer->setModel('gpt-4');

    $this->assertSame(0, $this->tokenizer->countTokens(''));
    $this->assertSame([], $this->tokenizer->getTokens(''));
  }

  /**
   * Tests that calling getTokens without setting a model throws an exception.
   *
   * @covers ::getTokens
   */
  public function testGetTokensWithoutModelThrows(): void {
    // Create a fresh tokenizer that has no model set. The uninitialized typed
    // property triggers an Error before the explicit exception check.
    $tokenizer = new Tokenizer($this->container->get('ai.provider'));
    $this->expectException(\Error::class);
    $tokenizer->getTokens('test');
  }

  /**
   * Tests that the fallback encoder is used for an unsupported model name.
   *
   * @covers ::setModel
   */
  public function testFallbackEncoderForUnknownModel(): void {
    $this->tokenizer->setModel('some-nonexistent-model-xyz');

    // Should not throw and should tokenize with the cl100k_base fallback.
    $tokens = $this->tokenizer->getTokens('Hello');
    $this->assertNotEmpty($tokens);
  }

  /**
   * Tests that the tokenizer stores vocabulary caches in the temp directory.
   *
   * @covers ::__construct
   */
  public function testVocabCacheStoredInTempDirectory(): void {
    $temp_dir = \Drupal::service('file_system')->getTempDirectory();

    // After construction the encoder provider should have been configured
    // with the temp directory. Trigger a model load so the vocabulary file
    // is fetched/cached.
    $this->tokenizer->setModel('gpt-4');
    $this->tokenizer->getTokens('cache warm-up');

    // Verify that at least one vocab cache file exists inside the temp dir.
    $bin_files = glob($temp_dir . '/*') ?: [];

    // Tiktoken stores vocab files with various extensions; check that
    // something was written to the temp directory by the encoder provider.
    $cache_files = array_filter($bin_files, function (string $path): bool {
      return is_file($path) && filesize($path) > 0;
    });

    $this->assertNotEmpty(
      $cache_files,
      sprintf('Expected vocabulary cache files in the temp directory (%s), but none were found.', $temp_dir)
    );
  }

}
