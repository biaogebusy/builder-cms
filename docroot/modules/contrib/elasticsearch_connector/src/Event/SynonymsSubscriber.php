<?php

declare(strict_types=1);

namespace Drupal\elasticsearch_connector\Event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to AlterSettingsEvents to add synonym settings.
 */
class SynonymsSubscriber implements EventSubscriberInterface {

  /**
   * Handles the AlterSettingsEvent.
   *
   * @param \Drupal\elasticsearch_connector\Event\AlterSettingsEvent $event
   *   The AlterSettingsEvent.
   */
  public function onAlterSettings(AlterSettingsEvent $event): void {
    $synonyms = $event->getBackendConfig()['advanced']['synonyms'] ?? [];
    if ($synonyms) {
      $settings = $event->getSettings();
      $settings['analysis']['filter']['synonyms'] = [
        'type' => 'synonym_graph',
        'lenient' => TRUE,
        'synonyms' => array_map('trim', $synonyms),
      ];
      $settings['analysis']['analyser']['querytime_synonyms'] = [
        'type' => 'custom',
        'tokenizer' => 'standard',
        'filter' => ['lowercase', 'asciifolding', 'synonyms'],
      ];
      $event->setSettings($settings);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      AlterSettingsEvent::class => 'onAlterSettings',
    ];
  }

}
