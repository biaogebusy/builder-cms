<?php

namespace Drupal\require_login\EventSubscriber;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\require_login\LoginRequirementsManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Provides login event subscriber.
 */
class LoginEventSubscriber implements EventSubscriberInterface {

  /**
   * The login requirements manager.
   *
   * @var \Drupal\require_login\LoginRequirementsManagerInterface
   */
  protected LoginRequirementsManagerInterface $loginRequirementsManager;

  /**
   * The exception boolean.
   *
   * @var bool
   */
  protected bool $exception = FALSE;

  /**
   * LoginEventSubscriber constructor.
   *
   * @param \Drupal\require_login\LoginRequirementsManagerInterface $loginRequirementsManager
   *   The login requirements manager.
   */
  public function __construct(LoginRequirementsManagerInterface $loginRequirementsManager) {
    $this->loginRequirementsManager = $loginRequirementsManager;
  }

  /**
   * Perform login evaluation and redirect on requests.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event.
   */
  public function onExceptionRedirect(ExceptionEvent $event): void {
    $exception = $event->getThrowable();
    if ($exception instanceof NotFoundHttpException || $exception instanceof AccessDeniedHttpException) {
      if ($url = $this->loginRequirementsManager->evaluate($exception)) {
        $event->setResponse(new TrustedRedirectResponse($url->toString()));
      }
      $this->exception = TRUE;
    }
  }

  /**
   * Perform login evaluation and redirect on exceptions.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequestRedirect(RequestEvent $event): void {
    if ($event->getRequestType() !== 1) {
      // Prevent evaluations on sub requests.
      return;
    }
    if (!$this->exception && ($url = $this->loginRequirementsManager->evaluate())) {
      $event->setResponse(new TrustedRedirectResponse($url->toString()));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::EXCEPTION][] = ['onExceptionRedirect'];
    $events[KernelEvents::REQUEST][] = ['onRequestRedirect', 31];
    return $events;
  }

}
