<?php

namespace Drupal\commerce_payment\Plugin\Commerce\CheckoutPane;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\PaymentMethodStorageInterface;
use Drupal\commerce_payment\PaymentOption;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsCreatingPaymentMethodsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the payment information pane.
 *
 * Disabling this pane will automatically disable the payment process pane,
 * since they are always used together. Developers subclassing this pane
 * should use hook_commerce_checkout_pane_info_alter(array &$panes) to
 * point $panes['payment_information']['class'] to the new child class.
 */
#[CommerceCheckoutPane(
  id: "payment_information",
  label: new TranslatableMarkup("Payment information"),
  default_step: "order_information",
  wrapper_element: "fieldset",
)]
class PaymentInformation extends PaymentCheckoutPaneBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->currentUser = $container->get('current_user');
    $instance->inlineFormManager = $container->get('plugin.manager.commerce_inline_form');
    $instance->paymentOptionsBuilder = $container->get('commerce_payment.options_builder');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'always_display' => FALSE,
      'require_payment_method' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $summary_parts = [
      empty($this->configuration['require_payment_method'])
        ? $this->t('Customer payment methods will not be stored, if the order balance is zero.')
        : $this->t('Customer payment methods will always be stored, even if order balance is zero.'),
      !empty($this->configuration['always_display'])
        ? $this->t('Always display payment methods.')
        : $this->t('Display payment methods only if more than one method available.'),
    ];

    $parent_summary = parent::buildConfigurationSummary();
    if ($parent_summary) {
      array_unshift($summary_parts, $parent_summary);
    }

    return implode('<br>', $summary_parts);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['always_display'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always display payment methods.'),
      '#description' => $this->t('Display payment methods even if there is only one method available.'),
      '#default_value' => !empty($this->configuration['always_display']),
    ];
    $form['require_payment_method'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Collect payment methods on orders with zero balance'),
      '#default_value' => (int) $this->configuration['require_payment_method'],
      '#description' => $this->t('Note that some modules can override default setting.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['require_payment_method'] = !empty($values['require_payment_method']);
      $this->configuration['always_display'] = !empty($values['always_display']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $billing_profile = $this->order->getBillingProfile();
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $this->order->get('payment_gateway')->entity;
    $summary = [];
    if ($this->collectBillingProfileOnly() || !$payment_gateway) {
      if ($billing_profile) {
        // Only the billing information was collected.
        $view_builder = $this->entityTypeManager->getViewBuilder('profile');
        $summary = [
          '#title' => $this->t('Billing information'),
          'profile' => $view_builder->view($billing_profile, 'default'),
        ];
        return $summary;
      }
    }

    $payment_method = $this->order->get('payment_method')->entity;
    if ($payment_method) {
      $view_builder = $this->entityTypeManager->getViewBuilder('commerce_payment_method');
      $summary = $view_builder->view($payment_method, 'default');
    }
    else {
      if ($payment_gateway) {
        $summary = [
          'payment_gateway' => [
            '#markup' => $payment_gateway->getPlugin()->getDisplayLabel(),
          ],
        ];
      }
      if ($billing_profile) {
        $view_builder = $this->entityTypeManager->getViewBuilder('profile');
        $summary['profile'] = $view_builder->view($billing_profile, 'default');
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    if ($this->collectBillingProfileOnly()) {
      // No payment is needed if we don't require payment method collection,
      // and the order balance is zero. In that case, collect just the billing
      // information.
      $pane_form['#title'] = $this->t('Billing information');
      $pane_form = $this->buildBillingProfileForm($pane_form, $form_state);
      return $pane_form;
    }

    /** @var \Drupal\commerce_payment\PaymentGatewayStorageInterface $payment_gateway_storage */
    $payment_gateway_storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');
    // Load the payment gateways. This fires an event for filtering the
    // available gateways, and then evaluates conditions on all remaining ones.
    $payment_gateways = $payment_gateway_storage->loadMultipleForOrder($this->order);
    // Can't proceed without any payment gateways.
    if (empty($payment_gateways)) {
      $this->messenger()->addError($this->noPaymentGatewayErrorMessage());
      return $pane_form;
    }

    // Core bug #1988968 doesn't allow the payment method add form JS to depend
    // on an external library, so the libraries need to be preloaded here.
    foreach ($payment_gateways as $payment_gateway) {
      foreach ($payment_gateway->getPlugin()->getLibraries() as $library) {
        $pane_form['#attached']['library'][] = $library;
      }
    }

    $options = $this->paymentOptionsBuilder->buildOptions($this->order, $payment_gateways);

    // Can't proceed without any payment gateways.
    if (empty($options)) {
      $this->messenger()->addError($this->noPaymentGatewayErrorMessage());
      return $pane_form;
    }

    $option_labels = array_map(function (PaymentOption $option) {
      return $option->getLabel();
    }, $options);
    $parents = array_merge($pane_form['#parents'], ['payment_method']);
    $default_option_id = NestedArray::getValue($form_state->getUserInput(), $parents);
    if ($default_option_id && isset($options[$default_option_id])) {
      $default_option = $options[$default_option_id];
    }
    else {
      $default_option = $this->paymentOptionsBuilder->selectDefaultOption($this->order, $options);
    }

    $pane_form['#after_build'][] = [get_class($this), 'clearValues'];
    $pane_form['payment_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Payment method'),
      '#options' => $option_labels,
      '#default_value' => $default_option->getId(),
      '#weight' => -10,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefresh'],
        'wrapper' => $pane_form['#id'],
      ],
      '#access' => !empty($this->configuration['always_display']) || (count($options) > 1),
    ];
    // Add a class to each individual radio, to help themers.
    foreach ($options as $option) {
      $class_name = $option->getPaymentMethodId() ? 'stored' : 'new';
      $pane_form['payment_method'][$option->getId()]['#attributes']['class'][] = "payment-method--$class_name";
    }
    // Store the options for submitPaneForm().
    $pane_form['#payment_options'] = $options;

    $default_payment_gateway_id = $default_option->getPaymentGatewayId();
    $payment_gateway = $payment_gateways[$default_payment_gateway_id];
    $payment_gateway_plugin = $payment_gateway->getPlugin();

    // If this is an existing payment method, return the pane form.
    // Editing payment methods at checkout is not supported.
    if ($default_option->getPaymentMethodId()) {
      $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
      /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
      $payment_method = $payment_method_storage->load($default_option->getPaymentMethodId());
      // If the payment method hasn't been tokenized yet, allow updating the
      // billing information.
      if (empty($payment_method->getRemoteId()) && $payment_gateway_plugin->collectsBillingInformation()) {
        $pane_form = $this->buildBillingProfileForm($pane_form, $form_state);
      }
      return $pane_form;
    }

    // If this payment gateway plugin supports creating tokenized payment
    // methods before processing payment, we build the "add-payment-method"
    // plugin form.
    if ($payment_gateway_plugin instanceof SupportsCreatingPaymentMethodsInterface) {
      $pane_form = $this->buildPaymentMethodForm($pane_form, $form_state, $default_option);
    }
    // Check if the billing profile form should be rendered for the payment
    // gateway to collect billing information.
    elseif ($payment_gateway_plugin->collectsBillingInformation()) {
      $pane_form = $this->buildBillingProfileForm($pane_form, $form_state);
    }

    return $pane_form;
  }

  /**
   * Builds the payment method form for the selected payment option.
   *
   * @param array $pane_form
   *   The pane form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   * @param \Drupal\commerce_payment\PaymentOption $payment_option
   *   The payment option.
   *
   * @return array
   *   The modified pane form.
   */
  protected function buildPaymentMethodForm(array $pane_form, FormStateInterface $form_state, PaymentOption $payment_option) {
    if ($payment_option->getPaymentMethodId() && !$payment_option->getPaymentMethodTypeId()) {
      // Editing payment methods at checkout is not supported.
      return $pane_form;
    }
    $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
    assert($payment_method_storage instanceof PaymentMethodStorageInterface);
    $payment_method = $payment_method_storage->createForCustomer(
      $payment_option->getPaymentMethodTypeId(),
      $payment_option->getPaymentGatewayId(),
      $this->order->getCustomerId(),
      $this->order->getBillingProfile()
    );
    $inline_form = $this->inlineFormManager->createInstance('payment_gateway_form', [
      'operation' => 'add-payment-method',
    ], $payment_method);

    $pane_form['add_payment_method'] = [
      '#parents' => array_merge($pane_form['#parents'], ['add_payment_method']),
      '#inline_form' => $inline_form,
      '#weight' => 0,
    ];
    $pane_form['add_payment_method'] = $inline_form->buildInlineForm($pane_form['add_payment_method'], $form_state);

    return $pane_form;
  }

  /**
   * Builds the billing profile form.
   *
   * @param array $pane_form
   *   The pane form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   *
   * @return array
   *   The modified pane form.
   */
  protected function buildBillingProfileForm(array $pane_form, FormStateInterface $form_state) {
    $billing_profile = $this->order->getBillingProfile();
    if (!$billing_profile) {
      $billing_profile = $this->entityTypeManager->getStorage('profile')->create([
        'type' => 'customer',
        'uid' => 0,
      ]);
    }
    $inline_form = $this->inlineFormManager->createInstance('customer_profile', [
      'profile_scope' => 'billing',
      'available_countries' => $this->order->getStore()->getBillingCountries(),
      'address_book_uid' => $this->order->getCustomerId(),
      // Don't copy the profile to address book until the order is placed.
      'copy_on_save' => FALSE,
    ], $billing_profile);

    $pane_form['billing_information'] = [
      '#parents' => array_merge($pane_form['#parents'], ['billing_information']),
      '#inline_form' => $inline_form,
      '#weight' => 10,
    ];
    $pane_form['billing_information'] = $inline_form->buildInlineForm($pane_form['billing_information'], $form_state);

    return $pane_form;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#parents'];
    array_pop($parents);
    return NestedArray::getValue($form, $parents);
  }

  /**
   * Clears dependent form input when the payment_method changes.
   *
   * Without this Drupal considers the rebuilt form to already be submitted,
   * ignoring default values.
   */
  public static function clearValues(array $element, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (!$triggering_element) {
      return $element;
    }
    $triggering_element_name = end($triggering_element['#parents']);
    if ($triggering_element_name == 'payment_method') {
      $user_input = &$form_state->getUserInput();
      $pane_input = NestedArray::getValue($user_input, $element['#parents']);
      unset($pane_input['add_payment_method']);
      NestedArray::setValue($user_input, $element['#parents'], $pane_input);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    if ($this->collectBillingProfileOnly()) {
      return;
    }

    $values = $form_state->getValue($pane_form['#parents']);
    if (!isset($values['payment_method'])) {
      $form_state->setError($complete_form, $this->noPaymentGatewayErrorMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    if (isset($pane_form['billing_information']['#inline_form'])) {
      /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
      $inline_form = $pane_form['billing_information']['#inline_form'];
      /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
      $billing_profile = $inline_form->getEntity();
      $this->order->setBillingProfile($billing_profile);
      // The billing profile is provided either because the order is free,
      // or the selected gateway does not support stored payment methods.
      // If it's the former, stop here.
      if ($this->collectBillingProfileOnly()) {
        return;
      }
    }

    $values = $form_state->getValue($pane_form['#parents']);
    /** @var \Drupal\commerce_payment\PaymentOption $selected_option */
    $selected_option = $pane_form['#payment_options'][$values['payment_method']];
    /** @var \Drupal\commerce_payment\PaymentGatewayStorageInterface $payment_gateway_storage */
    $payment_gateway_storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $payment_gateway_storage->load($selected_option->getPaymentGatewayId());
    if (!$payment_gateway) {
      return;
    }

    $payment_gateway_plugin = $payment_gateway->getPlugin();
    if ($payment_gateway_plugin instanceof SupportsCreatingPaymentMethodsInterface) {
      if (!empty($selected_option->getPaymentMethodTypeId())) {
        /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
        $inline_form = $pane_form['add_payment_method']['#inline_form'];
        // The payment method was just created.
        $payment_method = $inline_form->getEntity();
      }
      else {
        /** @var \Drupal\commerce_payment\PaymentMethodStorageInterface $payment_method_storage */
        $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
        $payment_method = $payment_method_storage->load($selected_option->getPaymentMethodId());
      }

      /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
      $this->order->set('payment_gateway', $payment_method->getPaymentGateway());
      $this->order->set('payment_method', $payment_method);
      // Copy the billing information to the order.
      $payment_method_profile = $payment_method->getBillingProfile();
      if ($payment_method_profile) {
        // If the $billing_profile variable is set, this means the payment
        // method isn't yet tokenized and the billing information was
        // potentially updated, therefore we need to copy the billing
        // information entered to the payment method.
        if (isset($billing_profile)) {
          $payment_method_profile->populateFromProfile($billing_profile);
          // The data field is not copied by default but needs to be.
          // For example, both profiles need to have an address_book_profile_id.
          $payment_method_profile->populateFromProfile($billing_profile, ['data']);
          $payment_method_profile->save();
        }
        else {
          $billing_profile = $this->order->getBillingProfile();
          if (!$billing_profile) {
            $billing_profile = $this->entityTypeManager->getStorage('profile')->create([
              'type' => 'customer',
              'uid' => 0,
            ]);
          }
          $billing_profile->populateFromProfile($payment_method_profile);
          // The data field is not copied by default but needs to be.
          // For example, both profiles need to have an address_book_profile_id.
          $billing_profile->populateFromProfile($payment_method_profile, ['data']);
          $billing_profile->save();
          $this->order->setBillingProfile($billing_profile);
        }
      }
    }
    elseif ($payment_gateway_plugin instanceof SupportsStoredPaymentMethodsInterface) {
      if ($selected_option->getPaymentMethodId()) {
        /** @var \Drupal\commerce_payment\PaymentMethodStorageInterface $payment_method_storage */
        $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
        $payment_method = $payment_method_storage->load($selected_option->getPaymentMethodId());
        assert($payment_method instanceof PaymentMethodInterface);
        /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
        $this->order->set('payment_gateway', $payment_method->getPaymentGateway());
        $this->order->set('payment_method', $payment_method);
        $this->order->setBillingProfile($payment_method->getBillingProfile());
      }
      // The new payment method is created when the transaction processes.
      else {
        $this->order->set('payment_gateway', $payment_gateway);
        $this->order->set('payment_method', NULL);
      }
    }
    else {
      $this->order->set('payment_gateway', $payment_gateway);
      $this->order->set('payment_method', NULL);
    }
  }

  /**
   * Returns an error message in case there are no available payment gateways.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The error message.
   */
  protected function noPaymentGatewayErrorMessage() {
    if ($this->currentUser->hasPermission('administer commerce_payment_gateway')) {
      $message = $this->t('There are no <a href=":url"">payment gateways</a> available for this order.', [
        ':url' => Url::fromRoute('entity.commerce_payment_gateway.collection')->toString(),
      ]);
    }
    else {
      $message = $this->t('There are no payment gateways available for this order. Please try again later.');
    }
    return $message;
  }

}
