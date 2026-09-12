<?php

namespace Drupal\commerce_promotion\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Views hook implementations for Commerce Promotion.
 */
class CommercePromotionViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $data = [];

    // Expose the promotion usage data to views.
    $data['commerce_promotion_usage']['table']['group'] = $this->t('Promotion Usage');
    $data['commerce_promotion_usage']['table']['base'] = [
      'title' => $this->t('Promotion Usage'),
      'field' => 'usage_id',
      'help' => $this->t('Data for Commerce Promotion usage.'),
      'weight' => -10,
    ];
    $data['commerce_promotion_usage']['table']['join'] = [
      'commerce_promotion_coupon' => [
        'left_field' => 'id',
        'field' => 'coupon_id',
      ],
      'commerce_promotion_field_data' => [
        'left_field' => 'promotion_id',
        'field' => 'promotion_id',
      ],
      'commerce_order' => [
        'left_field' => 'order_id',
        'field' => 'order_id',
      ],
      'users_field_data' => [
        'left_field' => 'mail',
        'field' => 'mail',
      ],
    ];
    $data['commerce_promotion_usage']['promotion_id'] = [
      'title' => $this->t('Promotion'),
      'help' => $this->t('The promotion.'),
      'relationship' => [
        'base' => 'commerce_promotion_field_data',
        'base field' => 'promotion_id',
        'id' => 'standard',
        'label' => $this->t('Promotion'),
      ],
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'standard',
      ],
    ];
    $data['commerce_promotion_usage']['coupon_id'] = [
      'title' => $this->t('Coupon'),
      'help' => $this->t('The coupon.'),
      'relationship' => [
        'base' => 'commerce_promotion_coupon',
        'base field' => 'id',
        'id' => 'standard',
        'label' => $this->t('Coupon'),
      ],
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'standard',
      ],
    ];
    $data['commerce_promotion_usage']['order_id'] = [
      'title' => $this->t('Order'),
      'help' => $this->t('The order.'),
      'relationship' => [
        'base' => 'commerce_order',
        'base field' => 'order_id',
        'id' => 'standard',
        'label' => $this->t('Order'),
      ],
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'standard',
      ],
    ];

    $data['commerce_promotion_usage']['mail'] = [
      'title' => $this->t('Customer email'),
      'help' => $this->t('The customer email.'),
      'relationship' => [
        'base' => 'users_field_data',
        'base field' => 'mail',
        'id' => 'standard',
        'label' => $this->t('User'),
      ],
      'field' => [
        'id' => 'standard',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['views']['commerce_coupon_redemption'] = [
      'title' => $this->t('Coupon redemption'),
      'help' => $this->t('Displays a coupon redemption pane, requires an Order ID argument.'),
      'area' => [
        'id' => 'commerce_coupon_redemption',
      ],
    ];

    return $data;
  }

}
