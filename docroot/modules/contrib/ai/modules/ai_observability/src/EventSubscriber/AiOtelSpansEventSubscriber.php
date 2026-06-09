<?php

namespace Drupal\ai_observability\EventSubscriber;

use Drupal\ai\Event\AiProviderRequestBaseEvent;
use Drupal\ai\Event\AiProviderResponseBaseEvent;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai_observability\AiObservabilityUtils;
use Drupal\ai_observability\Form\SettingsForm;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\opentelemetry\OpentelemetryService;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\TracerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for AI observability OpenTelemetry spans export.
 *
 * @package Drupal\ai_observability\EventSubscriber
 */
class AiOtelSpansEventSubscriber implements EventSubscriberInterface {

  /**
   * List of processing OTEL spans.
   *
   * @var array<string, \OpenTelemetry\API\Trace\Span>
   */
  protected array $otelSpans = [];

  /**
   * The OpenTelemetry tracer instance.
   *
   * @var \OpenTelemetry\API\Trace\TracerInterface|null
   */
  protected ?TracerInterface $otelTracer = NULL;

  /**
   * Constructs an AiOtelSpansEventSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Closure<\Psr\Log\LoggerInterface> $loggerClosure
   *   The logger closure.
   * @param \Drupal\opentelemetry\OpentelemetryService $opentelemetry
   *   The OpenTelemetry service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    #[AutowireServiceClosure('logger.channel.ai_observability')]
    protected \Closure $loggerClosure,
    #[Autowire(service: 'opentelemetry')]
    protected OpentelemetryService $opentelemetry,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreGenerateResponseEvent::EVENT_NAME => 'handlePreGenerateResponseEvent',
      PostGenerateResponseEvent::EVENT_NAME => 'handlePostGenerateResponseEvent',
      PostStreamingResponseEvent::EVENT_NAME => 'handlePostGenerateResponseEvent',
    ];
  }

  /**
   * Handles PreGenerateResponseEvent: starts and stores a span.
   *
   * @param \Drupal\ai\Event\PreGenerateResponseEvent $event
   *   The event to export.
   */
  public function handlePreGenerateResponseEvent(PreGenerateResponseEvent $event): void {
    $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);
    if (
      !$config->get(SettingsForm::CONFIG_KEY_OTEL_ENABLED)
      || !$config->get(SettingsForm::CONFIG_KEY_OTEL_SPANS)
      // Exit if no tracer is available.
      || !$this->otelTracer ??= $this->opentelemetry->getTracer()
    ) {
      return;
    }

    $requestId = $event->getRequestThreadId();
    $span = $this->otelTracer->spanBuilder(SettingsForm::OTEL_SPAN_NAME_REQUEST)->startSpan();
    $this->fillRequestSpanAttributes($span, $event);
    $this->otelSpans[$requestId] = $span;
  }

  /**
   * Handles Post*ResponseEvent events: ends span.
   *
   * @param \Drupal\ai\Event\AiProviderRequestBaseEvent $event
   *   The event to export.
   */
  public function handlePostGenerateResponseEvent(AiProviderRequestBaseEvent $event): void {
    $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);
    if (
      !$config->get(SettingsForm::CONFIG_KEY_OTEL_ENABLED)
      || !$config->get(SettingsForm::CONFIG_KEY_OTEL_SPANS)
      // Exit if no tracer is available.
      || !$this->otelTracer ??= $this->opentelemetry->getTracer()
    ) {
      return;
    }

    $requestId = $event->getRequestThreadId();
    if (!isset($this->otelSpans[$requestId])) {
      // No span found for this request ID.
      return;
    }
    $span = $this->otelSpans[$requestId];
    $this->fillResponseSpanAttributes($span, $event);
    $span->end();
  }

  /**
   * Fills span attributes for the request event.
   *
   * @param \OpenTelemetry\API\Trace\Span $span
   *   The span to fill.
   * @param \Drupal\ai\Event\AiProviderRequestBaseEvent $event
   *   The event to get data from.
   */
  protected function fillRequestSpanAttributes(Span $span, AiProviderRequestBaseEvent $event): void {
    // @todo Add settings to choose which fields to submit.
    $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);
    $span->setAttribute('provider', $event->getProviderId());
    $span->setAttribute('operation_type', $event->getOperationType());
    $span->setAttribute('model', $event->getModelId());
    $span->setAttribute('provider_request_id', $event->getRequestThreadId());
    $span->setAttribute('provider_request_parent_id', $event->getRequestParentId());
    $span->setAttribute('configuration', Json::encode($event->getConfiguration()));
    $span->setAttribute('tags', $event->getTags());

    // Optionally submit input to the span if enabled in configuration.
    if ($config->get(SettingsForm::CONFIG_KEY_OTEL_STORE_INPUT)) {
      $payload = $event->getInput();
      // @todo Remove this check when https://www.drupal.org/i/3567673 is fixed.
      if ($payload instanceof InputInterface) {
        $span->setAttribute('input', AiObservabilityUtils::summarizeAiPayloadData($payload->toString()));
      }
      else {
        $span->setAttribute('input', 'Unsupported input type: ' . get_debug_type($payload));
      }
    }
  }

  /**
   * Fills span attributes for the response event.
   *
   * @param \OpenTelemetry\API\Trace\Span $span
   *   The span to fill.
   * @param \Drupal\ai\Event\AiProviderRequestBaseEvent|\Drupal\ai\Event\AiProviderResponseBaseEvent $event
   *   The event to get data from.
   */
  protected function fillResponseSpanAttributes(Span $span, AiProviderResponseBaseEvent $event): void {
    $output = $event->getOutput();
    if ($output !== NULL && method_exists($output, 'getTokenUsage')) {
      $tokenUsage = $output->getTokenUsage()->toArray();
      // Remove NULL values because OpenTelemetry attributes don't support them.
      $tokenUsage = array_filter($tokenUsage);
      $span->setAttribute('token_usage', $tokenUsage);
    }
    $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);

    if ($config->get(SettingsForm::CONFIG_KEY_OTEL_STORE_OUTPUT)) {
      $payload = $event->getOutput();
      $payloadStringified = AiObservabilityUtils::aiOutputToString($payload);
      $span->setAttribute('output', AiObservabilityUtils::summarizeAiPayloadData($payloadStringified));
    }
  }

}
