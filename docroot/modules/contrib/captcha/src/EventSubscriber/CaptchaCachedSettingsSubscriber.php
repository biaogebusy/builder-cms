<?php

namespace Drupal\captcha\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A subscriber clearing the cached definitions when saving captcha settings.
 */
class CaptchaCachedSettingsSubscriber implements EventSubscriberInterface {

  /**
   * The Element info.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected $elementInfo;

  /**
   * CaptchaCachedSettingsSubscriber constructor.
   *
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $elementInfo
   *   Constructor.
   */
  public function __construct(ElementInfoManagerInterface $elementInfo) {
    $this->elementInfo = $elementInfo;
  }

  /**
   * Clearing the cached definitions whenever the settings are modified.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The Event to process.
   */
  public function onSave(ConfigCrudEvent $event) {
    // Changing the Captcha settings means that any page might result in other
    // settings for captcha so the cached definitions need to be cleared.
    if ($event->getConfig()->getName() === 'captcha.settings') {
      $this->elementInfo->clearCachedDefinitions();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::SAVE][] = ['onSave'];
    return $events;
  }

}
