<?php

namespace Drupal\commerce_payment;

use Drupal\commerce\CommerceContentEntityStorage;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the payment method storage.
 */
class PaymentMethodStorage extends CommerceContentEntityStorage implements PaymentMethodStorageInterface {

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->time = $container->get('datetime.time');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadReusable(UserInterface $account, PaymentGatewayInterface $payment_gateway, array $billing_countries = []) {
    // Anonymous users cannot have reusable payment methods.
    if ($account->isAnonymous()) {
      return [];
    }
    if (!($payment_gateway->getPlugin() instanceof SupportsStoredPaymentMethodsInterface)) {
      return [];
    }

    $query = $this->getQuery();
    $query
      ->condition('uid', $account->id())
      ->condition('payment_gateway', $payment_gateway->id())
      ->condition('payment_gateway_mode', $payment_gateway->getPlugin()->getMode())
      ->condition('reusable', TRUE)
      ->condition($query->orConditionGroup()
        ->condition('expires', $this->time->getRequestTime(), '>')
        ->condition('expires', 0))
      ->accessCheck(FALSE)
      ->sort('method_id', 'DESC');
    $result = $query->execute();
    if (empty($result)) {
      return [];
    }

    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface[] $payment_methods */
    $payment_methods = $this->loadMultiple($result);
    if (!empty($billing_countries) && $payment_gateway->getPlugin()->collectsBillingInformation()) {
      // Filter out payment methods that don't match the billing countries.
      // @todo Use a query condition once #2822359 is fixed.
      foreach ($payment_methods as $id => $payment_method) {
        $country_code = 'ZZ';
        if ($billing_profile = $payment_method->getBillingProfile()) {
          if (!$billing_profile->get('address')->isEmpty()) {
            $country_code = $billing_profile->address->first()->getCountryCode();
          }
        }

        if (!in_array($country_code, $billing_countries)) {
          unset($payment_methods[$id]);
        }
      }
    }

    return $payment_methods;
  }

  /**
   * {@inheritdoc}
   */
  public function createForCustomer($payment_method_type, $payment_gateway_id, $customer_id, ProfileInterface $billing_profile = NULL) {
    return $this->create([
      'type' => $payment_method_type,
      'payment_gateway' => $payment_gateway_id,
      'uid' => $customer_id,
      'billing_profile' => $billing_profile,
    ]);
  }

}
