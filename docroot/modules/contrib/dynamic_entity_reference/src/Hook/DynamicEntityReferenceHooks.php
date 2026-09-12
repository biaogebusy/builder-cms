<?php

namespace Drupal\dynamic_entity_reference\Hook;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem;

/**
 * Hook implementations for dynamic_entity_reference.
 */
final class DynamicEntityReferenceHooks {

  public function __construct(
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly LoggerChannelFactoryInterface $loggerChannelFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Implements hook_entity_bundle_delete().
   *
   * We are duplicating the work done by
   * \Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem::onDependencyRemoval()
   * because we need to take into account bundles that are not provided by a
   * config entity type so they are not part of the config dependencies.
   */
  #[Hook('entity_bundle_delete')]
  public function entityBundleDelete($entity_type_id, $bundle) {
    // Gather a list of all entity reference fields.
    $map = $this->entityFieldManager->getFieldMapByFieldType('dynamic_entity_reference');
    $ids = [];
    foreach ($map as $type => $info) {
      foreach ($info as $name => $data) {
        foreach ($data['bundles'] as $bundle_name) {
          $ids[] = "{$type}.{$bundle_name}.{$name}";
        }
      }
    }
    // Update the 'target_bundles' handler setting if needed.
    foreach ($this->entityTypeManager->getStorage('field_config')->loadMultiple($ids) as $field_config) {
      $settings = $field_config->getSettings();
      $target_types = DynamicEntityReferenceItem::getTargetTypes($settings);
      if (in_array($entity_type_id, $target_types, TRUE) && isset($settings[$entity_type_id]['handler_settings'])) {
        $handler_settings = $settings[$entity_type_id]['handler_settings'];
        if (isset($handler_settings['target_bundles'][$bundle])) {
          unset($handler_settings['target_bundles'][$bundle]);
          $settings[$entity_type_id]['handler_settings'] = $handler_settings;
          $field_config->setSettings($settings);
          $field_config->save();
          // In case we deleted the only target bundle allowed by the field we
          // have to log a critical message because the field will not function
          // correctly anymore.
          if ($handler_settings['target_bundles'] === []) {
            $this->loggerChannelFactory->get('dynamic_entity_reference')->critical('The %target_bundle bundle (entity type: %target_entity_type) was deleted. As a result, the %field_name dynamic entity reference field (entity_type: %entity_type, bundle: %bundle) no longer has any valid bundle it can reference. The field is not working correctly anymore and has to be adjusted.', [
              '%target_bundle' => $bundle,
              '%target_entity_type' => $entity_type_id,
              '%field_name' => $field_config->getName(),
              '%entity_type' => $field_config->getTargetEntityTypeId(),
              '%bundle' => $field_config->getTargetBundle(),
            ]);
          }
        }
      }
    }
  }

  /**
   * Implements hook_field_info_alter().
   */
  #[Hook('field_info_alter')]
  public function fieldInfoAlter(&$info) {
    if (\version_compare(\Drupal::VERSION, '10.1.999', '<')) {
      $info['dynamic_entity_reference']['category'] = new TranslatableMarkup('Dynamic Reference');
    }
  }

}
