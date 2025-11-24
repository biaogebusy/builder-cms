<?php

declare(strict_types = 1);

namespace Drupal\sms\Provider;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Random;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Utility\Token;
use Drupal\sms\Direction;
use Drupal\sms\Exception\PhoneNumberSettingsException;
use Drupal\sms\Message\SmsMessage;

/**
 * Phone number verification provider.
 */
class PhoneNumberVerification implements PhoneNumberVerificationInterface {

  /**
   * Storage for phone number settings.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected ConfigEntityStorageInterface $phoneNumberSettings;

  /**
   * Storage for Phone Number Verification entities.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $phoneNumberVerificationStorage;

  /**
   * Constructs a new PhoneNumberProvider object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Utility\Token $token
   *   The token replacement system.
   * @param \Drupal\sms\Provider\SmsProviderInterface $smsProvider
   *   The SMS provider.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected Token $token,
    protected SmsProviderInterface $smsProvider,
    protected TimeInterface $time,
  ) {
    $this->phoneNumberSettings = $entityTypeManager->getStorage('phone_number_settings');
    $this->phoneNumberVerificationStorage = $entityTypeManager->getStorage('sms_phone_number_verification');
  }

  /**
   * {@inheritdoc}
   */
  public function getPhoneNumberSettings($entity_type_id, $bundle) {
    return $this->phoneNumberSettings
      ->load($entity_type_id . '.' . $bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function getPhoneNumberSettingsForEntity(EntityInterface $entity) {
    if (!$phone_number_settings = $this->getPhoneNumberSettings($entity->getEntityTypeId(), $entity->bundle())) {
      throw new PhoneNumberSettingsException(sprintf('Entity phone number config does not exist for bundle %s:%s', $entity->getEntityTypeId(), $entity->bundle()));
    }
    return $phone_number_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getPhoneVerificationByCode($code) {
    $entities = $this->phoneNumberVerificationStorage
      ->loadByProperties([
        'code' => $code,
      ]);
    return reset($entities);
  }

  /**
   * {@inheritdoc}
   */
  public function getPhoneVerificationByPhoneNumber($phone_number, $verified = TRUE, $entity_type = NULL) {
    $properties['phone'] = $phone_number;
    if (isset($entity_type)) {
      $properties['entity__target_type'] = $entity_type;
    }
    if (isset($verified)) {
      $properties['status'] = (int) $verified;
    }
    return $this->phoneNumberVerificationStorage
      ->loadByProperties($properties);
  }

  /**
   * {@inheritdoc}
   */
  public function getPhoneVerificationByEntity(EntityInterface $entity, $phone_number) {
    $entities = $this->phoneNumberVerificationStorage
      ->loadByProperties([
        'entity__target_id' => $entity->id(),
        'entity__target_type' => $entity->getEntityTypeId(),
        'phone' => $phone_number,
      ]);
    return reset($entities);
  }

  /**
   * {@inheritdoc}
   */
  public function newPhoneVerification(EntityInterface $entity, $phone_number) {
    $config = $this->getPhoneNumberSettingsForEntity($entity);
    $message = $config->getVerificationMessage() ?: '';

    // @todo Replace with code generator.
    $random = new Random();
    $code = strtoupper($random->name(6));

    /** @var \Drupal\sms\Entity\PhoneNumberVerificationInterface $phone_verification */
    $phone_verification = $this->phoneNumberVerificationStorage->create();
    $phone_verification
      ->setCode($code)
      ->setStatus(FALSE)
      ->setPhoneNumber($phone_number)
      ->setEntity($entity)
      ->save();

    if ($phone_verification) {
      $sms_message = new SmsMessage();
      $sms_message
        ->addRecipient($phone_number)
        ->setOption('_is_verification_message', TRUE)
        ->setMessage($message)
        ->setDirection(Direction::OUTGOING);

      $data['sms-message'] = $sms_message;
      $data['sms_verification_code'] = $phone_verification->getCode();

      $sms_message
        ->setMessage($this->token->replace($message, $data))
        ->setAutomated(FALSE);

      $this->smsProvider
        ->queue($sms_message);
    }

    return $phone_verification;
  }

  /**
   * {@inheritdoc}
   */
  public function updatePhoneVerificationByEntity(EntityInterface $entity) {
    try {
      $phone_number_settings = $this->getPhoneNumberSettingsForEntity($entity);
      $field_name = $phone_number_settings->getFieldName('phone_number');
      if (!empty($field_name)) {
        $items_original = isset($entity->original) ? $entity->original->{$field_name} : NULL;
        $items = $entity->{$field_name};
      }
    }
    catch (PhoneNumberSettingsException $e) {
      // Missing phone number configuration for this entity.
    }

    // $items can be unassigned because field_name is not configured, or is NULL
    // because there is no items.
    if (!isset($items)) {
      return;
    }

    $numbers = [];
    assert($items instanceof FieldItemListInterface);
    foreach ($items as $item) {
      $phone_number = $item->value;
      $numbers[] = $phone_number;

      if (!$this->getPhoneVerificationByEntity($entity, $phone_number)) {
        $this->newPhoneVerification($entity, $phone_number);
      }
    }

    if (isset($items_original) && !$items->equals($items_original)) {
      foreach ($items_original as $item) {
        $phone_number = $item->value;
        // A phone number was deleted.
        if (!in_array($phone_number, $numbers)) {
          if ($phone_verification = $this->getPhoneVerificationByEntity($entity, $phone_number)) {
            $phone_verification->delete();
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deletePhoneVerificationByEntity(EntityInterface $entity) {
    // Check the entity uses phone numbers. To save on a SQL call, and to
    // prevent having to install phone number verification for SMS Framework
    // tests which delete entities. Which would otherwise error on non-existent
    // tables.
    try {
      $this->getPhoneNumberSettingsForEntity($entity);
      $verification_entities = $this->phoneNumberVerificationStorage
        ->loadByProperties([
          'entity__target_id' => $entity->id(),
          'entity__target_type' => $entity->getEntityTypeId(),
        ]);
      $this->phoneNumberVerificationStorage->delete($verification_entities);
    }
    catch (PhoneNumberSettingsException $e) {
    }
  }

  /**
   * {@inheritdoc}
   */
  public function purgeExpiredVerifications() {
    $current_time = $this->time->getRequestTime();

    $verification_ids = [];
    foreach ($this->configFactory->listAll('sms.phone.') as $config_id) {
      $config = $this->configFactory->get($config_id);
      $lifetime = $config->get('verification_code_lifetime');
      if (!empty($lifetime)) {
        $verification_ids += $this->phoneNumberVerificationStorage->getQuery()
          ->accessCheck(FALSE)
          ->condition('entity__target_type', $config->get('entity_type'))
          ->condition('bundle', $config->get('bundle'))
          ->condition('status', 0)
          ->condition('created', ($current_time - $lifetime), '<')
          ->execute();
      }
    }

    /** @var \Drupal\sms\Entity\PhoneNumberVerificationInterface $phone_number_verification */
    foreach ($this->phoneNumberVerificationStorage->loadMultiple($verification_ids) as $phone_number_verification) {
      if ($entity = $phone_number_verification->getEntity()) {
        try {
          $config = $this->getPhoneNumberSettingsForEntity($entity);
          $purge = $config->getPurgeVerificationPhoneNumber();
          $field_name = $config->getFieldName('phone_number');
          if (!empty($purge) && isset($entity->{$field_name})) {
            $entity->{$field_name}->filter(static function ($item) use ($phone_number_verification) {
              return $item->value != $phone_number_verification->getPhoneNumber();
            });
            $entity->save();
          }
        }
        catch (EntityStorageException $e) {
          // Failed to save entity.
        }
      }
      $this->phoneNumberVerificationStorage->delete([$phone_number_verification]);
    }
  }

}
