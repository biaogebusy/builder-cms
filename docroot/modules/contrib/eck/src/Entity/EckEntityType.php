<?php

namespace Drupal\eck\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\eck\EckEntityTypeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines the ECK Entity Type config entities.
 *
 * @ConfigEntityType(
 *   id = "eck_entity_type",
 *   label = @Translation("ECK entity type"),
 *   handlers = {
 *     "list_builder" = "Drupal\eck\Controller\EckEntityTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\eck\Form\EntityType\EckEntityTypeAddForm",
 *       "edit" = "Drupal\eck\Form\EntityType\EckEntityTypeEditForm",
 *       "delete" = "Drupal\eck\Form\EntityType\EckEntityTypeDeleteForm"
 *     }
 *   },
 *   admin_permission = "administer eck entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/eck/{eck_entity_type}",
 *     "delete-form" = "/admin/structure/eck/{eck_entity_type}/delete"
 *   },
 *   config_export = {
 *     "id",
 *     "uuid",
 *     "label",
 *     "created",
 *     "changed",
 *     "uid",
 *     "title"
 *   }
 * )
 *
 * @ingroup eck
 */
class EckEntityType extends ConfigEntityBase implements EckEntityTypeInterface {

  use StringTranslationTrait;

  /**
   * If this entity type has an "Author" base field.
   *
   * @var bool
   */
  protected $uid;

  /**
   * If this entity type has a "Title" base field.
   *
   * @var bool
   */
  protected $title;

  /**
   * If this entity type has a "Created" base field.
   *
   * @var bool
   */
  protected $created;

  /**
   * If this entity type has a "Changed" base field.
   *
   * @var bool
   */
  protected $changed;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    // Entity ids are limited to 32 characters, but since eck adds '_type' to
    // the id of it's bundle storage, that id would be too long. we therefore
    // limit the id to 27 characters.
    if (strlen($this->id()) > ECK_ENTITY_ID_MAX_LENGTH) {
      throw new \RuntimeException("Entity id has more than " . ECK_ENTITY_ID_MAX_LENGTH . " characters.");
    }

    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Clear the router cache to prevent RouteNotFoundException while creating
    // the edit link.
    \Drupal::service('router.builder')->rebuild();
    $edit_link = $this->toLink($this->t('Edit entity type'), 'edit-form')->toString();

    if ($update) {
      $this->logger($this->id())->notice(
        'Entity type %label has been updated.',
        ['%label' => $this->label(), 'link' => $edit_link]
      );
    }
    else {
      $this->logger($this->id())->notice(
        'Entity type %label has been added.',
        ['%label' => $this->label(), 'link' => $edit_link]
      );
    }

    \Drupal::service('eck.entity.entity_update_service')->applyUpdates($this->id());
  }

  /**
   * Load all reference fields with provided target type.
   *
   * @param string $target_entity_type_id
   *   The entity type id created by ECK.
   *
   * @return \Drupal\field\FieldConfigInterface[]
   *   Returns loaded config fields entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public static function loadReferenceFieldsByType($target_entity_type_id) {
    $entity_manager = \Drupal::entityTypeManager();

    $fields_array = \Drupal::service('entity_field.manager')->getFieldMapByFieldType('entity_reference');
    $field_storage = $entity_manager->getStorage('field_config');

    /** @var \Drupal\field\FieldConfigInterface[] $fields_list */
    $fields_list = $list = [];

    // Get list of fields with type entity_reference.
    foreach ($fields_array as $entity_type_id => $fields) {
      foreach ($fields as $field_name => $info) {
        foreach ($info['bundles'] as $bundle) {
          if ($field = $field_storage->load($entity_type_id . '.' . $bundle . '.' . $field_name)) {
            $fields_list[] = $field;
          }
        }
      }
    }

    foreach ($fields_list as $field) {
      if ($field->getSetting('target_type') == $target_entity_type_id) {
        $list[] = $field;
      }
    }

    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    // Remove all reference fields.
    foreach (array_keys($entities) as $entity_type_id) {
      if ($fields = static::loadReferenceFieldsByType($entity_type_id)) {
        foreach ($fields as $field) {
          $field->delete();
          field_purge_field($field);
        }
      }
    }
  }

  /**
   * Gets the logger for a specific channel.
   *
   * @param string $channel
   *   The name of the channel.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger for this channel.
   */
  protected function logger($channel) {
    return \Drupal::getContainer()->get('logger.factory')->get($channel);
  }

  /**
   * {@inheritdoc}
   */
  public function hasAuthorField() {
    return isset($this->uid) && $this->uid;
  }

  /**
   * {@inheritdoc}
   */
  public function hasChangedField() {
    return isset($this->changed) && $this->changed;
  }

  /**
   * {@inheritdoc}
   */
  public function hasCreatedField() {
    return isset($this->created) && $this->created;
  }

  /**
   * {@inheritdoc}
   */
  public function hasTitleField() {
    return isset($this->title) && $this->title;
  }

}
