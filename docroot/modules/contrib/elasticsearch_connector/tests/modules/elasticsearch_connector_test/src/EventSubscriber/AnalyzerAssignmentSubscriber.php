<?php

declare(strict_types=1);

namespace Drupal\elasticsearch_connector_test\EventSubscriber;

use Drupal\elasticsearch_connector\Event\AlterSettingsEvent;
use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An event subscriber to assign a custom analyzer to a field.
 *
 * @see \Drupal\elasticsearch_connector_test\Plugin\ElasticSearch\Analyzer\AnalyzerOne
 */
class AnalyzerAssignmentSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function getSubscribedEvents() {
    return [
      FieldMappingEvent::class => 'onFieldMapping',
      AlterSettingsEvent::class => 'onAlterIndexSettings',
    ];
  }

  /**
   * Alter the index definition to modify the custom analyzer.
   *
   * @param \Drupal\elasticsearch_connector\Event\AlterSettingsEvent $event
   *   An event triggered when an index is being created.
   */
  public function onAlterIndexSettings(AlterSettingsEvent $event): void {
    $settings = $event->getSettings();

    // If the 'elasticsearch_connector_test_analyzer_one' analyzer is defined
    // for the index being added, then add the 'trim' token filter.
    // See
    // \Drupal\elasticsearch_connector_test\Plugin\ElasticSearch\Analyzer\AnalyzerOne.
    if (isset($settings['analysis']['analyzer']['elasticsearch_connector_test_analyzer_one']['filter'])) {
      $settings['analysis']['analyzer']['elasticsearch_connector_test_analyzer_one']['filter'][] = 'trim';
    }

    $event->setSettings($settings);
  }

  /**
   * Alter field mappings to assign custom analyzers.
   *
   * @param \Drupal\elasticsearch_connector\Event\FieldMappingEvent $event
   *   An event triggered when a field is mapped.
   */
  public function onFieldMapping(FieldMappingEvent $event): void {
    $properties = $event->getParam();

    // Assign the 'elasticsearch_connector_test_analyzer_one' analyzer defined
    // in the class
    // \Drupal\elasticsearch_connector_test\Plugin\ElasticSearch\Analyzer\AnalyzerOne
    // to the field with the machine name 'body_with_custom_analyzer'.
    if ($event->getField()->getFieldIdentifier() === 'body_with_custom_analyzer') {
      $properties['analyzer'] = 'elasticsearch_connector_test_analyzer_one';
    }

    $event->setParam($properties);
  }

}
