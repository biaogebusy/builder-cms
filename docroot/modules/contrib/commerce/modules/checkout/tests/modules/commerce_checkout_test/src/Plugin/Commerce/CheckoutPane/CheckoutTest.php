<?php

namespace Drupal\commerce_checkout_test\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a test pane used in test to test the dependency removal.
 *
 * @CommerceCheckoutPane(
 *   id = "checkout_test",
 *   label = @Translation("Checkout test"),
 *   default_step = "review",
 * )
 */
class CheckoutTest extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    return $pane_form;
  }

}
