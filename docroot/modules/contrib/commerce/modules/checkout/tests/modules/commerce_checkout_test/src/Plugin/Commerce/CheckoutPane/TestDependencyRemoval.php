<?php

namespace Drupal\commerce_checkout_test\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;

/**
 * Provides a test pane used in test to test the dependency removal.
 */
#[CommerceCheckoutPane(
  id: "test_dependency_removal",
  label: new TranslatableMarkup("Checkout test"),
  admin_description: new TranslatableMarkup("This is just for testing."),
  default_step: "review",
)]
class TestDependencyRemoval extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    return $pane_form;
  }

}
