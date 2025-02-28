<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\forms_steps\WorkflowInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Workflow entity.
 *
 * @ContentEntityType(
 *   id = "forms_steps_workflow",
 *   label = @Translation("Workflow Instances"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "list_builder" = "Drupal\forms_steps\Builder\WorkflowListBuilder",
 *   },
 *   list_cache_contexts = { "user" },
 *   admin_permission = "administer contact entity",
 *   base_table = "forms_steps_workflow",
 *   entity_keys = {
 *     "id" = "id"
 *   },
 * )
 */
class Workflow extends ContentEntityBase implements WorkflowInterface {
  use EntityChangedTrait;

  /**
 * Entity type id. */
  const ENTITY_TYPE = 'forms_steps_workflow';

  /**
   * {@inheritdoc}
   *
   * When a new entity instance is added, set the user_id entity reference to
   * the current user as the creator of the instance.
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner(): UserInterface {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId(): ?int {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Workflow entity.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['instance_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Instance ID'))
      ->setDescription(t('The Instance ID of the Workflow entity.'))
      ->setReadOnly(TRUE);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity Type'))
      ->setDescription(t('The Entity Type of the Workflow entity.'))
      ->setReadOnly(FALSE);

    $fields['bundle'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Bundle'))
      ->setDescription(t('The Bundle of the Workflow entity.'))
      ->setReadOnly(FALSE);

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The ID of the entity id.'))
      ->setReadOnly(FALSE);

    $fields['form_mode'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Form Mode'))
      ->setDescription(t('The Form Mode of the Workflow entity.'))
      ->setReadOnly(FALSE);

    $fields['forms_steps'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Forms Steps'))
      ->setDescription(t('The Workflow machine name of the Workflow entity.'))
      ->setReadOnly(FALSE);

    $fields['step'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Step'))
      ->setDescription(t('The Step of the Workflow entity.'))
      ->setReadOnly(FALSE);

    // Owner field of the workflow instance.
    // Entity reference field, holds the reference to the user object.
    // The view shows the user name field of the user.
    // The form presents a auto complete field for the user name.
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User Name'))
      ->setDescription(t('The Name of the associated user.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference',
        'weight' => -3,
      ]);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code of inventory entity.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

}
