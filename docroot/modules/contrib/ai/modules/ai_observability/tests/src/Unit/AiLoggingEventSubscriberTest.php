<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_observability\Unit;

use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\Event\ProviderDisabledEvent;
use Drupal\ai_observability\AiObservabilityUtils;
use Drupal\ai_observability\EventSubscriber\AiLoggingEventSubscriber;
use Drupal\ai_observability\Form\SettingsForm;
use Drupal\test_helpers\TestHelpers;
use Drupal\Tests\UnitTestCase;

/**
 * Tests AI logging event subscriber.
 *
 * @group ai_observability
 */
class AiLoggingEventSubscriberTest extends UnitTestCase {

  /**
   * Tests that no events are logged when logging is disabled.
   */
  public function testWithLoggingDisabled() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_LOGGING_ENABLED => FALSE,
    ]);

    $event = AiObservabilityTestHelper::getAiEventStub(PreGenerateResponseEvent::class);
    $service = $this->initAiLoggingEventSubscriberService();
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $event);

    $logs = TestHelpers::service('logger.factory')->stubGetLogs();
    $this->assertCount(0, $logs);
  }

  /**
   * Tests that AI events are properly logged when logging is enabled.
   */
  public function testWithLoggingEnabled() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_LOGGING_ENABLED => TRUE,
    ]);
    $service = $this->initAiLoggingEventSubscriberService();

    $event = AiObservabilityTestHelper::getAiEventStub(PreGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $event);

    $event = AiObservabilityTestHelper::getAiEventStub(PostGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $event);

    $event = AiObservabilityTestHelper::getAiEventStub(PostStreamingResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostStreamingResponseEvent::EVENT_NAME, $event);

    $event = AiObservabilityTestHelper::getAiEventStub(ProviderDisabledEvent::class);
    TestHelpers::callEventSubscriber($service, ProviderDisabledEvent::EVENT_NAME, $event);

    $logs = TestHelpers::service('logger.factory')->stubGetLogs();
    $this->assertCount(4, $logs);

    TestHelpers::isNestedArraySubsetOf($logs[0], [
      'type' => 'ai_observability',
      'message' => 'Call provider {metadata.provider}: model: {metadata.model}, operation type: {metadata.operation_type}.',
      'severity' => 6,
      '_context' => [
        'metadata' => [
          'event_name' => 'ai.pre_generate_response',
          'provider' => 'test-provider',
          'provider_request_id' => 'test-thread-id',
          'operation_type' => 'test-operation',
          'model' => 'test-model',
        ],
      ],
    ]);

    TestHelpers::isNestedArraySubsetOf($logs[1], [
      'type' => 'ai_observability',
      'message' => 'Response from provider {metadata.provider}: model: {metadata.model}, operation type: {metadata.operation_type}.',
      'severity' => 6,
      '_context' => [
        'metadata' => [
          'event_name' => 'ai.post_generate_response',
          'provider' => 'test-provider',
          'provider_request_id' => 'test-thread-id',
          'operation_type' => 'test-operation',
          'model' => 'test-model',
        ],
      ],
    ]);

    TestHelpers::isNestedArraySubsetOf($logs[3], [
      'type' => 'ai_observability',
      'message' => 'Provider @provider disabled.',
      'severity' => 6,
      '_context' => [
        '@provider' => 'test-provider',
      ],
    ]);
  }

  /**
   * Tests that logging respects input/output configuration.
   */
  public function testLoggingWithInputOutput() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_LOGGING_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_LOG_INPUT => TRUE,
      SettingsForm::CONFIG_KEY_LOG_OUTPUT => TRUE,
    ]);
    $service = $this->initAiLoggingEventSubscriberService();

    $event = AiObservabilityTestHelper::getAiEventStub(PostGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $event);

    $logs = TestHelpers::service('logger.factory')->stubGetLogs();
    $this->assertCount(1, $logs);
    $this->assertEquals($logs[0]['_context']['metadata']['input'], AiObservabilityTestHelper::getInputStub()->toString());
    $outputExpected = AiObservabilityTestHelper::getOutputStub();
    $outputExpectedString = AiObservabilityUtils::aiOutputToString($outputExpected);
    $this->assertEquals($logs[0]['_context']['metadata']['output'], $outputExpectedString);
    $this->assertArrayHasKey('output', $logs[0]['_context']['metadata']);
  }

  /**
   * Initializes the AI logging event subscriber service for testing.
   *
   * @return \Drupal\ai_observability\EventSubscriber\AiLoggingEventSubscriber
   *   The initialized AI logging event subscriber service.
   */
  private function initAiLoggingEventSubscriberService() {
    return TestHelpers::service(
      serviceName: AiLoggingEventSubscriber::class,
      initService: TRUE,
      customArguments: [
        TestHelpers::service('config.factory'),
        fn () => TestHelpers::service('logger.factory')->get('ai_observability'),
      ]);
  }

}
