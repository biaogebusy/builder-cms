<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_observability\Unit;

use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai_observability\AiObservabilityUtils;
use Drupal\ai_observability\EventSubscriber\AiOtelSpansEventSubscriber;
use Drupal\ai_observability\Form\SettingsForm;
use Drupal\opentelemetry\OpentelemetryService;
use Drupal\test_helpers\TestHelpers;
use Drupal\Tests\UnitTestCase;

/**
 * Tests AI OpenTelemetry spans event subscriber.
 *
 * @group ai_observability
 */
class AiOtelSpansEventSubscriberTest extends UnitTestCase {

  /**
   * Storage for OpenTelemetry spans created during tests.
   *
   * @var \ArrayObject
   */
  protected \ArrayObject $spanStorage;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->spanStorage = new \ArrayObject();
  }

  /**
   * Tests that no spans are created when OTEL is disabled.
   */
  public function testWithOtelDisabled() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => FALSE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    $event = AiObservabilityTestHelper::getAiEventStub(PreGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $event);

    $this->assertCount(0, $this->spanStorage);
  }

  /**
   * Tests that OpenTelemetry spans are created when OTEL is enabled.
   */
  public function testWithOtelSpansEnabled() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_SPANS => TRUE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    $event = AiObservabilityTestHelper::getAiEventStub(PreGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $event);

    $event = AiObservabilityTestHelper::getAiEventStub(PostGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $event);

    $this->assertCount(1, $this->spanStorage);
    $span = $this->spanStorage[0];
    $this->assertEquals('AI provider request', $span->getName());
    $this->assertEquals('test-provider', $span->getAttributes()->get('provider'));
    $this->assertEquals('test-operation', $span->getAttributes()->get('operation_type'));
    $this->assertEquals('test-model', $span->getAttributes()->get('model'));
  }

  /**
   * Tests that spans are properly completed with streaming events.
   */
  public function testSpanWithStreamingEvent() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_SPANS => TRUE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    $event = AiObservabilityTestHelper::getAiEventStub(PreGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $event);

    $event = AiObservabilityTestHelper::getAiEventStub(PostStreamingResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostStreamingResponseEvent::EVENT_NAME, $event);

    $this->assertCount(1, $this->spanStorage);
    $span = $this->spanStorage[0];
    $this->assertEquals('AI provider request', $span->getName());
  }

  /**
   * Tests that spans include input/output when configured.
   */
  public function testSpanWithInputOutput() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_SPANS => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_STORE_INPUT => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_STORE_OUTPUT => TRUE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    $event = AiObservabilityTestHelper::getAiEventStub(PreGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $event);

    $event = AiObservabilityTestHelper::getAiEventStub(PostGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $event);

    $this->assertCount(1, $this->spanStorage);
    $span = $this->spanStorage[0];
    $this->assertEquals($span->getAttributes()->get('input'), AiObservabilityTestHelper::getInputStub()->toString());
    $outputExpected = AiObservabilityTestHelper::getOutputStub();
    $outputExpectedString = AiObservabilityUtils::aiOutputToString($outputExpected);
    $this->assertEquals($span->getAttributes()->get('output'), $outputExpectedString);
  }

  /**
   * Initializes the AI OTEL spans event subscriber service for testing.
   *
   * @return \Drupal\ai_observability\EventSubscriber\AiOtelSpansEventSubscriber
   *   The initialized AI OTEL spans event subscriber service.
   */
  private function initAiOtelSpansEventSubscriberService() {
    TestHelpers::service('logger.factory');
    return new AiOtelSpansEventSubscriber(
      TestHelpers::service('config.factory'),
      fn () => TestHelpers::service('logger.factory')->get('ai_observability'),
      TestHelpers::service('opentelemetry'),
    );
  }

}
