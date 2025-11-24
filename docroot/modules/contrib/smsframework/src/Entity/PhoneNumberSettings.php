<?php

declare(strict_types = 1);

namespace Drupal\sms\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\field\Entity\FieldConfig;

/**
 * Defines storage for an SMS Gateway instance.
 *
 * @ConfigEntityType(
 *   id = "phone_number_settings",
 *   label = @Translation("Phone number settings"),
 *   label_collection = @Translation("Phone number settings"),
 *   label_singular = @Translation("phone number settings"),
 *   label_plural = @Translation("phone number settings"),
 *   label_count = @PluralTranslation(
 *     singular = "@count phone number settings",
 *     plural = "@count phone number settings",
 *   ),
 *   config_prefix = "phone",
 *   admin_permission = "administer smsframework",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id"
 *   },
 *   handlers = {
 *     "list_builder" = "\Drupal\sms\Lists\PhoneNumberSettingsListBuilder",
 *     "form" = {
 *       "add" = "Drupal\sms\Form\PhoneNumberSettingsForm",
 *       "default" = "Drupal\sms\Form\PhoneNumberSettingsForm",
 *       "edit" = "Drupal\sms\Form\PhoneNumberSettingsForm",
 *       "delete" = "Drupal\sms\Form\PhoneNumberSettingsDeleteForm",
 *     }
 *   },
 *   links = {
 *     "canonical" = "/admin/config/smsframework/phone_number/{phone_number_settings}",
 *     "edit-form" = "/admin/config/smsframework/phone_number/{phone_number_settings}",
 *     "delete-form" = "/admin/config/smsframework/phone_number/{phone_number_settings}/delete",
 *   },
 *   config_export = {
 *     "id",
 *     "entity_type",
 *     "bundle",
 *     "automated_optout",
 *     "verification_message",
 *     "verification_code_lifetime",
 *     "purge_verification_phone_number",
 *     "fields",
 *   },
 * )
 */
class PhoneNumberSettings extends ConfigEntityBase implements PhoneNumberSettingsInterface {

  /**
   * Phone number settings entity ID.
   *
   * ID is a concatenation of entity type ID and bundle
   * "{entity_type_id}.{bundle}" suitable as config ID "sms.phone.*.*".
   *
   * @var string
   */
  protected $id;

  /**
   * Entity type ID of phone number settings.
   *
   * @var string
   */
  protected $entity_type;

  /**
   * Bundle of phone number settings.
   *
   * @var string
   */
  protected $bundle;

  /**
   * Message template to send for phone number verification.
   *
   * @var string
   */
  protected $verification_message = '';

  /**
   * Number of seconds before phone number verifications expire.
   *
   * @var int
   */
  protected $verification_code_lifetime = 0;

  /**
   * Whether to remove phone numbers from entities when verifications expire.
   *
   * @var bool
   */
  protected $purge_verification_phone_number = TRUE;

  /**
   * Field name mapping.
   *
   * Keys are sms.phone.*.*.fields.$key, values are field names.
   *
   * @var array
   */
  protected $fields = [];

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->entity_type . '.' . $this->bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getPhoneNumberEntityTypeId() {
    return $this->entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setPhoneNumberEntityTypeId($entity_type_id) {
    $this->entity_type = $entity_type_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPhoneNumberBundle() {
    return $this->bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function setPhoneNumberBundle($bundle) {
    $this->bundle = $bundle;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVerificationMessage() {
    return $this->verification_message;
  }

  /**
   * {@inheritdoc}
   */
  public function setVerificationMessage($message) {
    $this->verification_message = $message;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVerificationCodeLifetime() {
    return $this->verification_code_lifetime;
  }

  /**
   * {@inheritdoc}
   */
  public function setVerificationCodeLifetime($lifetime) {
    $this->verification_code_lifetime = $lifetime;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPurgeVerificationPhoneNumber() {
    return $this->purge_verification_phone_number;
  }

  /**
   * {@inheritdoc}
   */
  public function setPurgeVerificationPhoneNumber($purge) {
    $this->purge_verification_phone_number = $purge;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldName($map) {
    return $this->fields[$map] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldName($map, $field_name) {
    $this->fields[$map] = $field_name;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    // Delete associated phone number verifications.
    // Does not remove phone number field values.
    $verification_storage = \Drupal::entityTypeManager()
      ->getStorage('sms_phone_number_verification');

    $verification_ids = [];
    /** @var static $phone_number_settings */
    foreach ($entities as $phone_number_settings) {
      $verification_ids += $verification_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('entity__target_type', $phone_number_settings->getPhoneNumberEntityTypeId())
        ->condition('bundle', $phone_number_settings->getPhoneNumberBundle())
        ->execute();
    }

    $verification_storage->delete($verification_storage->loadMultiple($verification_ids));
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    foreach ($this->fields as $map => $field_name) {
      $field_config = FieldConfig::loadByName(
        $this->getPhoneNumberEntityTypeId(),
        $this->getPhoneNumberBundle(),
        $field_name,
      );
      if ($field_config) {
        $this->addDependency('config', $field_config->getConfigDependencyName());
      }
    }

    return $this->dependencies;
  }

}
