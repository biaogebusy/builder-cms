<?php

declare(strict_types = 1);

namespace Drupal\sms\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Interface for SMS Gateway configuration entity.
 */
interface PhoneNumberSettingsInterface extends ConfigEntityInterface {

  /**
   * Gets the phone number settings entity type.
   *
   * @return string
   *   Entity type ID of phone number settings.
   */
  public function getPhoneNumberEntityTypeId();

  /**
   * Sets the phone number settings entity type.
   *
   * @param string $entity_type_id
   *   Entity type ID of phone number settings.
   *
   * @return $this
   *   Return phone number settings for chaining.
   */
  public function setPhoneNumberEntityTypeId($entity_type_id);

  /**
   * Gets the phone number settings bundle.
   *
   * @return string
   *   Bundle of phone number settings.
   */
  public function getPhoneNumberBundle();

  /**
   * Sets the phone number settings bundle.
   *
   * @param string $bundle
   *   Bundle of phone number settings.
   *
   * @return $this
   *   Return phone number settings for chaining.
   */
  public function setPhoneNumberBundle($bundle);

  /**
   * Gets the message template to send for phone number verification.
   *
   * @return string
   *   Message template to send for phone number verification.
   */
  public function getVerificationMessage();

  /**
   * Sets the message template to send for phone number verification.
   *
   * @param string $message
   *   Message template to send for phone number verification.
   *
   * @return $this
   *   Return phone number settings for chaining.
   */
  public function setVerificationMessage($message);

  /**
   * Gets the number of seconds before phone number verifications expire.
   *
   * @return int
   *   Number of seconds before phone number verifications expire.
   */
  public function getVerificationCodeLifetime();

  /**
   * Sets the number of seconds before phone number verifications expire.
   *
   * @param int $lifetime
   *   Number of seconds before phone number verifications expire.
   *
   * @return $this
   *   Return phone number settings for chaining.
   */
  public function setVerificationCodeLifetime($lifetime);

  /**
   * Whether phone numbers are purged when verifications expire.
   *
   * Determines if phone number field values are removed when phone number
   * verifications expire.
   *
   * @return bool
   *   Whether to remove phone number field values
   */
  public function getPurgeVerificationPhoneNumber();

  /**
   * Whether phone numbers should be purged when verifications expire.
   *
   * Sets if phone number field values are removed when phone number
   * verifications expire.
   *
   * @param bool $purge
   *   Whether to remove phone number field values.
   *
   * @return $this
   *   Return phone number settings for chaining.
   */
  public function setPurgeVerificationPhoneNumber($purge);

  /**
   * Gets a field mapping.
   *
   * @param string $map
   *   ID to map field name, as found in sms.phone.*.*.fields.$map.
   *
   * @return string|null
   *   A field name, or NULL if not set.
   */
  public function getFieldName($map);

  /**
   * Gets a field mapping.
   *
   * @param string $map
   *   ID to map field name, as found in sms.phone.*.*.fields.$map.
   * @param string|null $field_name
   *   A field name, or NULL to unset.
   *
   * @return $this
   *   Return phone number settings for chaining.
   */
  public function setFieldName($map, $field_name);

}
