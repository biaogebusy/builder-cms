<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_observability\Unit;

use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\Event\ProviderDisabledEvent;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScope;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\RandomIdGenerator;
use OpenTelemetry\SDK\Trace\SamplerFactory;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanLimitsBuilder;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\Tracer;
use OpenTelemetry\SDK\Trace\TracerSharedState;

/**
 * Helper class for AI observability unit tests.
 *
 * @group ai_observability
 */
class AiObservabilityTestHelper {

  /**
   * Creates stub instances of AI events based on the provided class.
   *
   * @param string $class
   *   The AI event class to create a stub for.
   *
   * @return object
   *   A stub instance of the requested event class.
   *
   * @throws \InvalidArgumentException
   *   If the event class is not supported.
   */
  public static function getAiEventStub(string $class) {
    return match ($class) {
      PreGenerateResponseEvent::class => new PreGenerateResponseEvent(
        requestThreadId: 'test-thread-id',
        providerId: 'test-provider',
        operationType: 'test-operation',
        configuration: [],
        input: self::getInputStub(),
        modelId: 'test-model',
        tags: [],
        debugData: [],
        metadata: [],
      ),
      PostGenerateResponseEvent::class => new PostGenerateResponseEvent(
        requestThreadId: 'test-thread-id',
        providerId: 'test-provider',
        operationType: 'test-operation',
        configuration: [],
        input: self::getInputStub(),
        modelId: 'test-model',
        output: self::getOutputStub(),
        tags: [],
        debugData: [],
        metadata: [],
      ),
      PostStreamingResponseEvent::class => new PostStreamingResponseEvent(
        requestThreadId: 'test-thread-id',
        providerId: 'test-provider',
        operationType: 'test-operation',
        configuration: [],
        input: self::getInputStub(),
        modelId: 'test-model',
        output: self::getOutputStub(),
        tags: [],
        debugData: [],
        metadata: [],
      ),
      ProviderDisabledEvent::class => new ProviderDisabledEvent('test-provider'),
      default => throw new \InvalidArgumentException("Unsupported event class: $class"),
    };
  }

  /**
   * Creates a stub ChatInput for testing.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatInput
   *   A chat input stub instance.
   */
  public static function getInputStub(): ChatInput {
    return new ChatInput([
      new ChatMessage('system', 'bar'),
      new ChatMessage('user', 'foo'),
      new ChatMessage('agent', 'bar'),
      new ChatMessage('user', 'baz'),
    ]);
  }

  /**
   * Creates a stub ChatOutput for testing.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   A chat output stub instance.
   */
  public static function getOutputStub(): ChatOutput {
    return new ChatOutput(
      normalized: new ChatMessage('agent', 'response'),
      rawOutput: ['foo'],
      metadata: [],
      tokenUsage: new TokenUsageDto(
        input: 10,
        output: 20,
        total: 30,
      ),
    );
  }

  /**
   * Initializes OpenTelemetry tracer with in-memory span storage.
   *
   * @param \ArrayObject $spanStorage
   *   An array object to store exported spans.
   *
   * @return \OpenTelemetry\API\Trace\TracerInterface
   *   A tracer instance configured with in-memory span export.
   */
  public static function initOtelSpanStorageStub(\ArrayObject $spanStorage): TracerInterface {
    $exporter = new InMemoryExporter($spanStorage);
    $spanProcessor = new SimpleSpanProcessor($exporter);

    $tracerSharedState = new TracerSharedState(
      new RandomIdGenerator(),
      ResourceInfoFactory::defaultResource(),
      (new SpanLimitsBuilder())->build(),
      (new SamplerFactory())->create(),
      [$spanProcessor],
    );

    $attributes = new Attributes([], 0);
    $instrumentationScope = new InstrumentationScope(
      'ai_observability_test',
      '1.0.0',
      '',
      $attributes,
    );

    return new Tracer($tracerSharedState, $instrumentationScope);
  }

}
