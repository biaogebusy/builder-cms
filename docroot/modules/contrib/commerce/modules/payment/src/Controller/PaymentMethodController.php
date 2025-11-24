<?php

namespace Drupal\commerce_payment\Controller;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Returns responses for PaymentMethodController routes.
 */
class PaymentMethodController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Mark payment method as default.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $commerce_payment_method
   *   The payment method.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect back to the user payment methods listing.
   */
  public function setDefault(PaymentMethodInterface $commerce_payment_method) {
    $commerce_payment_method->setDefault(TRUE);
    $commerce_payment_method->save();

    $this->messenger()->addMessage($this->t('The %label payment method has been marked as default.', ['%label' => $commerce_payment_method->label()]));

    /** @var \Drupal\Core\Url $url */
    $url = $commerce_payment_method->toUrl('collection');
    return $this->redirect($url->getRouteName(), $url->getRouteParameters(), $url->getOptions());
  }

}
