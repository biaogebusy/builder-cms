<?php

namespace Drupal\commerce_order\Plugin\Commerce\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce\Attribute\CommerceCondition;
use Drupal\commerce\EntityHelper;
use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;
use Drupal\user\Entity\Role;

/**
 * Provides the customer role condition for orders.
 */
#[CommerceCondition(
  id: "order_customer_role",
  label: new TranslatableMarkup("Customer role"),
  entity_type: "commerce_order",
  category: new TranslatableMarkup("Customer"),
  weight: -1,
)]
class OrderCustomerRole extends ConditionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'roles' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $roles = EntityHelper::extractLabels(Role::loadMultiple());
    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed roles'),
      '#default_value' => $this->configuration['roles'],
      '#options' => array_map('\Drupal\Component\Utility\Html::escape', $roles),
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
    $this->configuration['roles'] = array_filter($values['roles']);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    $customer = $order->getCustomer();

    return (bool) array_intersect($this->configuration['roles'], $customer->getRoles());
  }

}
