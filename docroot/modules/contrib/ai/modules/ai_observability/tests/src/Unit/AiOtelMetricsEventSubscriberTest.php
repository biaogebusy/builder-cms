<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_observability\Unit;

use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai_observability\EventSubscriber\AiOtelMetricsEventSubscriber;
use Drupal\ai_observability\Form\SettingsForm;
use Drupal\opentelemetry_metrics\OpentelemetryMetrics;
use Drupal\test_helpers\TestHelpers;
use Drupal\Tests\UnitTestCase;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\CounterInterface;

/**
 * Tests AI OpenTelemetry metrics event subscriber.
 *
 * @group ai_observability
 */
class AiOtelMetricsEventSubscriberTest extends UnitTestCase {

  /**
   * Tests that no metrics are exported when OTEL is disabled.
   */
  public function testWithOtelDisabled() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => FALSE,
    ]);

    $otelMetrics = $this->createMock(OpentelemetryMetrics::class);
    $otelMetrics->expects($this->never())->method('getMeter');

    $service = $this->initAiOtelMetricsEventSubscriberService($otelMetrics);

    $event = AiObservabilityTestHelper::getAiEventStub(PostGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $event);
  }

  /**
   * Tests that metrics are exported when OTEL metrics is enabled.
   */
  public function testWithOtelMetricsEnabled() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_METRICS => TRUE,
    ]);

    $counter = $this->createMock(CounterInterface::class);
    $counter->expects($this->exactly(6))
      ->method('add')
      ->with(
        $this->anything(),
        $this->callback(function ($attributes) {
          return isset($attributes['provider'])
            && isset($attributes['operation_type'])
            && isset($attributes['model']);
        })
      );

    $meter = $this->createMock(MeterInterface::class);
    $meter->expects($this->exactly(6))
      ->method('createCounter')
      ->willReturn($counter);

    $otelMetrics = $this->createMock(OpentelemetryMetrics::class);
    $otelMetrics->expects($this->atLeastOnce())
      ->method('getMeter')
      ->with(SettingsForm::OTEL_METER_NAME_TOKEN_USAGE)
      ->willReturn($meter);

    $service = $this->initAiOtelMetricsEventSubscriberService($otelMetrics);

    $event = AiObservabilityTestHelper::getAiEventStub(PostGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $event);

    $event = AiObservabilityTestHelper::getAiEventStub(PostStreamingResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $event);
  }

  /**
   * Initializes the AI OTEL metrics event subscriber service for testing.
   *
   * @param \Drupal\opentelemetry_metrics\OpentelemetryMetrics $otelMetrics
   *   The OpenTelemetry metrics service mock.
   *
   * @return \Drupal\ai_observability\EventSubscriber\AiOtelMetricsEventSubscriber
   *   The initialized AI OTEL metrics event subscriber service.
   */
  private function initAiOtelMetricsEventSubscriberService($otelMetrics) {
    return new AiOtelMetricsEventSubscriber(
      TestHelpers::service('config.factory'),
      $otelMetrics,
      TestHelpers::service('current_user'),
    );
  }

}
