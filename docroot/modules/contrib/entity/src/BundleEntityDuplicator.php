<?php

namespace Drupal\entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class BundleEntityDuplicator implements BundleEntityDuplicatorInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new BundleEntityDuplicator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function duplicate(ConfigEntityInterface $bundle_entity, array $values) {
    $entity_type = $bundle_entity->getEntityType();
    if (!$entity_type->getBundleOf()) {
      throw new \InvalidArgumentException(sprintf('The "%s" entity type is not a bundle entity type.', $entity_type->id()));
    }
    $id_key = $entity_type->getKey('id');
    if (empty($values[$id_key])) {
      throw new \InvalidArgumentException(sprintf('The $values[\'%s\'] key is empty or missing.', $id_key));
    }

    $entity = $bundle_entity->createDuplicate();
    foreach ($values as $property_name => $value) {
      $entity->set($property_name, $value);
    }
    $entity->save();
    $this->duplicateFields($bundle_entity, $entity->id());
    $this->duplicateDisplays($bundle_entity, $entity->id());

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function duplicateFields(ConfigEntityInterface $bundle_entity, $target_bundle_id) {
    $entity_type = $bundle_entity->getEntityType();
    $bundle_of = $entity_type->getBundleOf();
    if (!$bundle_of) {
      throw new \InvalidArgumentException(sprintf('The "%s" entity type is not a bundle entity type.', $entity_type->id()));
    }
    if (empty($target_bundle_id)) {
      throw new \InvalidArgumentException('The $target_bundle_id must not be empty.');
    }

    $id_prefix = $bundle_of . '.' . $bundle_entity->id() . '.';
    $fields = $this->loadEntities('field_config', $id_prefix);
    foreach ($fields as $field) {
      /** @var \Drupal\Core\Field\FieldConfigInterface $field */
      $duplicate_field = $field->createDuplicate();
      $duplicate_field->set('id', $bundle_of . '.' . $target_bundle_id . '.' . $field->getName());
      $duplicate_field->set('bundle', $target_bundle_id);
      $duplicate_field->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function duplicateDisplays(ConfigEntityInterface $bundle_entity, $target_bundle_id) {
    $entity_type = $bundle_entity->getEntityType();
    $bundle_of = $entity_type->getBundleOf();
    if (!$bundle_of) {
      throw new \InvalidArgumentException(sprintf('The "%s" entity type is not a bundle entity type.', $entity_type->id()));
    }
    if (empty($target_bundle_id)) {
      throw new \InvalidArgumentException('The $target_bundle_id must not be empty.');
    }

    $id_prefix = $bundle_of . '.' . $bundle_entity->id() . '.';
    $form_displays = $this->loadEntities('entity_form_display', $id_prefix);
    $view_displays = $this->loadEntities('entity_view_display', $id_prefix);
    foreach ($form_displays as $form_display) {
      /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
      $duplicate_form_display = $form_display->createDuplicate();
      $duplicate_form_display->set('id', $bundle_of . '.' . $target_bundle_id . '.' . $form_display->getMode());
      $duplicate_form_display->set('bundle', $target_bundle_id);
      $duplicate_form_display->save();
    }
    foreach ($view_displays as $view_display) {
      /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $view_display */
      $duplicate_view_display = $view_display->createDuplicate();
      $duplicate_view_display->set('id', $bundle_of . '.' . $target_bundle_id . '.' . $view_display->getMode());
      $duplicate_view_display->set('bundle', $target_bundle_id);
      $duplicate_view_display->save();
    }
  }

  /**
   * Loads config entities with the given ID prefix.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $id_prefix
   *   The ID prefix.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface[]
   *   The loaded config entities.
   */
  protected function loadEntities($entity_type_id, $id_prefix) {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $ids = $storage->getQuery()
      ->condition('id', $id_prefix, 'STARTS_WITH')
      ->accessCheck(TRUE)
      ->execute();

    return $ids ? $storage->loadMultiple($ids) : [];
  }

}
