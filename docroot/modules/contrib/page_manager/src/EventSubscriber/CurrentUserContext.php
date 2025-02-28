<?php

namespace Drupal\page_manager\EventSubscriber;

use Drupal\Core\Plugin\Context\LazyContextRepository;
use Drupal\page_manager\Event\PageManagerContextEvent;
use Drupal\page_manager\Event\PageManagerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sets the current user as a context.
 */
class CurrentUserContext implements EventSubscriberInterface {

  /**
   * Constructs a new CurrentUserContext.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * Creates LanguageInterfaceContext object.
   *
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The context repository service.
   */
  public function __construct(LazyContextRepository $context_repository) {
    $this->contextRepository = $context_repository;
  }

  /**
   * Adds in the current user as a context.
   *
   * @param \Drupal\page_manager\Event\PageManagerContextEvent $event
   *   The page entity context event.
   */
  public function onPageContext(PageManagerContextEvent $event) {
    $contexts = $this->contextRepository->getRuntimeContexts(['@user.current_user_context:current_user']);
    $context = reset($contexts);
    $event->getPage()
      ->addContext('@user.current_user_context:current_user', $context)
      ->addContext('current_user', $context);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[PageManagerEvents::PAGE_CONTEXT][] = 'onPageContext';
    return $events;
  }

}
