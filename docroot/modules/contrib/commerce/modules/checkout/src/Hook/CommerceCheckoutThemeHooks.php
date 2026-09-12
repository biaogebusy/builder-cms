<?php

namespace Drupal\commerce_checkout\Hook;

use Drupal\commerce_order\OrderTotalSummaryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;

/**
 * Theme hook implementations for Commerce Checkout.
 */
class CommerceCheckoutThemeHooks {

  /**
   * Constructs a new CommerceCheckoutThemeHooks object.
   *
   * @param \Drupal\commerce_order\OrderTotalSummaryInterface $orderTotalSummary
   *   The order total summary.
   */
  public function __construct(
    protected readonly OrderTotalSummaryInterface $orderTotalSummary,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'commerce_checkout_progress' => [
        'variables' => [
          'steps' => [],
        ],
      ],
      'commerce_checkout_completion_message' => [
        'variables' => [
          'order_entity' => NULL,
          'message' => NULL,
          'payment_instructions' => NULL,
        ],
      ],
      'commerce_checkout_form' => [
        'render element' => 'form',
      ],
      'commerce_checkout_form__with_sidebar' => [
        'base hook' => 'commerce_checkout_form',
      ],
      'commerce_checkout_order_summary' => [
        'variables' => [
          'order_entity' => NULL,
          'checkout_step' => '',
        ],
      ],
      'commerce_checkout_pane' => [
        'render element' => 'elements',
      ],
      'commerce_checkout_completion_register' => [
        'render element' => 'form',
      ],
    ];
  }

  /**
   * Implements hook_preprocess_HOOK() for 'commerce_checkout_order_summary'.
   */
  #[Hook('preprocess_commerce_checkout_order_summary')]
  public function preprocessCommerceCheckoutOrderSummary(array &$variables): void {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $variables['order_entity'];
    $variables['totals'] = $this->orderTotalSummary->buildTotals($order);
    $variables['rendered_totals'] = [
      '#theme' => 'commerce_order_total_summary',
      '#order_entity' => $order,
      '#totals' => $variables['totals'],
    ];
  }

  /**
   * Implements hook_theme_suggestions_HOOK() for 'commerce_checkout_form'.
   */
  #[Hook('theme_suggestions_commerce_checkout_form')]
  public function themeSuggestionsCommerceCheckoutForm(array $variables): array {
    $original = $variables['theme_hook_original'];
    $suggestions = [];
    // If the checkout form has a sidebar, suggest the enhanced layout.
    if (isset($variables['form']['sidebar'])
      && Element::isVisibleElement($variables['form']['sidebar'])
    ) {
      $suggestions[] = $original . '__with_sidebar';
    }

    return $suggestions;
  }

  /**
   * Implements hook_theme_suggestions_HOOK() for 'commerce_checkout_pane'.
   */
  #[Hook('theme_suggestions_commerce_checkout_pane')]
  public function themeSuggestionsCommerceCheckoutPane(array $variables): array {
    $original = $variables['theme_hook_original'];
    $suggestions = [];
    $suggestions[] = $original . '__' . $variables['elements']['#pane_id'];

    return $suggestions;
  }

}
