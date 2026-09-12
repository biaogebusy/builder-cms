<?php

namespace Drupal\commerce_order\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Views hook implementations for Commerce Order.
 */
class CommerceOrderViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $data['views']['commerce_order_total'] = [
      'title' => $this->t('Order total'),
      'help' => $this->t('Displays the order total field, requires an Order ID argument.'),
      'area' => [
        'id' => 'commerce_order_total',
      ],
    ];
    $data['commerce_order']['billing_profile'] = [
      'title' => $this->t('Billing Profile'),
      'help' => $this->t('Reference to the billing profile of a commerce order.'),
      'relationship' => [
        'group' => $this->t('Order'),
        'base' => 'profile',
        'base field' => 'profile_id',
        'field' => 'billing_profile__target_id',
        'id' => 'standard',
        'label' => $this->t('Billing Profile'),
      ],
    ];

    return $data;
  }

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    $data['commerce_order']['store_id']['field']['id'] = 'commerce_store';
  }

}
