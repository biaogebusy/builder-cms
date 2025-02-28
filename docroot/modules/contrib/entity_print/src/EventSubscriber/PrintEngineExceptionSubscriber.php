<?php

namespace Drupal\entity_print\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\entity_print\PrintEngineException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

/**
 * Exception event subscriber.
 */
class PrintEngineExceptionSubscriber implements EventSubscriberInterface {

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * PrintEngineExceptionSubscriber constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Route match service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(RouteMatchInterface $routeMatch, EntityTypeManagerInterface $entityTypeManager, MessengerInterface $messenger) {
    $this->routeMatch = $routeMatch;
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
  }

  /**
   * Handles print exceptions.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent|\Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event.
   *
   * @todo remove reference to
   *   Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent when D9
   *   is no longer supported and add back a type-hint for the ExceptionEvent.
   */
  public function handleException($event) {
    assert($event instanceof \Symfony\Component\HttpKernel\Event\ExceptionEvent || $event instanceof ExceptionEvent);
    $exception = $event->getThrowable();
    if ($exception instanceof PrintEngineException) {
      $this->messenger->addError(new FormattableMarkup($exception->getPrettyMessage(), []));

      if ($entity = $this->getEntity()) {
        $event->setResponse(new RedirectResponse($entity->toUrl()->toString()));
      }
      elseif ($view = $this->getView()) {
        $display_id = $this->routeMatch->getParameter('display_id');
        /** @var \Drupal\views\ViewExecutable $executable */
        $executable = $view->getExecutable();
        $executable->setDisplay($display_id);
        $url = $executable->hasUrl() ? $executable->getUrl()->toString() : Url::fromRoute('<front>');
        $event->setResponse(new RedirectResponse($url));
      }
    }
  }

  /**
   * Gets a generic entity from the route data if it exists.
   *
   * @return bool|\Drupal\Core\Entity\EntityInterface
   *   The entity or FALSE if it does not exist.
   */
  protected function getEntity() {
    $entity_type = $this->routeMatch->getParameter('entity_type');
    $entity_id = $this->routeMatch->getParameter('entity_id');
    return $entity_type && $entity_id ? $this->entityTypeManager->getStorage($entity_type)->load($entity_id) : FALSE;
  }

  /**
   * Gets the view from the route data if it exists.
   *
   * @return bool|\Drupal\views\ViewEntityInterface
   *   The View or FALSE if it not a view route.
   */
  protected function getView() {
    $view_name = $this->routeMatch->getParameter('view_name');
    return $view_name ? $this->entityTypeManager->getStorage('view')->load($view_name) : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::EXCEPTION => 'handleException',
    ];
  }

}
