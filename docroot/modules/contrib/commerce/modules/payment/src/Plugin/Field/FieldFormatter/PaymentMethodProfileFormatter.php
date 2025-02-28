<?php

namespace Drupal\commerce_payment\Plugin\Field\FieldFormatter;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\entity_reference_revisions\Plugin\Field\FieldFormatter\EntityReferenceRevisionsEntityFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_payment_method_profile' formatter.
 *
 * @FieldFormatter(
 *   id = "commerce_payment_method_profile",
 *   label = @Translation("Payment method profile"),
 *   description = @Translation("Displays the billing profile referenced by the payment method."),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
final class PaymentMethodProfileFormatter extends EntityReferenceRevisionsEntityFormatter {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  private $currentRouteMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currentRouteMatch = $container->get('current_route_match');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->currentRouteMatch->getParameter('commerce_order');

    // Defer to the parent method if the order could not be fetched from the
    // current route or if it doesn't reference a payment method.
    if (!$order || $order->get('payment_method')->isEmpty() ||
      !$order->get('payment_method')->entity) {
      return parent::checkAccess($entity);
    }
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $order->get('payment_method')->entity;

    // Allow access if the billing profile belongs to the payment method
    // referenced by the order being viewed.
    return AccessResult::allowedIf($payment_method->getBillingProfile()->id() == $entity->id())
      ->addCacheableDependency($order);
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getTargetEntityTypeId() === 'commerce_payment_method' && $field_definition->getName() === 'billing_profile';
  }

}
