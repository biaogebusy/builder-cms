<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Base;

use Drupal\ai\Base\OpenAiBasedProviderClientBase;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiResponseErrorException;
use PHPUnit\Framework\TestCase;

/**
 * Tests error handling in OpenAiBasedProviderClientBase.
 *
 * @group ai
 * @coversDefaultClass \Drupal\ai\Base\OpenAiBasedProviderClientBase
 */
class OpenAiBasedProviderClientBaseTest extends TestCase {

  /**
   * The provider instance under test.
   *
   * @var \Drupal\ai\Base\OpenAiBasedProviderClientBase
   */
  protected OpenAiBasedProviderClientBase $provider;

  /**
   * Reflection method for handleApiException.
   *
   * @var \ReflectionMethod
   */
  protected \ReflectionMethod $handleApiException;

  /**
   * Reflection method for handleApiThrowable.
   *
   * @var \ReflectionMethod
   */
  protected \ReflectionMethod $handleApiThrowable;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->provider = $this->createMock(OpenAiBasedProviderClientBase::class);

    $this->handleApiException = new \ReflectionMethod(
      $this->provider,
      'handleApiException',
    );
    $this->handleApiThrowable = new \ReflectionMethod(
      $this->provider,
      'handleApiThrowable',
    );
  }

  /**
   * Tests that \TypeError is wrapped in AiResponseErrorException.
   *
   * Real providers like the OpenAI PHP client can throw \TypeError when the
   * API returns an unexpected response format. handleApiThrowable() must
   * convert these to AiResponseErrorException so that all consumers can
   * catch them.
   *
   * @covers ::handleApiThrowable
   */
  public function testTypeErrorIsWrappedInAiResponseErrorException(): void {
    $original = new \TypeError('CreateResponse::from(): Argument #1 ($attributes) must be of type array, string given');

    $this->expectException(AiResponseErrorException::class);
    $this->expectExceptionMessage('CreateResponse::from(): Argument #1 ($attributes) must be of type array, string given');

    $this->handleApiThrowable->invoke($this->provider, $original);
  }

  /**
   * Tests that the original \Error is preserved as the previous exception.
   *
   * @covers ::handleApiThrowable
   */
  public function testWrappedErrorPreservesOriginalAsPrevious(): void {
    $original = new \TypeError('type mismatch');

    try {
      $this->handleApiThrowable->invoke($this->provider, $original);
      $this->fail('Expected AiResponseErrorException was not thrown.');
    }
    catch (AiResponseErrorException $e) {
      $this->assertSame($original, $e->getPrevious());
    }
  }

  /**
   * Tests that generic \Error subclasses are also wrapped.
   *
   * @covers ::handleApiThrowable
   */
  public function testGenericErrorIsWrapped(): void {
    $original = new \ValueError('Invalid value');

    $this->expectException(AiResponseErrorException::class);
    $this->expectExceptionMessage('Invalid value');

    $this->handleApiThrowable->invoke($this->provider, $original);
  }

  /**
   * Tests that handleApiThrowable delegates exceptions to handleApiException.
   *
   * @covers ::handleApiThrowable
   */
  public function testThrowableDelegatesExceptionToHandleApiException(): void {
    $original = new \RuntimeException('Too Many Requests');

    $this->expectException(AiRateLimitException::class);

    $this->handleApiThrowable->invoke($this->provider, $original);
  }

  /**
   * Tests that regular exceptions are rethrown as-is.
   *
   * @covers ::handleApiException
   */
  public function testRegularExceptionIsRethrown(): void {
    $original = new \RuntimeException('Something went wrong');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Something went wrong');

    $this->handleApiException->invoke($this->provider, $original);
  }

  /**
   * Tests that rate limit messages throw AiRateLimitException.
   *
   * @covers ::handleApiException
   */
  public function testRateLimitExceptionForTooManyRequests(): void {
    $original = new \RuntimeException('Too Many Requests');

    $this->expectException(AiRateLimitException::class);

    $this->handleApiException->invoke($this->provider, $original);
  }

  /**
   * Tests that rate limit messages throw AiRateLimitException.
   *
   * @covers ::handleApiException
   */
  public function testRateLimitExceptionForRequestTooLarge(): void {
    $original = new \RuntimeException('Request too large for model');

    $this->expectException(AiRateLimitException::class);

    $this->handleApiException->invoke($this->provider, $original);
  }

  /**
   * Tests that quota messages throw AiQuotaException.
   *
   * @covers ::handleApiException
   */
  public function testQuotaException(): void {
    $original = new \RuntimeException('You exceeded your current quota');

    $this->expectException(AiQuotaException::class);

    $this->handleApiException->invoke($this->provider, $original);
  }

  /**
   * Guards against regression of issue #3573429.
   *
   * A subclass that overrides handleApiException() with the original
   * \Exception parameter type must remain loadable and invokable. If the
   * parent signature is ever widened again (e.g. to \Throwable), PHP would
   * raise a fatal "incompatible signature" error at class declaration.
   *
   * @covers ::handleApiException
   */
  public function testSubclassWithExceptionParamTypeIsCompatible(): void {
    // Referencing the class triggers PHP's signature compatibility check.
    $this->assertTrue(class_exists(LegacyExceptionSignatureProvider::class));

    $parent = new \ReflectionMethod(OpenAiBasedProviderClientBase::class, 'handleApiException');
    $parentType = $parent->getParameters()[0]->getType();
    $this->assertNotNull($parentType);
    $this->assertSame('Exception', ltrim((string) $parentType, '?\\'));
  }

}

/**
 * Fixture: subclass overriding handleApiException() with \Exception param.
 *
 * This mirrors the signature used by real contributed provider modules
 * (e.g. the Anthropic provider) prior to the issue #3573429 regression.
 * Having this class in the same file causes the signature compatibility
 * check to run whenever this test file is loaded.
 */
// phpcs:disable Drupal.Classes.ClassFileName.NoMatch
abstract class LegacyExceptionSignatureProvider extends OpenAiBasedProviderClientBase {

  /**
   * {@inheritdoc}
   *
   * Intentionally re-declares the signature with the legacy \Exception
   * parameter type. This method looks redundant but is load-bearing: its
   * presence triggers PHP's signature compatibility check against the
   * parent at class-declaration time.
   */
  // phpcs:disable Squiz.Scope.MethodScope.Missing, Generic.CodeAnalysis.UselessOverridingMethod.Found
  protected function handleApiException(\Exception $e): void {
    parent::handleApiException($e);
  }

  // phpcs:enable Squiz.Scope.MethodScope.Missing, Generic.CodeAnalysis.UselessOverridingMethod.Found

}
// phpcs:enable Drupal.Classes.ClassFileName.NoMatch
