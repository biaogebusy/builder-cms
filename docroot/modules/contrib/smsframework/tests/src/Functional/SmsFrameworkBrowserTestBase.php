<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\sms\Entity\PhoneNumberSettingsInterface;
use Drupal\sms\Plugin\SmsGatewayPluginManagerInterface;
use Drupal\sms\Provider\SmsProviderInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Base test class for functional browser tests.
 *
 * Provides commonly used functionality for tests.
 */
abstract class SmsFrameworkBrowserTestBase extends BrowserTestBase {

  use SmsFrameworkTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sms',
    'sms_test_gateway',
    'telephone',
    'dynamic_entity_reference',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The gateway manager.
   *
   * @var \Drupal\sms\Plugin\SmsGatewayPluginManagerInterface
   */
  protected SmsGatewayPluginManagerInterface $gatewayManager;

  /**
   * The default SMS provider service.
   *
   * @var \Drupal\sms\Provider\SmsProviderInterface
   */
  protected SmsProviderInterface $defaultSmsProvider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->gatewayManager = $this->container->get('plugin.manager.sms_gateway');
    $this->defaultSmsProvider = $this->container->get('sms.provider');
  }

  /**
   * Utility to create phone number settings.
   *
   * Creates new field storage and field configs.
   *
   * @return \Drupal\sms\Entity\PhoneNumberSettingsInterface
   *   A phone number settings entity.
   */
  protected function createPhoneNumberSettings($entity_type_id, $bundle): PhoneNumberSettingsInterface {
    $entity_type_manager = \Drupal::entityTypeManager();

    /** @var \Drupal\field\FieldStorageConfigInterface $field_storage */
    $field_storage = $entity_type_manager->getStorage('field_storage_config')
      ->create([
        'entity_type' => $entity_type_id,
        'field_name' => mb_strtolower($this->randomMachineName()),
        'type' => 'telephone',
      ]);
    $field_storage
      ->setCardinality(1)
      ->save();

    $entity_type_manager->getStorage('field_config')
      ->create([
        'entity_type' => $entity_type_id,
        'bundle' => $bundle,
        'field_name' => $field_storage->getName(),
      ])->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $entity_form_display */
    $entity_form_display = EntityFormDisplay::load($entity_type_id . '.' . $bundle . '.default');
    if (!$entity_form_display) {
      $entity_form_display = EntityFormDisplay::create([
        'targetEntityType' => $entity_type_id,
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $entity_form_display->save();

    $entity_form_display
      ->setComponent($field_storage->getName(), ['type' => 'sms_telephone'])
      ->save();

    /** @var \Drupal\sms\Entity\PhoneNumberSettingsInterface $phone_number_settings */
    $phone_number_settings = $entity_type_manager
      ->getStorage('phone_number_settings')
      ->create();

    $phone_number_settings
      ->setFieldName('phone_number', $field_storage->getName())
      ->setPhoneNumberEntityTypeId($entity_type_id)
      ->setPhoneNumberBundle($bundle)
      ->setVerificationCodeLifetime(3601)
      ->setVerificationMessage('Verification code is [sms:verification-code]')
      ->setPurgeVerificationPhoneNumber(TRUE)
      ->save();

    return $phone_number_settings;
  }

}
