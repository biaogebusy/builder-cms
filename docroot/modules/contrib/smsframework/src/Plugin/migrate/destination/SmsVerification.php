<?php

declare(strict_types = 1);

namespace Drupal\sms\Plugin\migrate\destination;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\sms\Entity\PhoneNumberVerificationInterface as EntityPhoneNumberVerificationInterface;
use Drupal\sms\Provider\PhoneNumberVerificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Destination plugin for SMS phone number verifications.
 *
 * @MigrateDestination(
 *   id = "entity:sms_phone_number_verification"
 * )
 */
class SmsVerification extends EntityContentBase implements ContainerFactoryPluginInterface {

  /**
   * The phone number verification service.
   *
   * @var \Drupal\sms\Provider\PhoneNumberVerificationInterface
   */
  protected $phoneNumberVerificationService;

  /**
   * Builds a phone number verification entity destination.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The storage for this entity type.
   * @param array $bundles
   *   The list of bundles this entity type has.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type plugin manager service.
   * @param \Drupal\sms\Provider\PhoneNumberVerificationInterface $verification
   *   The phone number verification service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, EntityStorageInterface $storage, array $bundles, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, PhoneNumberVerificationInterface $verification) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $storage, $bundles, $entity_field_manager, $field_type_manager);
    $this->phoneNumberVerificationService = $verification;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    $entity_type = static::getEntityTypeId($plugin_id);
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('entity_type.manager')->getStorage($entity_type),
      array_keys($container->get('entity_type.bundle.info')->getBundleInfo($entity_type)),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('sms.phone_number.verification'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    $return = parent::import($row, $old_destination_id_values);
    if ($return) {
      // After successful import of the verification data, the phone number
      // should be updated on the corresponding user entity.
      /** @var \Drupal\sms\Entity\PhoneNumberVerification $verification */
      $verification = $this->storage->load(reset($return));
      $this->setVerifiedValue($verification, $row->getSourceProperty('delta'));
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function rollback(array $destination_identifier) {
    /** @var \Drupal\sms\Entity\PhoneNumberVerification $verification */
    $verification = $this->storage->load(reset($destination_identifier));
    $this->unsetVerifiedValue($verification);
    // Remove the verified user phone number.
    parent::rollback($destination_identifier);
  }

  /**
   * Sets the verified value for the user entity.
   *
   * @param \Drupal\sms\Entity\PhoneNumberVerificationInterface $verification
   *   The phone number verification for a given user entity.
   * @param int $delta
   *   The specific item of the phone number field to set.
   */
  protected function setVerifiedValue(EntityPhoneNumberVerificationInterface $verification, $delta) {
    if (!isset($delta)) {
      $delta = 0;
    }
    $user_entity = $verification->getEntity();
    $phone_number_settings = $this->phoneNumberVerificationService
      ->getPhoneNumberSettingsForEntity($user_entity);
    if ($user_entity && $phone_number_settings) {
      $phone_field_name = $phone_number_settings->getFieldName('phone_number');
      $user_entity->{$phone_field_name}[$delta] = $verification->getPhoneNumber();
      $user_entity->save();
    }
  }

  /**
   * Unsets the verified value for the user entity.
   *
   * @param \Drupal\sms\Entity\PhoneNumberVerificationInterface $verification
   *   The phone number verification for a given user entity.
   */
  protected function unsetVerifiedValue(EntityPhoneNumberVerificationInterface $verification) {
    $user_entity = $verification->getEntity();
    $phone_number_settings = $this->phoneNumberVerificationService
      ->getPhoneNumberSettingsForEntity($user_entity);
    if ($user_entity && $phone_number_settings) {
      $phone_field_name = $phone_number_settings->getFieldName('phone_number');
      $user_entity->{$phone_field_name} = '';
      $user_entity->save();
    }
  }

}
