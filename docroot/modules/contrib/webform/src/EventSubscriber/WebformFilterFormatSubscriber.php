<?php

declare(strict_types=1);

namespace Drupal\webform\EventSubscriber;

use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\webform\Element\WebformHtmlEditor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Alters filter format list builder and removes the hidden Webform filter.
 *
 * @see \Drupal\filter\FilterFormatListBuilder
 */
class WebformFilterFormatSubscriber extends ServiceProviderBase implements EventSubscriberInterface {

  /**
   * Constructs a WebformFilterFormatSubscriber object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Alters filter format list builder and removes the hidden Webform filter.
   *
   * @param \Symfony\Component\HttpKernel\Event\ViewEvent $event
   *   The event to process.
   */
  public function onView(ViewEvent $event): void {
    switch ($this->routeMatch->getRouteName()) {
      case 'filter.admin_overview':
        $result = $event->getControllerResult();
        unset($result['formats'][WebformHtmlEditor::DEFAULT_FILTER_FORMAT]);
        $event->setControllerResult($result);
        return;

      case 'user.admin_permissions':
      case 'entity.user_role.edit_permissions_form':
        // Note: We can't alter permission therefore we need to hide the
        // 'Use the Webform (Default) - DO NOT EDIT text format' permission.
        // @see https://www.drupal.org/project/drupal/issues/763074
        $result = $event->getControllerResult();
        unset($result['permissions']['use text format webform_default']);
        $event->setControllerResult($result);
        return;
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run before main_content_view_subscriber.
    $events[KernelEvents::VIEW][] = ['onView', 100];
    return $events;
  }

}
