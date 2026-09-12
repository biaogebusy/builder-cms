<?php

namespace Drupal\eck\Entity;

use Drupal\Core\Entity\BundleEntityStorageInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\eck\EckEntityInterface;
use Drupal\user\UserInterface;

/**
 * Defines the ECK entity.
 *
 * @ingroup eck
 */
class EckEntity extends ContentEntityBase implements EckEntityInterface {

  use EntityPublishedTrait;
  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += ['uid' => \Drupal::currentUser()->id()];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \UnexpectedValueException
   */
  public static function create(array $values = []) {
    $entity_type_manager = \Drupal::entityTypeManager();

    if (isset($values['entity_type'])) {
      $storage = $entity_type_manager->getStorage($values['entity_type']);
      return $storage->create($values);
    }

    if (version_compare(\Drupal::VERSION, '9.3', '>=')) {
      $entity_type_repository = \Drupal::service('entity_type.repository');
      $class_name = static::class;
      $entity_type_id = $entity_type_repository->getEntityTypeFromClass($class_name);
      $storage = $entity_type_manager->getStorage($entity_type_id);

      // Always explicitly specify the bundle if the entity has a bundle class.
      if ($storage instanceof BundleEntityStorageInterface && ($bundle = $storage->getBundleFromClass($class_name))) {
        $values[$storage->getEntityType()->getKey('bundle')] = $bundle;
      }

      return $storage->create($values);
    }

    throw new \UnexpectedValueException('ECK does not support calling EckEntity::create() without providing an entity_type in the $values argument.');
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);

      // If no owner has been set explicitly, make the anonymous user the owner.
      if (!$translation->getOwner()) {
        $translation->setOwnerId(0);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    if ($this->hasField('uid')) {
      return $this->get('uid')->entity;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    if ($owner = $this->getOwner()) {
      return $owner->id();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    $entityType = $this->getEntityType();

    if ($entityType->hasKey('published')) {
      return $this->get($entityType->getKey('published'))->value;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished() {
    $entityType = $this->getEntityType();

    if ($entityType->hasKey('published')) {
      return $this->set($entityType->getKey('published'), TRUE);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setUnpublished() {
    $entityType = $this->getEntityType();

    if ($entityType->hasKey('published')) {
      return $this->set($entityType->getKey('published'), FALSE);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    if ($this->hasField('uid')) {
      $this->set('uid', $uid);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->setOwnerId($account->id());

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    if ($this->hasField('title')) {
      $title = $this->get('title')->first();
      if (!empty($title)) {
        return $title->getString();
      }
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTime() {
    if ($this->hasField('changed')) {
      return $this->get('changed')->value;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setChangedTime($timestamp) {
    if ($this->hasField('changed')) {
      $this->set('changed', $timestamp);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    if ($this->hasField('created')) {
      return $this->get('created')->value;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    if ($this->hasField('created')) {
      $this->set('created', $timestamp);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entityType) {
    $fields = parent::baseFieldDefinitions($entityType);
    $config = \Drupal::config("eck.eck_entity_type.{$entityType->id()}");

    $fields['type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Type'))
      ->setDescription(t('The entity type.'))
      ->setSetting('target_type', "{$entityType->id()}_type")
      ->setReadOnly(TRUE);

    // Title field for the entity.
    if ($config->get('title')) {
      $fields['title'] = BaseFieldDefinition::create('string')
        ->setLabel(t('Title'))
        ->setDescription(t('The title of the entity.'))
        ->setRequired(TRUE)
        ->setTranslatable(TRUE)
        ->setSetting('max_length', 255)
        ->setDisplayOptions('view', [
          'label' => 'hidden',
          'type' => 'string',
          'weight' => -5,
        ]
        )
        ->setDisplayOptions('form', [
          'type' => 'string_textfield',
          'weight' => -5,
        ]
        )
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);
    }

    // Author field for the entity.
    if ($config->get('uid')) {
      $fields['uid'] = BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Authored by'))
        ->setDescription(t('The username of the entity author.'))
        ->setSetting('target_type', 'user')
        ->setSetting('handler', 'default')
        ->setTranslatable(TRUE)
        ->setDisplayOptions('view', [
          'label' => 'hidden',
          'type' => 'author',
          'weight' => 0,
        ])
        ->setDisplayOptions('form', [
          'type' => 'entity_reference_autocomplete',
          'weight' => 5,
          'settings' => [
            'match_operator' => 'CONTAINS',
            'size' => 60,
            'placeholder' => '',
          ],
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);
    }

    // Created field for the entity.
    if ($config->get('created')) {
      $fields['created'] = BaseFieldDefinition::create('created')
        ->setLabel(t('Authored on'))
        ->setDescription(t('The time that the entity was created.'))
        ->setTranslatable(TRUE)
        ->setDisplayOptions('view', [
          'label' => 'hidden',
          'type' => 'timestamp',
          'weight' => 0,
        ])
        ->setDisplayOptions('form', [
          'type' => 'datetime_timestamp',
          'weight' => 10,
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);
    }

    // Changed field for the entity.
    if ($config->get('changed')) {
      $fields['changed'] = BaseFieldDefinition::create('changed')
        ->setLabel(t('Changed'))
        ->setDescription(t('The time that the entity was last edited.'))
        ->setTranslatable(TRUE)
        ->setDisplayConfigurable('view', TRUE);
    }

    // Status field for the entity.
    if ($config->get('status')) {
      $fields += static::publishedBaseFieldDefinitions($entityType);
      $fields['status']
        ->setLabel(t('Published'))
        ->setInitialValue(TRUE)
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayOptions('form', [
          'type' => 'boolean_checkbox',
          'settings' => [
            'display_label' => TRUE,
          ],
          'weight' => 100,
        ]);
    }

    return $fields;
  }

}
