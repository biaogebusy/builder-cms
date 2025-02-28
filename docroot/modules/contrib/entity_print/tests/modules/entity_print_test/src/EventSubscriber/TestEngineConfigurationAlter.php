<?php

namespace Drupal\entity_print_test\EventSubscriber;

use Drupal\entity_print\Event\PrintCssAlterEvent;
use Drupal\entity_print\Event\PrintEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * The TestEngineConfigurationAlter class.
 */
class TestEngineConfigurationAlter implements EventSubscriberInterface {

  /**
   * Alter the configuration for our testpdf engine.
   *
   * @param \Symfony\Component\EventDispatcher\GenericEvent $event
   *   The event object.
   */
  public function alterConfiguration(GenericEvent $event) {
    if ($event->getArgument('config')->id() === 'testprintengine') {
      $event->setArgument('configuration', ['test_engine_suffix' => 'overridden'] + $event->getArgument('configuration'));
    }
  }

  /**
   * Alter the CSS renderable array and add our CSS.
   *
   * @param \Drupal\entity_print\Event\PrintCssAlterEvent $event
   *   The event object.
   */
  public function alterCss(PrintCssAlterEvent $event) {
    $event->getBuild()['#attached']['library'][] = 'entity_print_test_theme/module';
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PrintEvents::CONFIGURATION_ALTER => 'alterConfiguration',
      PrintEvents::CSS_ALTER => 'alterCss',
    ];
  }

}
