<?php

namespace Drupal\votingapi\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Drupal\votingapi\VoteResultInterface;

/**
 * Defines the VoteResult entity.
 *
 * @ingroup votingapi
 *
 * @ContentEntityType(
 *   id = "vote_result",
 *   label = @Translation("Vote result"),
 *   label_collection = @Translation("Vote results"),
 *   label_singular = @Translation("vote result"),
 *   label_plural = @Translation("vote results"),
 *   label_count = @PluralTranslation(
 *     singular = "@count vote result",
 *     plural = "@count vote results",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\votingapi\VoteResultStorage",
 *     "access" = "Drupal\votingapi\VoteResultAccessControlHandler",
 *     "views_data" = "Drupal\votingapi\Entity\VoteResultViewsData",
 *   },
 *   base_table = "votingapi_result",
 *   entity_keys = {
 *     "id" = "id"
 *   }
 * )
 */
class VoteResult extends ContentEntityBase implements VoteResultInterface {

  /**
   * {@inheritdoc}
   */
  public function getVotedEntityType() {
    return $this->get('entity_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setVotedEntityType($name) {
    return $this->set('entity_type', $name);
  }

  /**
   * {@inheritdoc}
   */
  public function getVotedEntityId() {
    return $this->get('entity_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setVotedEntityId($id) {
    return $this->set('entity_id', $id);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->get('value')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value) {
    return $this->set('value', $value);
  }

  /**
   * {@inheritdoc}
   */
  public function getValueType() {
    return $this->get('value_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setValueType($value_type) {
    return $this->set('value_type', $value_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getFunction() {
    return $this->get('function')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setFunction($function) {
    return $this->set('function', $function);
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('timestamp')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    return $this->set('timestamp', $timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The vote result ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Type'))
      ->setDescription(t('The vote type.'))
      ->setSetting('target_type', 'vote_type')
      ->setReadOnly(TRUE);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity Type'))
      ->setDescription(t('The type from the voted entity.'))
      ->setSettings([
        'max_length' => 64,
      ])
      ->setRequired(TRUE);

    $fields['entity_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Voted entity'))
      ->setDescription(t('The ID from the voted entity'))
      ->setRequired(TRUE);

    $fields['value'] = BaseFieldDefinition::create('float')
      ->setLabel(t('Value'))
      ->setDescription(t('The numeric value of the vote.'))
      ->setRequired(TRUE);

    $fields['value_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Value Type'))
      ->setSettings([
        'max_length' => 64,
      ])
      ->setRequired(TRUE);

    $fields['function'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Function'))
      ->setDescription(t('Function to apply to the numbers.'))
      ->setSettings([
        'max_length' => 100,
      ])
      ->setRequired(TRUE);

    $fields['timestamp'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'))
      ->setRequired(TRUE);

    return $fields;
  }

}
