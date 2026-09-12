<?php

namespace Drupal\commerce_order\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_order\OrderAssignmentInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for assigning orders to a different customer.
 */
class OrderReassignForm extends FormBase {

  use CustomerFormTrait;

  /**
   * The current order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected OrderInterface $order;

  /**
   * The order assignment service.
   *
   * @var \Drupal\commerce_order\OrderAssignmentInterface
   */
  protected OrderAssignmentInterface $orderAssignment;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->passwordGenerator = $container->get('password_generator');
    $instance->order = $container->get('current_route_match')->getParameter('commerce_order');
    $instance->orderAssignment = $container->get('commerce_order.order_assignment');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_order_reassign_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $customer = $this->order->getCustomer();
    if ($customer->isAnonymous()) {
      $current_customer = $this->t('anonymous user with the email %email', [
        '%email' => $this->order->getEmail(),
      ]);
    }
    else {
      // If the display name has been altered to not be the email address,
      // show the email as well.
      if ($customer->getDisplayName() != $customer->getEmail()) {
        $customer_link_text = $this->t('@display (@email)', [
          '@display' => $customer->getDisplayName(),
          '@email' => $customer->getEmail(),
        ]);
      }
      else {
        $customer_link_text = $customer->getDisplayName();
      }

      $current_customer = $this->order->getCustomer()->toLink($customer_link_text)->toString();
    }

    $form['current_customer'] = [
      '#type' => 'item',
      '#markup' => $this->t('The order is currently assigned to @customer.', [
        '@customer' => $current_customer,
      ]),
    ];
    $form += $this->buildCustomerForm($form, $form_state, $this->order);
    $form['customer']['keep_existing_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Keep existing order email address'),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reassign order'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->validateCustomerForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->submitCustomerForm($form, $form_state);

    $values = $form_state->getValues();
    /** @var \Drupal\user\UserInterface $user */
    $user_storage = $this->entityTypeManager->getStorage('user');
    $user = $user_storage->load($values['uid']);
    if ($values['keep_existing_email']) {
      $this->order->setData('customer_email_overridden', TRUE);
    }
    $this->orderAssignment->assign($this->order, $user);
    $this->messenger()->addMessage($this->t('The %label has been assigned to customer %customer.', [
      '%label' => $this->order->label(),
      '%customer' => $this->order->getCustomer()->label(),
    ]));
    $form_state->setRedirectUrl($this->order->toUrl());
  }

}
