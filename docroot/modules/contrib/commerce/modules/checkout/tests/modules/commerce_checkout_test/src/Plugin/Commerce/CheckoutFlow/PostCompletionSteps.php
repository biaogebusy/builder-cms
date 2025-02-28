<?php

namespace Drupal\commerce_checkout_test\Plugin\Commerce\CheckoutFlow;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\MultistepDefault;

/**
 * A checkout flow that allows for post-completion steps.
 *
 * @CommerceCheckoutFlow(
 *   id = "commerce_checkout_test_post_completion_steps",
 *   label = "Post completion steps",
 * )
 */
class PostCompletionSteps extends MultistepDefault {

  /**
   * {@inheritdoc}
   */
  public function getStepId($requested_step_id = NULL) {}

}
