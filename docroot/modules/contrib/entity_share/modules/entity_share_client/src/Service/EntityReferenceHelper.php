<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Provides helper functions related to Entity reference fields.
 */
class EntityReferenceHelper implements EntityReferenceHelperInterface {

  /**
   * The entity type definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface[]
   */
  protected $entityDefinitions;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->entityDefinitions = $entity_type_manager->getDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function relationshipHandleable(FieldItemListInterface $field) {
    if (!$field instanceof EntityReferenceFieldItemListInterface) {
      return static::RELATIONSHIP_NOT_ENTITY_REFERENCE;
    }

    $relationship_handleable = FALSE;
    $settings = $field->getItemDefinition()->getSettings();

    // Entity reference and Entity reference revisions.
    if (isset($settings['target_type'])) {
      $relationship_handleable = !$this->isUserOrConfigEntity($settings['target_type']);
    }
    // Dynamic entity reference.
    elseif (isset($settings['entity_type_ids'])) {
      foreach ($settings['entity_type_ids'] as $entity_type_id) {
        $relationship_handleable = !$this->isUserOrConfigEntity($entity_type_id);
        if (!$relationship_handleable) {
          break;
        }
      }
    }

    return $relationship_handleable ? static::RELATIONSHIP_HANDLEABLE : static::RELATIONSHIP_NOT_HANDLEABLE;
  }

  /**
   * Helper function to check if an entity type id is a user or a config entity.
   *
   * @param string $entity_type_id
   *   The entity type id.
   *
   * @return bool
   *   TRUE if the entity type is user or a config entity. FALSE otherwise.
   */
  protected function isUserOrConfigEntity($entity_type_id) {
    if ($entity_type_id == 'user') {
      return TRUE;
    }
    elseif ($this->entityDefinitions[$entity_type_id]->getGroup() == 'configuration') {
      return TRUE;
    }

    return FALSE;
  }

}
