<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginBase;
use Drupal\entity_share_client\RuntimeImportContext;

/**
 * Update changed time.
 *
 * Because, by example, it could have been altered with relationship saved.
 *
 * @ImportProcessor(
 *   id = "changed_time",
 *   label = @Translation("Changed time"),
 *   description = @Translation("Set the changed time to changed time from remote data."),
 *   stages = {
 *     "process_entity" = 100,
 *   },
 *   locked = false,
 * )
 */
class ChangedTime extends ImportProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function processEntity(RuntimeImportContext $runtime_import_context, ContentEntityInterface $processed_entity, array $entity_json_data) {
    $field_mappings = $runtime_import_context->getFieldMappings();
    $entity_type_id = $processed_entity->getEntityTypeId();
    $entity_bundle = $processed_entity->bundle();

    $changed_public_name = FALSE;
    if (isset($field_mappings[$entity_type_id][$entity_bundle]['changed'])) {
      $changed_public_name = $field_mappings[$entity_type_id][$entity_bundle]['changed'];
    }

    if (
      $changed_public_name &&
      !empty($entity_json_data['attributes'][$changed_public_name]) &&
      method_exists($processed_entity, 'setChangedTime')
    ) {
      $remote_changed_value = $entity_json_data['attributes'][$changed_public_name];
      $remote_changed_timestamp = EntityShareUtility::convertChangedTime($remote_changed_value);
      $processed_entity->setChangedTime($remote_changed_timestamp);
    }
  }

}
