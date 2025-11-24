<?php

namespace Drupal\commerce_order\Plugin\Commerce\Condition;

use CommerceGuys\Addressing\Zone\Zone;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;

/**
 * Provides a base class for the billing/shipping address condition for orders.
 */
abstract class CustomerAddressBase extends ConditionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'zone' => NULL,
      'operator' => 'in',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['operator'] = [
      '#type' => 'select',
      '#field_prefix' => $this->t('Address') . ' ',
      '#field_suffix' => ' ' . $this->t('in one of the following territories:'),
      '#options' => [
        'in' => $this->t('must be'),
        'not in' => $this->t('must NOT be'),
      ],
      '#default_value' => $this->configuration['operator'],
    ];

    $form['zone'] = [
      '#type' => 'address_zone',
      '#default_value' => $this->configuration['zone'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);
    // Work around an Address bug where the Remove button value is kept in the array.
    foreach ($values['zone']['territories'] as &$territory) {
      unset($territory['remove']);
    }
    // Don't store the label, it's always hidden and empty.
    unset($values['zone']['label']);

    $this->configuration['zone'] = $values['zone'];
    $this->configuration['operator'] = $values['operator'];
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;

    $profiles = $order->collectProfiles();
    $profile_scope = $this->pluginDefinition['profile_scope'] ?? $this->pluginDefinition['additional']['profile_scope'] ?? 'billing';
    $profile = $profiles[$profile_scope] ?? NULL;
    if (!$profile) {
      // The promotion can't be applied until the address is known.
      return FALSE;
    }
    $zone = new Zone([
      'id' => $profile_scope,
      'label' => 'N/A',
    ] + $this->configuration['zone']);
    $address = $profile->get('address')->first();
    if (!$address) {
      // The conditions can't be applied without address.
      return FALSE;
    }

    if (!empty($this->configuration['operator']) && $this->configuration['operator'] == 'not in') {
      return !$zone->match($address);
    }

    return $zone->match($address);
  }

}
