<?php

namespace Drupal\commerce_payment\Form;

use Drupal\commerce\InlineFormManager;
use Drupal\commerce_payment\PaymentOption;
use Drupal\commerce_payment\PaymentOptionsBuilderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides the payment add form.
 */
class PaymentAddForm extends FormBase implements ContainerInjectionInterface {

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
   * The current order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The payment options.
   *
   * @var \Drupal\commerce_payment\PaymentOption[]
   */
  protected $paymentOptions;

  /**
   * Constructs a new PaymentAddForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce\InlineFormManager $inline_form_manager
   *   The inline form manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\commerce_payment\PaymentOptionsBuilderInterface $payment_options_builder
   *   The payment options builder.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, InlineFormManager $inline_form_manager, RouteMatchInterface $route_match, PaymentOptionsBuilderInterface $payment_options_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->inlineFormManager = $inline_form_manager;
    $this->paymentOptionsBuilder = $payment_options_builder;
    $this->order = $route_match->getParameter('commerce_order');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_inline_form'),
      $container->get('current_route_match'),
      $container->get('commerce_payment.options_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_payment_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Prepare the form for ajax.
    $form['#wrapper_id'] = Html::getUniqueId('payment-add-form-wrapper');
    $form['#prefix'] = '<div id="' . $form['#wrapper_id'] . '">';
    $form['#suffix'] = '</div>';
    $form['#tree'] = TRUE;

    $step = $form_state->get('step');
    $step = $step ?: 'payment_gateway';
    $form_state->set('step', $step);
    if ($step == 'payment_gateway') {
      $form = $this->buildPaymentGatewayForm($form, $form_state);
    }
    elseif ($step == 'payment') {
      $form = $this->buildPaymentForm($form, $form_state);
    }

    return $form;
  }

  /**
   * Builds the form for selecting a payment gateway.
   *
   * @param array $form
   *   The parent form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   *
   * @return array
   *   The built form.
   */
  protected function buildPaymentGatewayForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\PaymentGatewayStorageInterface $payment_gateway_storage */
    $payment_gateway_storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');
    $payment_gateways = $payment_gateway_storage->loadMultipleForOrder($this->order);
    // Allow on-site and manual payment gateways.
    $payment_gateways = array_filter($payment_gateways, function ($payment_gateway) {
      /** @var \Drupal\commerce_payment\Entity\PaymentGateway $payment_gateway */
      return $payment_gateway->getPlugin()->hasFormClass('add-payment');
    });
    // @todo Move this check to the access handler.
    if (count($payment_gateways) < 1) {
      throw new AccessDeniedHttpException();
    }

    // Core bug #1988968 doesn't allow the payment method add form JS to depend
    // on an external library, so the libraries need to be preloaded here.
    foreach ($payment_gateways as $payment_gateway) {
      if ($js_library = $payment_gateway->getPlugin()->getJsLibrary()) {
        $form['#attached']['library'][] = $js_library;
      }
    }

    $payment_options = $this->paymentOptionsBuilder->buildOptions($this->order, $payment_gateways);
    // Do not allow admins to add payments to non-reusable payment methods
    // through this form.
    $this->paymentOptions = array_filter($payment_options, function (PaymentOption $payment_option) {
      $order_payment_method = $this->order->get('payment_method')->entity;

      if (!$order_payment_method) {
        return TRUE;
      }

      /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $order_payment_method */
      if ($order_payment_method->id() === $payment_option->getPaymentMethodId()) {
        return $order_payment_method->isReusable();
      }

      return TRUE;
    });
    $option_labels = array_map(function (PaymentOption $option) {
      return $option->getLabel();
    }, $this->paymentOptions);
    $default_option_id = NestedArray::getValue($form_state->getUserInput(), ['payment_option']);
    if ($default_option_id && isset($this->paymentOptions[$default_option_id])) {
      $default_option = $this->paymentOptions[$default_option_id];
    }
    else {
      $default_option = $this->paymentOptionsBuilder->selectDefaultOption($this->order, $this->paymentOptions);
    }

    $form['payment_option'] = [
      '#type' => 'radios',
      '#required' => TRUE,
      '#title' => $this->t('Payment option'),
      '#options' => $option_labels,
      '#default_value' => $default_option->getId(),
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefresh'],
        'wrapper' => $form['#wrapper_id'],
      ],
    ];

    // Add a class to each individual radio, to help themers.
    foreach ($this->paymentOptions as $option) {
      $class_name = $option->getPaymentMethodId() ? 'stored' : 'new';
      $form['payment_option'][$option->getId()]['#attributes']['class'][] = "payment-method--$class_name";
    }

    $default_payment_gateway_id = $default_option->getPaymentGatewayId();
    $payment_gateway = $payment_gateways[$default_payment_gateway_id];
    if ($payment_gateway->getPlugin() instanceof SupportsStoredPaymentMethodsInterface) {
      $form = $this->buildPaymentMethodForm($form, $form_state, $default_option);
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Clears the payment method value when the payment gateway changes.
   *
   * Changing the payment gateway results in a new set of payment methods,
   * causing the submitted value to trigger an "Illegal choice" error, cause
   * it's no longer allowed. Clearing the value causes the element to fallback
   * to the default value, avoiding the error.
   */
  public static function clearValue(array $element, FormStateInterface $form_state) {
    $value = $element['#value'];
    if (!isset($element['#options'][$value])) {
      $element['#value'] = NULL;
      $user_input = &$form_state->getUserInput();
      unset($user_input['payment_option']);
    }
    return $element;
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
   *
   * @return array
   *   The modified form.
   */
  protected function buildPaymentMethodForm(array $form, FormStateInterface $form_state, PaymentOption $payment_option) {
    if ($payment_option->getPaymentMethodId() && !$payment_option->getPaymentMethodTypeId()) {
      // Editing payment methods at checkout is not supported.
      return $form;
    }

    /** @var \Drupal\commerce_payment\PaymentMethodStorageInterface $payment_method_storage */
    $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
    $payment_method = $payment_method_storage->create([
      'type' => $payment_option->getPaymentMethodTypeId(),
      'payment_gateway' => $payment_option->getPaymentGatewayId(),
      'uid' => $this->order->getCustomerId(),
      'billing_profile' => $this->order->getBillingProfile(),
    ]);
    $inline_form = $this->inlineFormManager->createInstance('payment_gateway_form', [
      'operation' => 'add-payment-method',
    ], $payment_method);

    $form['add_payment_method'] = [
      '#parents' => ['add_payment_method'],
    ];
    $form['add_payment_method'] = $inline_form->buildInlineForm($form['add_payment_method'], $form_state);

    return $form;
  }

  /**
   * Builds the form for adding a payment.
   *
   * @param array $form
   *   The parent form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   *
   * @return array
   *   The built form.
   */
  protected function buildPaymentForm(array $form, FormStateInterface $form_state) {
    $values = [
      'order_id' => $this->order->id(),
    ];

    if ($form_state->has('new_payment_method')) {
      /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $new_payment_method */
      $new_payment_method = $form_state->get('new_payment_method');
      $values['payment_method'] = $new_payment_method->id();
      $values['payment_gateway'] = $new_payment_method->getPaymentGatewayId();
    }
    else {
      $selected_payment_option = $form_state->getValue('payment_option');
      /** @var \Drupal\commerce_payment\PaymentOption $payment_option */
      $payment_option = $this->paymentOptions[$selected_payment_option];
      $values['payment_method'] = $payment_option->getPaymentMethodId();
      $values['payment_gateway'] = $payment_option->getPaymentGatewayId();
    }

    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payment = $payment_storage->create($values);
    $inline_form = $this->inlineFormManager->createInstance('payment_gateway_form', [
      'operation' => 'add-payment',
    ], $payment);

    $form['payment'] = [
      '#parents' => ['payment'],
    ];
    $form['payment'] = $inline_form->buildInlineForm($form['payment'], $form_state);
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add payment'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $step = $form_state->get('step');
    if ($step == 'payment_gateway') {
      // Check if a new payment method was created.
      if (isset($form['add_payment_method']['#inline_form'])) {
        /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
        $inline_form = $form['add_payment_method']['#inline_form'];
        $payment_method = $inline_form->getEntity();
        $form_state->set('new_payment_method', $payment_method);
      }
      $form_state->set('step', 'payment');
      $form_state->setRebuild(TRUE);
    }
    elseif ($step == 'payment') {
      // Save payment gateway and method references on order entity.
      /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
      $payment = $form['payment']['#inline_form']->getEntity();
      $order = $payment->getOrder();
      $order->set('payment_gateway', $payment->getPaymentGateway());
      if ($payment->getPaymentMethod()) {
        $payment_method = $payment->getPaymentMethod();
        $order->set('payment_method', $payment_method);

        // Copy the billing information to the order.
        $payment_method_profile = $payment_method->getBillingProfile();
        if ($payment_method_profile) {
          $billing_profile = $order->getBillingProfile();
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
          $order->setBillingProfile($billing_profile);
        }
      }
      $order->save();
      $this->messenger()->addMessage($this->t('Payment saved.'));
      $form_state->setRedirect('entity.commerce_payment.collection', ['commerce_order' => $order->id()]);
    }
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    return $form;
  }

}
