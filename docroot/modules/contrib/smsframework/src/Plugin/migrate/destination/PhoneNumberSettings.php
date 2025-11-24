<?php

declare(strict_types = 1);

namespace Drupal\sms\Plugin\migrate\destination;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\migrate\Plugin\migrate\destination\EntityConfigBase;
use Drupal\migrate\Row;
use Drupal\sms\Entity\PhoneNumberSettingsInterface;
use Drupal\sms\Form\PhoneNumberSettingsForm;

/**
 * Destination plugin for SMS phone number verifications.
 *
 * @MigrateDestination(
 *   id = "entity:phone_number_settings"
 * )
 */
class PhoneNumberSettings extends EntityConfigBase {

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    $return = parent::import($row, $old_destination_id_values);
    if ($return) {
      // After successful import of the phone_number_setting, the phone number
      // field should be created and attached to the user entity type.
      /** @var \Drupal\sms\Entity\PhoneNumberSettingsInterface $phone_number_setting */
      $phone_number_setting = $this->storage->load(reset($return));
      $this->createPhoneNumberField($phone_number_setting);
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function rollback(array $destination_identifier) {
    /** @var \Drupal\sms\Entity\PhoneNumberSettingsInterface $phone_number_settings */
    $phone_number_settings = $this->storage->load(reset($destination_identifier));

    $entity_type_id = $phone_number_settings->getPhoneNumberEntityTypeId();
    $bundle = $phone_number_settings->getPhoneNumberBundle();
    $field_name = $phone_number_settings->getFieldName('phone_number');

    // Delete entity form display component.
    $entity_form_display = EntityFormDisplay::load($entity_type_id . '.' . $bundle . '.default');
    $entity_form_display?->removeComponent($field_name);

    // Delete the field storage and field instance.
    FieldStorageConfig::loadByName($entity_type_id, $field_name)->delete();

    // Remove the phone number settings.
    parent::rollback($destination_identifier);
  }

  /**
   * Creates a phone number field.
   *
   * @param \Drupal\sms\Entity\PhoneNumberSettingsInterface $phone_number_settings
   *   The phone number settings for a given entity type.
   *
   * @see \Drupal\sms\Form\PhoneNumberSettingsForm::createNewField()
   */
  protected function createPhoneNumberField(PhoneNumberSettingsInterface $phone_number_settings) {
    PhoneNumberSettingsForm::createNewField(
      $phone_number_settings->getPhoneNumberEntityTypeId(),
      $phone_number_settings->getPhoneNumberBundle(),
      $phone_number_settings->getFieldName('phone_number'),
    );
  }

}
