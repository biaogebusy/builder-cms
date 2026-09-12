<?php

namespace Drupal\commerce_payment\EventSubscriber;

use Drupal\commerce_payment\Form\OrderPaymentAddForm;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Site\Settings;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters the payment add form route to enable a redesigned merchant UX.
 *
 * This route subscriber allows dynamically switching the form controller used
 * for the payment add form ("/admin/commerce/orders/{order}/payments/add")
 * based on a flag in settings.php.
 *
 * By default, the redesigned single-page OrderPaymentAddForm is used. This
 * provides a simplified merchant experience and is the recommended approach
 * moving forward.
 *
 * To preserve the legacy two-step behavior, add the following to your
 * settings.php file:
 *
 * @code
 * $settings['commerce_payment_use_legacy_add_payment_form'] = TRUE;
 * @endcode
 *
 * This mechanism makes it easy to toggle between implementations without
 * modifying route definitions or overriding services. It also ensures backward
 * compatibility for sites that may still depend on the legacy form.
 *
 * @see \Drupal\commerce_payment\Form\OrderPaymentAddForm
 * @see \Drupal\commerce_payment\Form\PaymentAddForm
 */
class OrderPaymentAddFormRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $route = $collection->get('entity.commerce_payment.add_form');
    if (!$route) {
      return;
    }
    // If flag is explicitly set to TRUE, use the old two-step add payment form.
    if (Settings::get('commerce_payment_use_legacy_add_payment_form', FALSE)) {
      $route->setDefault('_form', '\Drupal\commerce_payment\Form\PaymentAddForm');
    }
    // Otherwise, default to the redesigned merchant-facing payment form.
    else {
      $route->setDefault('_form', OrderPaymentAddForm::class);
      $route->setRequirement('_custom_access', OrderPaymentAddForm::class . '::checkAccess');
    }
  }

}
