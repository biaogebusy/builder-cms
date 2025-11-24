<?php

declare(strict_types = 1);

namespace Drupal\sms\Lists;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\sms\Provider\PhoneNumberVerificationInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Builds a list of phone number settings.
 */
class PhoneNumberSettingsListBuilder extends ConfigEntityListBuilder {

  /**
   * Storage for Phone Number Verification entities.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $phoneNumberVerificationStorage;

  /**
   * Phone number verification provider.
   *
   * @var \Drupal\sms\Provider\PhoneNumberVerificationInterface
   */
  protected $phoneNumberVerificationProvider;

  /**
   * Time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new PhoneNumberSettingsListBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Entity\EntityStorageInterface $phone_number_verification_storage
   *   Storage for Phone Number Verification entities.
   * @param \Drupal\sms\Provider\PhoneNumberVerificationInterface $phone_number_verification_provider
   *   The phone number verification provider.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityStorageInterface $phone_number_verification_storage, PhoneNumberVerificationInterface $phone_number_verification_provider, TimeInterface $time) {
    parent::__construct($entity_type, $storage);
    $this->phoneNumberVerificationStorage = $phone_number_verification_storage;
    $this->phoneNumberVerificationProvider = $phone_number_verification_provider;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager')->getStorage('sms_phone_number_verification'),
      $container->get('sms.phone_number.verification'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['entity_type'] = $this->t('Entity type');
    $header['bundle'] = $this->t('Bundle');
    $header['count_expired'] = $this->t('Expired');
    $header['count_verified'] = $this->t('Verified');
    $header['count_unverified'] = $this->t('Unverified');
    $header['count_total'] = $this->t('Total');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\sms\Entity\PhoneNumberSettingsInterface $entity */
    $entity_type_id = $entity->getPhoneNumberEntityTypeId();
    $bundle = $entity->getPhoneNumberBundle();
    $row['entity_type'] = $entity_type_id;
    $row['bundle'] = $bundle;

    $phone_number_settings = $this->phoneNumberVerificationProvider
      ->getPhoneNumberSettings($entity_type_id, $bundle);
    $lifetime = $phone_number_settings->getVerificationCodeLifetime() ?: 0;

    $row['count_expired'] = $this->buildPhoneNumberVerificationQuery($entity_type_id, $bundle)
      ->condition('status', 0)
      ->condition('created', ($this->time->getRequestTime() - $lifetime), '<')
      ->count()
      ->execute();

    $row['count_verified'] = $this->buildPhoneNumberVerificationQuery($entity_type_id, $bundle)
      ->condition('status', 1)
      ->count()
      ->execute();

    $row['count_unverified'] = $this->buildPhoneNumberVerificationQuery($entity_type_id, $bundle)
      ->condition('status', 0)
      ->count()
      ->execute();

    $row['count_total'] = $this->buildPhoneNumberVerificationQuery($entity_type_id, $bundle)
      ->count()
      ->execute();

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $render = parent::render();
    $render['table']['#empty'] = t('No phone number settings found.');
    return $render;
  }

  /**
   * Builds a phone number verification query.
   *
   * @param string $entity_type_id
   *   Entity type to query.
   * @param string $bundle
   *   Entity bundle to query.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   A phone number entity query.
   */
  protected function buildPhoneNumberVerificationQuery($entity_type_id, $bundle) {
    return $this->phoneNumberVerificationStorage
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('entity__target_type', $entity_type_id)
      ->condition('bundle', $bundle);
  }

}
