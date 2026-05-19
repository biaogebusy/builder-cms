<?php

namespace Drupal\simple_oauth\EventSubscriber;

use Drupal\Core\EventSubscriber\ExceptionLoggingSubscriber as CoreExceptionLoggingSubscriber;
use Drupal\simple_oauth\Exception\OAuthUnauthorizedHttpException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Decorated logging subscriber to reduce log spam on normal OAuth2 responses.
 */
class ExceptionLoggingSubscriber implements EventSubscriberInterface {

  /**
   * Wrapped service.
   */
  protected EventSubscriberInterface $inner;

  /**
   * Logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * Constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventSubscriberInterface $inner
   *   Wrapped subscriber service.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   */
  public function __construct(
    EventSubscriberInterface $inner,
    LoggerInterface $logger,
  ) {
    $this->inner = $inner;
    $this->logger = $logger;
  }

  /**
   * Log exceptions.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function onException(ExceptionEvent $event): void {
    if ($event->getThrowable() instanceof OAuthUnauthorizedHttpException) {
      $throwable = $event->getThrowable();
      $this->logger->notice($throwable->getMessage() .
        ' Hint: ' . $throwable->getPrevious()->getHint() . '.');
      return;
    }
    $this->inner->onException($event);
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents(): array {
    return CoreExceptionLoggingSubscriber::getSubscribedEvents();
  }

}
