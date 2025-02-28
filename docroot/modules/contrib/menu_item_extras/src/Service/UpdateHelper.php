<?php

namespace Drupal\menu_item_extras\Service;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Class for service MenuLinkContentUpdateHelper.
 *
 * @package Drupal\menu_item_extras\Service
 */
class UpdateHelper {

  /**
   * Creates or load field storage.
   *
   * @param string $field_name
   *   Field name.
   * @param string $entity_type
   *   Entity type id.
   * @param string $type
   *   Field type id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|static
   *   Entity of field storage
   */
  public function checkBodyFieldStorage($field_name, $entity_type, $type) {
    // Add or remove the body field, as needed.
    $field_storage = FieldStorageConfig::loadByName($entity_type, $field_name);
    if (empty($field_storage)) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'type' => $type,
      ]);
      $field_storage->save();
    }

    return $field_storage;
  }

  /**
   * Creates or load field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $field_storage
   *   Field storage instance.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity instance.
   * @param string $field_name
   *   Field name.
   * @param string $label
   *   Label for field.
   * @param mixed[] $settings
   *   Field settings.
   *
   * @return \Drupal\Core\Entity\EntityInterface|static
   *   Field instance.
   */
  public function checkBodyField(EntityInterface $field_storage, EntityInterface $entity, $field_name, $label, array $settings) {
    $field = FieldConfig::loadByName($entity->getEntityTypeId(), $entity->bundle(), $field_name);
    if (empty($field)) {
      $field = FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity->getEntityTypeId(),
        'field_storage' => $field_storage,
        'bundle' => $entity->bundle(),
        'label' => $label,
        'settings' => $settings,
      ]);
      $field->save();
    }
    return $field;
  }

  /**
   * Creates or load view display.
   *
   * @param string $display_mode
   *   Name of display mode.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity instance.
   * @param string $field_name
   *   Field name.
   * @param array $settings
   *   Formatter settings.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null|static
   *   View display instance.
   */
  public function checkViewDisplay($display_mode, EntityInterface $entity, $field_name, array $settings) {
    // Try loading the display from configuration.
    $display = EntityViewDisplay::load($entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $display_mode);
    // If not found, create a fresh display object.
    // We do not preemptively create new entity_view_display configuration
    // entries for each existing entity type and bundle
    // whenever a new view mode becomes available.
    // Instead, configuration entries are only created when
    // a display object is explicitly configured and saved.
    if (!$display) {
      $display = EntityViewDisplay::create([
        'targetEntityType' => $entity->getEntityTypeId(),
        'bundle' => $entity->bundle(),
        'mode' => $display_mode,
        'status' => TRUE,
      ]);
      $display->save();
    }
    $component = $display->getComponent($field_name);
    if (empty($component)) {
      // Assign display settings for the 'default' and 'teaser' view modes.
      $display->setComponent($field_name, $settings);
      $display->save();
    }
    return $display;
  }

  /**
   * Creates or load form display.
   *
   * @param string $display_mode
   *   Name of display mode.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity instance.
   * @param string $field_name
   *   Field name.
   * @param array $settings
   *   Widget settings.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null|static
   *   Form display instance.
   */
  public function checkFormDisplay($display_mode, EntityInterface $entity, $field_name, array $settings) {
    $display = EntityFormDisplay::load($entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $display_mode);

    // If not found, create a fresh entity object.
    // We do not preemptively create new entity form display
    // configuration entries for each existing entity type
    // and bundle whenever a new form mode becomes available.
    // Instead, configuration entries are only created when
    // an entity form display is explicitly configured and saved.
    if (!$display) {
      $display = EntityFormDisplay::create([
        'targetEntityType' => $entity->getEntityTypeId(),
        'bundle' => $entity->bundle(),
        'mode' => $display_mode,
        'status' => TRUE,
      ]);
    }
    $component = $display->getComponent($field_name);
    if (empty($component)) {
      // Assign widget settings for the 'default' form mode.
      $display
        ->setComponent($field_name, $settings);
      $display->save();
    }
    return $display;
  }

}
