<?php

namespace Drupal\commerce_promotion\Entity;

use Drupal\commerce\Entity\CommerceContentEntityBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Defines the Coupon entity.
 *
 * @ContentEntityType(
 *   id = "commerce_promotion_coupon",
 *   label = @Translation("Coupon"),
 *   label_singular = @Translation("coupon"),
 *   label_plural = @Translation("coupons"),
 *   label_count = @PluralTranslation(
 *     singular = "@count coupon",
 *     plural = "@count coupons",
 *   ),
 *   handlers = {
 *     "event" = "Drupal\commerce_promotion\Event\CouponEvent",
 *     "list_builder" = "Drupal\commerce_promotion\CouponListBuilder",
 *     "storage" = "Drupal\commerce_promotion\CouponStorage",
 *     "storage_schema" = "Drupal\commerce\CommerceContentEntityStorageSchema",
 *     "access" = "Drupal\commerce_promotion\CouponAccessControlHandler",
 *     "views_data" = "Drupal\commerce\CommerceEntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\commerce_promotion\Form\CouponForm",
 *       "edit" = "Drupal\commerce_promotion\Form\CouponForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *    "local_task_provider" = {
 *       "default" = "Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\commerce_promotion\CouponRouteProvider",
 *     },
 *   },
 *   base_table = "commerce_promotion_coupon",
 *   admin_permission = "administer commerce_promotion",
 *   field_indexes = {
 *     "code"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "code",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *   },
 *   links = {
 *     "add-form" = "/promotion/{commerce_promotion}/coupons/add",
 *     "edit-form" = "/promotion/{commerce_promotion}/coupons/{commerce_promotion_coupon}/edit",
 *     "delete-form" = "/promotion/{commerce_promotion}/coupons/{commerce_promotion_coupon}/delete",
 *     "collection" = "/promotion/{commerce_promotion}/coupons",
 *   },
 * )
 */
class Coupon extends CommerceContentEntityBase implements CouponInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    $uri_route_parameters['commerce_promotion'] = $this->getPromotionId();
    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getPromotion() {
    return $this->getTranslatedReferencedEntity('promotion_id');
  }

  /**
   * {@inheritdoc}
   */
  public function getPromotionId() {
    return $this->get('promotion_id')->target_id;
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
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUsageLimit() {
    return $this->get('usage_limit')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setUsageLimit($usage_limit) {
    $this->set('usage_limit', $usage_limit);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomerUsageLimit() {
    return $this->get('usage_limit_customer')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCustomerUsageLimit($usage_limit_customer) {
    $this->set('usage_limit_customer', $usage_limit_customer);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setEnabled($enabled) {
    $this->set('status', (bool) $enabled);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStartDate($store_timezone = 'UTC') {
    if (!$this->get('start_date')->isEmpty()) {
      return new DrupalDateTime($this->get('start_date')->value, $store_timezone);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setStartDate(DrupalDateTime $start_date) {
    $this->get('start_date')->value = $start_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndDate($store_timezone = 'UTC') {
    if (!$this->get('end_date')->isEmpty()) {
      return new DrupalDateTime($this->get('end_date')->value, $store_timezone);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setEndDate(DrupalDateTime $end_date = NULL) {
    $this->get('end_date')->value = NULL;
    if ($end_date) {
      $this->get('end_date')->value = $end_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function available(OrderInterface $order) {
    if (!$this->isEnabled()) {
      return FALSE;
    }
    if (!$this->getPromotion()->available($order)) {
      return FALSE;
    }
    $date = $order->getCalculationDate();
    $store_timezone = $date->getTimezone()->getName();
    $start_date = $this->getStartDate($store_timezone);
    if ($start_date && ($start_date->format('U') > $date->format('U'))) {
      return FALSE;
    }
    $end_date = $this->getEndDate($store_timezone);
    if ($end_date && $end_date->format('U') <= $date->format('U')) {
      return FALSE;
    }

    $usage_limit = $this->getUsageLimit();
    $usage_limit_customer = $this->getCustomerUsageLimit();
    // If there are no usage limits, the coupon is available.
    if (!$usage_limit && !$usage_limit_customer) {
      return TRUE;
    }
    /** @var \Drupal\commerce_promotion\PromotionUsageInterface $usage */
    $usage = \Drupal::service('commerce_promotion.usage');

    // Check the global usage limit fist.
    if ($usage_limit && $usage_limit <= $usage->loadByCoupon($this)) {
      return FALSE;
    }

    // Only check customer usage when email address is known.
    if ($usage_limit_customer) {
      $email = $order->getEmail();
      if ($email && $usage_limit_customer <= $usage->loadByCoupon($this, $email)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Ensure there's a reference on each promotion.
    $promotion = $this->getPromotion();
    if ($promotion) {
      if (!$promotion->hasCoupon($this)) {
        $promotion->addCoupon($this);
      }
      if (!$promotion->requiresCoupon()) {
        $promotion->set('require_coupon', TRUE);
      }
      if ($promotion->hasTranslationChanges()) {
        $promotion->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    // Delete the related usage.
    /** @var \Drupal\commerce_promotion\PromotionUsageInterface $usage */
    $usage = \Drupal::service('commerce_promotion.usage');
    $usage->deleteByCoupon($entities);
    // Delete references to those coupons in promotions.
    foreach ($entities as $coupon) {
      $coupons_id[] = $coupon->id();
    }
    $promotions = \Drupal::entityTypeManager()->getStorage('commerce_promotion')->loadByProperties(['coupons' => $coupons_id]);
    /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
    foreach ($promotions as $promotion) {
      foreach ($entities as $entity) {
        $promotion->removeCoupon($entity);
      }
      $promotion->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // The promotion backreference, populated by Promotion::postSave().
    $fields['promotion_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Promotion'))
      ->setDescription(t('The parent promotion.'))
      ->setSetting('target_type', 'commerce_promotion')
      ->setReadOnly(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Coupon code'))
      ->setDescription(t('The unique, machine-readable identifier for a coupon.'))
      ->addConstraint('CouponCode')
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['start_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Start date'))
      ->setDescription(t('The date the coupon becomes valid.'))
      ->setRequired(FALSE)
      ->setSetting('datetime_type', 'datetime')
      ->setSetting('datetime_optional_label', t('Provide a start date'))
      ->setDefaultValueCallback('Drupal\commerce_promotion\Entity\Promotion::getDefaultStartDate')
      ->setDisplayOptions('form', [
        'type' => 'commerce_store_datetime',
        'weight' => 5,
      ]);

    $fields['end_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('End date'))
      ->setDescription(t('The date after which the coupon is invalid.'))
      ->setRequired(FALSE)
      ->setSetting('datetime_type', 'datetime')
      ->setSetting('datetime_optional_label', t('Provide an end date'))
      ->setDisplayOptions('form', [
        'type' => 'commerce_store_datetime',
        'weight' => 6,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the coupon was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the coupon was last edited.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['usage_limit'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Usage limit'))
      ->setDescription(t('The maximum number of times the coupon can be used. 0 for unlimited.'))
      ->setDefaultValue(0)
      ->setDisplayOptions('form', [
        'type' => 'commerce_usage_limit',
        'weight' => 4,
      ]);

    $fields['usage_limit_customer'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Customer usage limit'))
      ->setDescription(t('The maximum number of times the coupon can be used by a customer. 0 for unlimited.'))
      ->setDefaultValue(0)
      ->setDisplayOptions('form', [
        'type' => 'commerce_usage_limit',
        'weight' => 4,
      ]);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('Whether the coupon is enabled.'))
      ->setDefaultValue(TRUE)
      ->setRequired(TRUE)
      ->setSettings([
        'on_label' => t('Enabled'),
        'off_label' => t('Disabled'),
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 0,
      ]);

    return $fields;
  }

}
