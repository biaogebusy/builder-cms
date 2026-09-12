<?php

namespace Drupal\commerce_payment\Form;

use Drupal\commerce\AjaxFormTrait;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentOption;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\ManualPaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the unified merchant-facing payment form.
 *
 * This form replaces the legacy PaymentAddForm and aims to present the
 * workflow more naturally from the merchant's perspective.
 *
 * - Displays order items and a full order summary.
 * - Indicates total paid and remaining balance.
 * - Allows selecting or entering a payment method.
 * - Supports new and existing payment methods.
 * - Works inline with gateways that support off-site or on-site capture.
 *
 * @see \Drupal\commerce_payment\Form\PaymentAddForm
 */
class OrderPaymentAddForm extends FormBase implements ContainerInjectionInterface {

  use AjaxFormTrait;
  use PaymentOrderSummaryFormTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * The payment options builder.
   *
   * @var \Drupal\commerce_payment\PaymentOptionsBuilderInterface
   */
  protected $paymentOptionsBuilder;

  /**
   * The current order being processed.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The available payment options.
   *
   * @var \Drupal\commerce_payment\PaymentOption[]
   */
  protected $paymentOptions = [];

  /**
   * The applicable gateways for the current order.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface[]
   */
  protected $paymentGateways = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->inlineFormManager = $container->get('plugin.manager.commerce_inline_form');
    $instance->paymentOptionsBuilder = $container->get('commerce_payment.options_builder');
    $route_match = $container->get('current_route_match');
    $instance->order = $route_match->getParameter('commerce_order');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'commerce_payment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Gets the selected payment option from user input or fallback default.
    $selected_payment_option = $this->getSelectedPaymentOption($form_state);
    // Get the selected payment gateway from the selected option.
    $selected_payment_gateway_id = $selected_payment_option->getPaymentGatewayId();
    $selected_payment_gateway = $this->getPaymentGatewayById($selected_payment_gateway_id);
    // Workaround for core bug #2897377.
    $form['#theme'] = ['commerce_admin_payment_form'];
    $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
    $form['#tree'] = TRUE;
    // Display the order summary above the form.
    $form = $this->buildOrderSummaryForm($form, $this->order);
    // Add amount + transaction type fields.
    $form = $this->buildPaymentDetailsForm($form, $form_state, $selected_payment_gateway);
    // Add the Payment Method section.
    $form = $this->buildPaymentGatewayForm($form, $form_state, $selected_payment_option, $selected_payment_gateway);
    // Attaches necessary JavaScript libraries for the gateways.
    $form = $this->attachGatewayLibraries($form, $form_state);
    $form['actions'] = $this->buildActions($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // 1. Determine the selected payment method and gateway.
    if (isset($form['commerce_payment_details']['add_payment_method']['#inline_form'])) {
      // If a new payment method was added via inline form.
      /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
      $inline_form = $form['commerce_payment_details']['add_payment_method']['#inline_form'];
      $payment_method = $inline_form->getEntity();
      $payment_method_id = $payment_method->id();
      $payment_gateway_id = $payment_method->getPaymentGatewayId();
    }
    else {
      // Otherwise, use the existing reusable payment method selected via radio
      // options.
      $selected_payment_option = $form_state->getValue('payment_option');
      $payment_option = $this->getPaymentOptionById($selected_payment_option);
      $payment_method_id = $payment_option->getPaymentMethodId();
      $payment_gateway_id = $payment_option->getPaymentGatewayId();
    }
    // 2. Set the payment values and type of transaction.
    $values = [
      'order_id' => $this->order->id(),
      'payment_method' => $payment_method_id,
      'payment_gateway' => $payment_gateway_id,
      'amount' => $form_state->getValue('amount'),
    ];
    // 3. Create the payment entity.
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment_storage */
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payment = $payment_storage->create($values);
    // 4. Process the payment using the gateway plugin.
    $payment_gateway = $this->getPaymentGatewayById($payment_gateway_id);
    $payment_gateway_plugin = $payment_gateway->getPlugin();
    try {
      if ($payment_gateway_plugin instanceof ManualPaymentGatewayInterface) {
        $received = (bool) $form_state->getValue('payment_received');
        $payment_gateway_plugin->createPayment($payment, $received);
      }
      else {
        $capture = ($form_state->getValue('transaction_type') === 'capture');
        $payment_gateway_plugin->createPayment($payment, $capture);
      }
    }
    catch (DeclineException $e) {
      $this->messenger()->addError($e->getMessage());
      $form_state->setRebuild();
      return;
    }
    catch (PaymentGatewayException $e) {
      $this->logger('commerce_payment')->error($e->getMessage());
      $this->messenger()->addError($this->t('We encountered an error processing your payment. Please try again or use a different payment method.'));
      $form_state->setRebuild();
      return;
    }
    // 5. Save payment gateway and method references on order entity.
    $this->updateOrderWithPaymentData($payment);
    // 6. Show confirmation and redirect user to the payment collection page.
    $this->messenger()->addMessage($this->t('Payment saved.'));
    $form_state->setRedirect('entity.commerce_payment.collection', ['commerce_order' => $this->order->id()]);
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
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $selected_payment_gateway
   *   The payment gateway selected by the user.
   *
   * @return array
   *   The updated form structure including the payment details.
   */
  protected function buildPaymentDetailsForm(array $form, FormStateInterface $form_state, PaymentGatewayInterface $selected_payment_gateway): array {
    // Add Payment details section.
    $form['commerce_payment_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Payment details'),
      '#attributes' => ['class' => ['payment-section']],
      '#tree' => FALSE,
    ];
    // Add the transaction type.
    $default_transaction_type = 'capture';
    $form['commerce_payment_details']['transaction_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Transaction type'),
      '#title_display' => 'invisible',
      '#options' => [
        'authorize' => $this->t('Authorize only'),
        'capture' => $this->t('Authorize and capture'),
      ],
      '#default_value' => $default_transaction_type,
      '#access' => $selected_payment_gateway->getPlugin() instanceof SupportsAuthorizationsInterface,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefreshForm'],
      ],
    ];
    // The payment amount should not exceed the remaining order balance.
    $balance = $this->order->getBalance();
    $amount = $balance->isPositive() ? $balance : $balance->multiply(0);
    $default_transaction_amount = $amount->toArray();
    $form['commerce_payment_details']['amount'] = [
      '#type' => 'commerce_price',
      '#title' => $this->t('Amount'),
      '#default_value' => $amount->toArray(),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefreshForm'],
        'event' => 'change',
        'disable-refocus' => TRUE,
      ],
    ];
    $form['commerce_payment_details']['payment_received'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mark as received'),
      '#default_value' => FALSE,
      '#access' => $selected_payment_gateway->getPlugin() instanceof ManualPaymentGatewayInterface,
    ];
    // Persist merchant selections into form state for downstream use.
    $transaction_type = $form_state->getValue('transaction_type', $default_transaction_type);
    $form_state->set('transaction_type', $transaction_type);
    $transaction_amount = $form_state->getValue('amount', $default_transaction_amount);
    $form_state->set('amount', $transaction_amount);

    return $form;
  }

  /**
   * Builds the form for selecting a payment gateway.
   *
   * Displays available payment options as radios, attaches required libraries
   * for gateway plugins, and sets up AJAX for dynamic updates.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\commerce_payment\PaymentOption $selected_payment_option
   *   The selected payment option.
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $selected_payment_gateway
   *   The payment gateway selected by the user.
   *
   * @return array
   *   The updated form array.
   */
  protected function buildPaymentGatewayForm(array $form, FormStateInterface $form_state, PaymentOption $selected_payment_option, PaymentGatewayInterface $selected_payment_gateway): array {
    $form['#after_build'][] = [get_class($this), 'clearValues'];
    // Render radio buttons for available payment options.
    $form['commerce_payment_details']['payment_option'] = [
      '#type' => 'radios',
      '#title' => $this->t('Payment method'),
      '#required' => TRUE,
      '#options' => $this->buildPaymentOptionLabels(),
      '#default_value' => $selected_payment_option->getId(),
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefreshForm'],
      ],
    ];
    // Add CSS classes for theming each radio option.
    foreach ($this->getPaymentOptions() as $option) {
      $class_name = $option->getPaymentMethodId() ? 'stored' : 'new';
      $form['commerce_payment_details']['payment_option'][$option->getId()]['#attributes']['class'][] = "payment-method--$class_name";
    }
    if ($selected_payment_gateway->getPlugin() instanceof SupportsStoredPaymentMethodsInterface) {
      $form = $this->buildPaymentMethodForm($form, $form_state, $selected_payment_option, $selected_payment_gateway);
    }
    return $form;
  }

  /**
   * Builds the payment method form for the selected payment option.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the form.
   * @param \Drupal\commerce_payment\PaymentOption $payment_option
   *   The payment option.
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $selected_payment_gateway
   *   The payment gateway selected by the user.
   *
   * @return array
   *   The modified form.
   */
  protected function buildPaymentMethodForm(array $form, FormStateInterface $form_state, PaymentOption $payment_option, PaymentGatewayInterface $selected_payment_gateway): array {
    if ($payment_option->getPaymentMethodId()) {
      // Editing payment methods at checkout is not supported.
      return $form;
    }
    // Create a new payment method entity for the form.
    /** @var \Drupal\commerce_payment\PaymentMethodStorageInterface $payment_method_storage */
    $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
    $payment_method = $payment_method_storage->create([
      'type' => $payment_option->getPaymentMethodTypeId() ?? $selected_payment_gateway->getPlugin()->getDefaultPaymentMethodType()->getPluginId(),
      'payment_gateway' => $payment_option->getPaymentGatewayId(),
      'uid' => $this->order->getCustomerId(),
      'billing_profile' => $this->order->getBillingProfile(),
    ]);
    // Build the inline form for adding the payment method.
    $inline_form = $this->inlineFormManager->createInstance('payment_gateway_form', [
      'operation' => 'add-payment-method',
    ], $payment_method);
    $form['commerce_payment_details']['add_payment_method'] = [
      '#parents' => ['add_payment_method'],
    ];
    $form['commerce_payment_details']['add_payment_method'] = $inline_form->buildInlineForm($form['commerce_payment_details']['add_payment_method'], $form_state);
    return $form;
  }

  /**
   * Returns an array of supported actions for the form.
   *
   * @param array $form
   *   The complete form structure at the time of rendering actions.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   An 'actions' render array containing one or more action buttons.
   */
  protected function buildActions(array $form, FormStateInterface $form_state): array {
    $actions = [
      '#type' => 'actions',
    ];
    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add payment'),
      '#button_type' => 'primary',
    ];
    $actions['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#attributes' => ['class' => ['button']],
      '#url' => Url::fromRoute('entity.commerce_payment.collection', [
        'commerce_order' => $this->order->id(),
      ]),
    ];
    return $actions;
  }

  /**
   * Checks access based on the availability of payment gateways for the order.
   *
   * Prevents access to the form when no applicable payment gateways exist.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(RouteMatchInterface $route_match): AccessResultInterface {
    $this->order = $route_match->getParameter('commerce_order');
    // Get all the list of applicable gateways for the current order.
    $available_gateways = $this->getPaymentGateways();
    // Prepare the access result.
    $access_result = AccessResult::allowedIf(count($available_gateways) > 0);
    // Add cacheable dependency.
    $access_result->addCacheableDependency($this->order);
    // Add verbose message to help admins understand what’s missing.
    if (!$access_result->isAllowed()) {
      $access_result->setReason('The Add Payment form is not accessible because no payment gateways are currently available for this order');
    }
    // Return the access result.
    return $access_result;
  }

  /**
   * Gets a payment gateway object by its ID.
   *
   * @param string $payment_gateway_id
   *   The payment gateway ID.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentGatewayInterface
   *   The payment gateway.
   *
   * @throws \InvalidArgumentException
   *   Thrown if no applicable payment gateway with the given ID is found.
   */
  protected function getPaymentGatewayById(string $payment_gateway_id): PaymentGatewayInterface {
    $gateways = $this->getPaymentGateways();
    if (!isset($gateways[$payment_gateway_id])) {
      throw new \InvalidArgumentException("Payment gateway '$payment_gateway_id' not found.");
    }
    return $gateways[$payment_gateway_id];
  }

  /**
   * Returns the applicable payment gateways for the current order.
   *
   * Uses lazy loading and caches the result.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentGatewayInterface[]
   *   The applicable payment gateways.
   */
  protected function getPaymentGateways(): array {
    if (!$this->paymentGateways) {
      $this->paymentGateways = $this->loadApplicablePaymentGateways();
    }
    return $this->paymentGateways;
  }

  /**
   * Loads all applicable payment gateways for the current order.
   *
   * Only gateways that provide an 'add-payment' form are considered.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentGatewayInterface[]
   *   The filtered list of payment gateways.
   */
  protected function loadApplicablePaymentGateways(): array {
    /** @var \Drupal\commerce_payment\PaymentGatewayStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');
    $gateways = $storage->loadMultipleForOrder($this->order);
    return array_filter($gateways, function ($gateway) {
      return $gateway->getPlugin()->hasFormClass('add-payment');
    });
  }

  /**
   * Gets a payment option by its ID.
   *
   * @param string $payment_option_id
   *   The payment option ID.
   *
   * @return \Drupal\commerce_payment\PaymentOption
   *   The payment option object.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the payment option ID is not found.
   */
  protected function getPaymentOptionById(string $payment_option_id): PaymentOption {
    $payment_options = $this->getPaymentOptions();
    if (!isset($payment_options[$payment_option_id])) {
      throw new \InvalidArgumentException("Payment option '$payment_option_id' not found.");
    }
    return $payment_options[$payment_option_id];
  }

  /**
   * Returns the applicable reusable payment options for the current order.
   *
   * Uses lazy loading and caches the result.
   *
   * @return \Drupal\commerce_payment\PaymentOption[]
   *   The filtered list of payment options.
   */
  protected function getPaymentOptions(): array {
    if (!$this->paymentOptions) {
      $this->paymentOptions = $this->loadApplicableReusablePaymentOptions();
    }
    return $this->paymentOptions;
  }

  /**
   * Loads the applicable, reusable payment options for the current order.
   *
   * This ensures that stored payment methods are only offered again if marked
   * reusable.
   *
   * @return \Drupal\commerce_payment\PaymentOption[]
   *   A list of filtered, reusable payment options.
   */
  protected function loadApplicableReusablePaymentOptions(): array {
    // Get all the list of applicable gateways for the current order.
    $payment_gateways = $this->getPaymentGateways();
    // Build the initial list of payment options from the gateways.
    $payment_options = $this->paymentOptionsBuilder->buildOptions($this->order, $payment_gateways);
    // Get the current payment method attached to the order, if any.
    $order_payment_method = $this->order->get('payment_method')->entity;
    // Filter out non-reusable payment methods already in use by the order.
    $filtered_options = array_filter($payment_options, function (PaymentOption $option) use ($order_payment_method) {
      if (!$order_payment_method) {
        // No existing payment method on the order; allow all options.
        return TRUE;
      }
      if ($order_payment_method->id() === $option->getPaymentMethodId()) {
        // Allow only if the method is marked reusable.
        return $order_payment_method->isReusable();
      }
      return TRUE;
    });
    // Result the reusable list of payment options.
    return $filtered_options;
  }

  /**
   * Gets the selected payment option from user input or fallback default.
   *
   * If the user selected an option manually, that one is returned.
   * Otherwise, falls back to the default recommended option.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\commerce_payment\PaymentOption
   *   The selected payment option.
   */
  protected function getSelectedPaymentOption(FormStateInterface $form_state): PaymentOption {
    // Get all the applicable, reusable payment options for the current order.
    $payment_options = $this->getPaymentOptions();
    // Find the selected payment option from user input.
    $user_input = NestedArray::getValue($form_state->getUserInput(), ['payment_option']);
    if ($user_input && isset($payment_options[$user_input])) {
      return $payment_options[$user_input];
    }
    // Fallback default.
    return $this->paymentOptionsBuilder->selectDefaultOption($this->order, $payment_options);
  }

  /**
   * Builds the label list for the payment method radio options.
   *
   * @return array
   *   A list of radio labels keyed by payment option ID.
   */
  protected function buildPaymentOptionLabels(): array {
    return array_map(function (PaymentOption $option) {
      return $option->getLabel();
    }, $this->getPaymentOptions());
  }

  /**
   * Attaches JavaScript libraries required by the selected payment gateways.
   *
   * Core bug #1988968 doesn't allow the payment method add form JS to depend
   * on an external library, so the libraries need to be preloaded here.
   *
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The updated form array with libraries attached.
   */
  protected function attachGatewayLibraries(array $form, FormStateInterface $form_state): array {
    foreach ($this->getPaymentGateways() as $gateway) {
      foreach ($gateway->getPlugin()->getLibraries() as $library) {
        $form['#attached']['library'][] = $library;
      }
    }

    return $form;
  }

  /**
   * Updates the order with references and billing info from the payment.
   *
   * - Sets the payment gateway and method on the order.
   * - If a billing profile exists on the payment method, it copies it to the
   *   order. Including the 'data' field (e.g. address book ID), which is not
   *   copied by default.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment entity used to update the order.
   */
  protected function updateOrderWithPaymentData(PaymentInterface $payment): void {
    $order = $payment->getOrder();
    $order->set('payment_gateway', $payment->getPaymentGateway());
    $payment_method = $payment->getPaymentMethod();
    if ($payment_method) {
      $order->set('payment_method', $payment_method);
      // Copy the billing information to the order.
      $payment_method_profile = $payment_method->getBillingProfile();
      if ($payment_method_profile) {
        $billing_profile = $order->getBillingProfile();
        if (!$billing_profile) {
          $billing_profile = $this->entityTypeManager->getStorage('profile')
            ->create([
              'type' => 'customer',
              'uid' => 0,
            ]);
        }
        $billing_profile->populateFromProfile($payment_method_profile);
        // The data field is not copied by default but needs to be.
        // For example, both profiles need to have an address_book_profile_id.
        $billing_profile->populateFromProfile($payment_method_profile, ['data']);
        $billing_profile->save();
        $order->setBillingProfile($billing_profile);
      }
    }
    $order->save();
  }

  /**
   * Clears dependent form input when the payment_method changes.
   *
   * Without this Drupal considers the rebuilt form to already be submitted,
   * ignoring default values.
   */
  public static function clearValues(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (!$triggering_element) {
      return $form;
    }
    $triggering_element_name = end($triggering_element['#parents']);
    if ($triggering_element_name === 'payment_option') {
      $user_input = &$form_state->getUserInput();
      unset($user_input['add_payment_method']);
    }

    return $form;
  }

}
