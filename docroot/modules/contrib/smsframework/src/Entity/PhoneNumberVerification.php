<?php

declare(strict_types = 1);

namespace Drupal\sms\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the phone number verification entity.
 *
 * @ContentEntityType(
 *   id = "sms_phone_number_verification",
 *   label = @Translation("Phone Number Verification"),
 *   label_collection = @Translation("Phone Number Verifications"),
 *   label_singular = @Translation("phone number verification"),
 *   label_plural = @Translation("phone number verifications"),
 *   label_count = @PluralTranslation(
 *     singular = "@count phone number verification",
 *     plural = "@count phone number verifications",
 *   ),
 *   base_table = "sms_phone_number_verification",
 *   entity_keys = {
 *     "id" = "id",
 *   },
 * )
 */
class PhoneNumberVerification extends ContentEntityBase implements PhoneNumberVerificationInterface {

  /**
   * The bundle of the associated entity.
   *
   * This value is read on a database level.
   *
   * @var string
   */
  protected $bundle;

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    return $this->get('entity')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntity(EntityInterface $entity) {
    $this->set('entity', $entity);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPhoneNumber() {
    return $this->get('phone')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPhoneNumber($phone_number) {
    $this->set('phone', $phone_number);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCode() {
    return $this->get('code')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCode($code) {
    $this->set('code', $code);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    $this->set('status', (bool) $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Phone verification ID'))
      ->setDescription(t('The phone verification ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['entity'] = BaseFieldDefinition::create('dynamic_entity_reference')
      ->setLabel(t('Entity'))
      ->setDescription(t('The entity for this verification code.'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    // Bundle field is required for statistics and bulk cleanup.
    $fields['bundle'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Bundle'))
      ->setDescription(t('The bundle of the entity.'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    $fields['phone'] = BaseFieldDefinition::create('telephone')
      ->setLabel(t('Phone'))
      ->setDescription(t('Phone number.'))
      ->setDefaultValue('')
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type' => 'hidden',
      ]);

    $fields['code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Verification code'))
      ->setDescription(t('The generated verification code.'))
      ->setRequired(TRUE)
      ->setDefaultValue('');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created on'))
      ->setDescription(t('The time that the verification code was created.'));

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('Verification status. 0=not verified, 1=verified.'))
      ->setDefaultValue(FALSE)
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    // Update bundle field with bundle of entity.
    $entity = $this->getEntity();
    if ($entity instanceof EntityInterface) {
      $this->set('bundle', $entity->bundle());
    }
  }

}
