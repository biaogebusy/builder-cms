<?php

namespace Drupal\commerce_payment\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the payment operation form.
 */
class PaymentOperationForm extends EntityForm implements ContainerInjectionInterface {

  use PaymentOrderSummaryFormTrait;

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * The current order being processed.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->inlineFormManager = $container->get('plugin.manager.commerce_inline_form');
    $instance->routeMatch = $container->get('current_route_match');
    $instance->order = $instance->routeMatch->getParameter('commerce_order');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $operations = $payment_gateway_plugin->buildPaymentOperations($payment);
    $operation_id = $this->routeMatch->getRawParameter('operation');
    $operation = $operations[$operation_id];

    $form['#title'] = $operation['page_title'];
    $form['#tree'] = TRUE;
    if ($operation_id !== 'void') {
      $form['#theme'] = ['commerce_admin_payment_form'];
      // Display the order summary above the form.
      $form = $this->buildOrderSummaryForm($form, $this->order);
      // Add Payment details section.
      $form['commerce_payment_details'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Payment details'),
        '#attributes' => ['class' => ['payment-section']],
        '#collapsible' => FALSE,
        '#tree' => FALSE,
      ];
      $form['commerce_payment_details']['payment'] = $this->buildPaymentDetailsForm($form, $form_state, $operation);
    }
    else {
      $form['payment'] = $this->buildPaymentDetailsForm($form, $form_state, $operation);
    }
    // Add actions.
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $operation['title'],
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#attributes' => ['class' => ['button']],
      '#url' => $this->entity->toUrl('collection'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (isset($form['commerce_payment_details'])) {
      $payment_element = $form['commerce_payment_details']['payment'];
    }
    else {
      $payment_element = $form['payment'];
    }
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
    $inline_form = $payment_element['#inline_form'];
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $inline_form->getEntity();

    if (!empty($payment_element['#success_message'])) {
      $this->messenger()->addMessage($payment_element['#success_message']);
    }
    $form_state->setRedirect('entity.commerce_payment.collection', ['commerce_order' => $payment->getOrderId()]);
  }

  /**
   * Builds the "Payment details" section of the payment form.
   *
   * This section allows the user to specify:
   *  - The payment amount to be charged (pre-filled with the order balance).
   *  - The transaction type (authorize-only or authorize-and-capture), when
   * supported.
   *
   * @param array $form
   *   The current form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $operation
   *   The data array of operation.
   *
   * @return array
   *   The updated form structure including the payment details.
   */
  protected function buildPaymentDetailsForm(array $form, FormStateInterface $form_state, array $operation): array {
    $inline_form = $this->inlineFormManager->createInstance('payment_gateway_form', [
      'operation' => $operation['plugin_form'],
    ], $this->entity);
    // Add payment inline form.
    $payment = [
      '#parents' => ['payment'],
      '#inline_form' => $inline_form,
    ];
    return $inline_form->buildInlineForm($payment, $form_state);
  }

}
