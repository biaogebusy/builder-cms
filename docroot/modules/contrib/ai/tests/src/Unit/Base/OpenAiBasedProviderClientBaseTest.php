<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Base;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\ai\Base\OpenAiBasedProviderClientBase;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Service\HostnameFilter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

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
    $this->assertInstanceOf(\ReflectionNamedType::class, $parentType);
    $this->assertSame('Exception', $parentType->getName());
  }

  /**
   * Tests that the Fiber branch keeps token usage from the consumed stream.
   *
   * Regression test for issue #3586522: when chat() executes inside a
   * \Fiber — which is the case for every web request since Drupal core 11.x
   * wraps controller execution in a Fiber — the streamed response is
   * consumed and reconstructed, but the token usage collected during
   * consumption was discarded from the returned ChatOutput.
   *
   * @covers ::chat
   */
  public function testFiberBranchKeepsTokenUsage(): void {
    // The streamed iterator falls back to the global container for the event
    // dispatcher (PostStreamingResponseEvent on full consumption) and the
    // hostname filter service (URL-safety buffer flush), and the hostname
    // filter reads the Settings singleton.
    new Settings([]);
    $config = $this->createMock(ImmutableConfig::class);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->willReturn($config);
    $container = new ContainerBuilder();
    $container->set('event_dispatcher', new EventDispatcher());
    $container->set('ai.hostname_filter_service', new HostnameFilter(
      $this->createMock(LoggerChannelFactoryInterface::class),
      $config_factory,
    ));
    \Drupal::setContainer($container);

    $reflection = new \ReflectionClass(FiberTokenUsageStubProvider::class);
    /** @var \Drupal\Tests\ai\Unit\Base\FiberTokenUsageStubProvider $provider */
    $provider = $reflection->newInstanceWithoutConstructor();
    $client = new \ReflectionProperty(OpenAiBasedProviderClientBase::class, 'client');
    $client->setValue($provider, new FiberTokenUsageStubClient());

    $input = new ChatInput([new ChatMessage('user', 'Hello')]);

    $usage = NULL;
    $fiber = new \Fiber(static function () use ($provider, $input, &$usage): void {
      $output = $provider->chat($input, 'stub-model');
      $usage = $output->getTokenUsage();
    });
    $fiber->start();
    while (!$fiber->isTerminated()) {
      $fiber->resume();
    }

    $this->assertNotNull($usage, 'chat() completed inside the Fiber.');
    $this->assertSame(10, $usage->input);
    $this->assertSame(5, $usage->output);
    $this->assertSame(15, $usage->total);
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

/**
 * Fixture: minimal concrete provider for exercising the chat() Fiber branch.
 */
// phpcs:disable Drupal.Classes.ClassFileName.NoMatch
final class FiberTokenUsageStubProvider extends OpenAiBasedProviderClientBase {

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return ['chat'];
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

}

/**
 * Fixture: stand-in for the OpenAI SDK client used by the Fiber branch test.
 */
final class FiberTokenUsageStubClient {

  /**
   * Returns a chat resource whose streamed response yields canned chunks.
   *
   * @return object
   *   The chat resource stub.
   */
  public function chat(): object {
    return new class {

      /**
       * Returns a traversable streamed response.
       *
       * @param array $payload
       *   The request payload (ignored).
       *
       * @return \IteratorAggregate
       *   The canned stream.
       */
      public function createStreamed(array $payload): \IteratorAggregate {
        return new class implements \IteratorAggregate {

          /**
           * {@inheritdoc}
           */
          public function getIterator(): \Generator {
            yield new FiberTokenUsageStubChunk([
              new FiberTokenUsageStubChoice(new FiberTokenUsageStubDelta('assistant', 'Hello'), NULL),
            ]);
            yield new FiberTokenUsageStubChunk([
              new FiberTokenUsageStubChoice(new FiberTokenUsageStubDelta('', ' world.'), 'stop'),
            ]);
            // OpenAI-compatible APIs send the usage data in a trailing chunk
            // with an empty choices list when stream_options.include_usage is
            // set.
            yield new FiberTokenUsageStubChunk([], new FiberTokenUsageStubUsage(10, 5, 15));
          }

        };
      }

    };
  }

}

/**
 * Fixture: a single streamed completion chunk.
 */
final class FiberTokenUsageStubChunk {

  public function __construct(
    public array $choices,
    public ?FiberTokenUsageStubUsage $usage = NULL,
  ) {}

  /**
   * Mirrors the SDK response object API.
   *
   * @return array
   *   The raw chunk data.
   */
  public function toArray(): array {
    return [];
  }

}

/**
 * Fixture: the usage block of a trailing streamed chunk.
 */
final class FiberTokenUsageStubUsage {

  public function __construct(
    public int $promptTokens,
    public int $completionTokens,
    public int $totalTokens,
  ) {}

  /**
   * Mirrors the SDK usage object API.
   *
   * @return array
   *   The raw usage data.
   */
  public function toArray(): array {
    return [
      'prompt_tokens' => $this->promptTokens,
      'completion_tokens' => $this->completionTokens,
      'total_tokens' => $this->totalTokens,
    ];
  }

}

/**
 * Fixture: the delta part of a streamed chunk choice.
 */
final class FiberTokenUsageStubDelta {

  public function __construct(
    public string $role = '',
    public string $content = '',
  ) {}

}

/**
 * Fixture: a single choice of a streamed chunk.
 */
final class FiberTokenUsageStubChoice {

  public function __construct(
    public FiberTokenUsageStubDelta $delta,
    public ?string $finishReason = NULL,
  ) {}

}
// phpcs:enable Drupal.Classes.ClassFileName.NoMatch
