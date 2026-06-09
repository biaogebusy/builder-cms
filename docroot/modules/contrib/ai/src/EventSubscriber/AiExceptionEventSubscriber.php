<?php

namespace Drupal\ai\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ai\Event\AiExceptionEvent;
use Drupal\ai\Exception\AiMissingFeatureException;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiRequestErrorException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\Exception\AiUnsafePromptException;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Logs AI exceptions and allows for message customization.
 */
final class AiExceptionEventSubscriber implements EventSubscriberInterface {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs the event subscriber.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Reacts to AI exceptions.
   */
  public function onAiException(AiExceptionEvent $event): void {
    $exception = $event->exception;

    // Use instanceof checks instead of get_class() so that interface types
    // (e.g. ClientExceptionInterface) and subclasses match correctly.
    if ($exception instanceof ClientExceptionInterface) {
      $message = 'Error invoking client: ' . $exception->getMessage();
    }
    elseif ($exception instanceof AiResponseErrorException) {
      $message = 'Error invoking model response: ' . $exception->getMessage();
    }
    elseif ($exception instanceof AiMissingFeatureException) {
      $message = 'The provider was missing a requested feature: ' . $exception->getMessage();
    }
    elseif ($exception instanceof AiQuotaException) {
      $message = 'The provider claims missing quota: ' . $exception->getMessage();
    }
    elseif ($exception instanceof AiRateLimitException) {
      $message = 'The provider claims rate limit: ' . $exception->getMessage();
    }
    elseif ($exception instanceof AiUnsafePromptException) {
      $message = 'The Prompt is unsafe: ' . $exception->getMessage();
    }
    elseif ($exception instanceof AiRequestErrorException) {
      $message = 'Error invoking model response: ' . $exception->getMessage();
    }
    else {
      $message = 'Error invoking model response: ' . $exception->getMessage();
    }
    // Log the error.
    $this->loggerFactory->get('ai')->error($message);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      AiExceptionEvent::class => 'onAiException',
    ];
  }

}
