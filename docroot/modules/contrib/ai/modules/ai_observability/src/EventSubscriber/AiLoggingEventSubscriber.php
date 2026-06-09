<?php

namespace Drupal\ai_observability\EventSubscriber;

use Drupal\ai\Event\AiProviderRequestBaseEvent;
use Drupal\ai\Event\AiProviderResponseBaseEvent;
use Drupal\ai\Event\ProviderDisabledEvent;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai_observability\AiLogEventType;
use Drupal\ai_observability\AiObservabilityUtils;
use Drupal\ai_observability\Form\SettingsForm;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for AI observability logging.
 *
 * @package Drupal\ai_observability\EventSubscriber
 */
class AiLoggingEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs an AiLoggingEventSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Closure<\Psr\Log\LoggerInterface> $loggerClosure
   *   The logger closure.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    #[AutowireServiceClosure('logger.channel.ai_observability')]
    protected \Closure $loggerClosure,
  ) {
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The subscribed events.
   */
  public static function getSubscribedEvents(): array {
    // We can't read the configuration in this static function, because the
    // Drupal Container is not initialized yet. So, have to subscribe to all
    // supported events.
    $events = [];
    foreach (AiLogEventType::supportedEventClasses() as $eventClass) {
      $events[$eventClass::EVENT_NAME] = 'logEvent';
    }
    return $events;
  }

  /**
   * Logs AI provider events.
   *
   * @param \Drupal\ai\Event\AiProviderRequestBaseEvent|\Drupal\ai\Event\ProviderDisabledEvent $event
   *   The event to log.
   */
  public function logEvent(AiProviderRequestBaseEvent|ProviderDisabledEvent $event) {
    $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);
    if (!$config->get(SettingsForm::CONFIG_KEY_LOGGING_ENABLED)) {
      return;
    }

    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = ($this->loggerClosure)();
    if ($event instanceof ProviderDisabledEvent) {
      $logger->info('Provider @provider disabled.', [
        '@provider' => $event->getProviderId(),
      ]);
      return;
    }
    $logTags = $config->get(SettingsForm::CONFIG_KEY_LOG_TAGS);
    $tags = $event->getTags();
    // Skip logging if log tags are set and none of them match event tags.
    if (
      !empty($logTags)
      && !array_intersect($logTags, $tags)
    ) {
      return;
    }

    $context = [
      'metadata' => [
        'event_name' => $event::EVENT_NAME,
      ],
    ];

    if (!empty($tags)) {
      $context['metadata']['tags'] = $tags;
    }

    if ($event instanceof AiProviderRequestBaseEvent) {
      $context['metadata']['provider'] = $event->getProviderId();
      $context['metadata']['operation_type'] = $event->getOperationType();
      $context['metadata']['model'] = $event->getModelId();
      $context['metadata']['provider_request_id'] = $event->getRequestThreadId();
      $context['metadata']['provider_request_parent_id'] = $event->getRequestParentId();
      $context['metadata']['configuration'] = $event->getConfiguration();
    }

    if ($event instanceof AiProviderResponseBaseEvent) {
      $output = $event->getOutput();
      // Not every AI output type provides the token usage info yet, therefore
      // we need to check if the method exists before calling it.
      // @todo Remove the method_exists check when all output types implement
      // the getTokenUsage method and it is added to the OutputInterface.
      if (method_exists($output, 'getTokenUsage')) {
        $context['metadata']['token_usage'] = $event->getOutput()->getTokenUsage()->toArray();
      }
    }

    if (
      $config->get(SettingsForm::CONFIG_KEY_LOG_INPUT)
      && $event instanceof AiProviderRequestBaseEvent
    ) {
      $payload = $event->getInput();
      if ($payload instanceof InputInterface) {
        $context['metadata']['input'] = AiObservabilityUtils::summarizeAiPayloadData($payload->toString());
        $guardrails_log = [];
        foreach ($payload->getAllGuardrailResults() as $mode => $results) {
          foreach ($results as $result) {
            if (!$result instanceof GuardrailResultInterface) {
              continue;
            }
            $guardrails_log[] = [
              'mode' => $mode,
              'guardrail' => $result->getGuardrailLabel(),
              'type' => get_class($result),
              'context' => $result->getContext(),
              'message' => $result->getMessage(),
            ];
          }
        }
        $context['metadata']['guardrails'] = $guardrails_log;
      }

    }
    if (
      $config->get(SettingsForm::CONFIG_KEY_LOG_OUTPUT)
      && $event instanceof AiProviderResponseBaseEvent
    ) {
      $payload = $event->getOutput();
      $payloadStringified = AiObservabilityUtils::aiOutputToString($payload);
      $context['metadata']['output'] = AiObservabilityUtils::summarizeAiPayloadData($payloadStringified);
    }

    $message = $this->prepareLogMessage($event, $context);
    $logger->info($message, $context);
  }

  /**
   * Composes a user-friendly log message with placeholders.
   *
   * @param \Drupal\ai\Event\AiProviderRequestBaseEvent $event
   *   The event to log.
   * @param mixed $context
   *   The context for the log message. New placeholders will be added there
   *   if the fallback mode is enabled.
   *
   * @return string
   *   The log message string with placeholders
   */
  protected function prepareLogMessage(AiProviderRequestBaseEvent $event, &$context): string {
    $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);

    if ($event instanceof AiProviderResponseBaseEvent) {
      $messagePrefix = 'Response from provider {metadata.provider}';
    }
    elseif ($event instanceof AiProviderRequestBaseEvent) {
      $messagePrefix = 'Call provider {metadata.provider}';
    }
    else {
      $messagePrefix = 'AI event {metadata.eventName} with provider {metadata.provider}';
    }

    $messageParts = [
      'model' => '{metadata.model}',
      'operation type' => '{metadata.operation_type}',
    ];
    if ($context['metadata']['token_usage']['total'] ?? NULL) {
      $messageParts['token usage'] = '{metadata.token_usage.total}';
    }

    foreach ($messageParts as $key => $value) {
      $messageItems[] = "$key: $value";
    }

    $message = $messagePrefix . ': ' . implode(', ', $messageItems) . '.';

    if ($config->get(SettingsForm::CONFIG_KEY_FALLBACK_LOG_MESSAGE_MODE)) {
      $messagePlaceholders = [];
      preg_match_all('/\{([^\}]+)\}/', $message, $matches);
      if (!empty($matches[1])) {
        foreach ($matches[1] as $placeholder) {
          $messagePlaceholders[] = $placeholder;
        }
      }
      foreach ($messagePlaceholders as $placeholder) {
        $path = explode('.', $placeholder);
        $value = (string) NestedArray::getValue($context, $path);
        $fallbackPlaceholder = '@' . implode('_', $path);
        $placeholderFull = '{' . $placeholder . '}';
        $replacements[$placeholderFull] = $fallbackPlaceholder;
        $context[$fallbackPlaceholder] = $value;
      }
      if (isset($replacements)) {
        $message = strtr($message, $replacements);
      }
    }

    return $message;
  }

}
