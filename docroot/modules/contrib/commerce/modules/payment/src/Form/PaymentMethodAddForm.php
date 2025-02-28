<?php

namespace Drupal\commerce_payment\Form;

use Drupal\commerce\InlineFormManager;
use Drupal\commerce_payment\PaymentOption;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsCreatingPaymentMethodsInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides the payment method add form.
 */
class PaymentMethodAddForm extends FormBase implements ContainerInjectionInterface {

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
   * Constructs a new PaymentMethodAddForm instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce\InlineFormManager $inline_form_manager
   *   The inline form manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, InlineFormManager $inline_form_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->inlineFormManager = $inline_form_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_inline_form')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_payment_method_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {
    /** @var \Drupal\commerce_payment\PaymentOption[] $payment_options */
    $payment_options = $form_state->get('payment_options');
    if (!$payment_options) {
      $payment_options = $this->buildPaymentOptions($form_state);
      if (!$payment_options) {
        throw new AccessDeniedHttpException();
      }
      $form_state->set('payment_options', $payment_options);
    }
    $payment_gateways = $form_state->get('payment_gateways');

    // Core bug #1988968 doesn't allow the payment method add form JS to depend
    // on an external library, so the libraries need to be preloaded here.
    foreach ($payment_gateways as $payment_gateway) {
      if ($js_library = $payment_gateway->getPlugin()->getJsLibrary()) {
        $form['#attached']['library'][] = $js_library;
      }
    }

    // Prepare the form for ajax.
    $form['#wrapper_id'] = Html::getUniqueId('payment-method-add-form-wrapper');
    $form['#prefix'] = '<div id="' . $form['#wrapper_id'] . '">';
    $form['#suffix'] = '</div>';
    $user_input = $form_state->getUserInput();
    if (!empty($user_input['payment_method']) && isset($payment_options[$user_input['payment_method']])) {
      $default_option = $payment_options[$user_input['payment_method']];
    }
    else {
      $default_option = reset($payment_options);
    }

    $option_labels = array_map(function (PaymentOption $option) {
      return $option->getLabel();
    }, $payment_options);
    $form['#after_build'][] = [get_class($this), 'clearValues'];
    $form['payment_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Payment method'),
      '#options' => $option_labels,
      '#default_value' => $default_option->getId(),
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefresh'],
        'wrapper' => $form['#wrapper_id'],
      ],
      '#access' => count($payment_options) > 1,
    ];
    $form_state->set('payment_gateway', $default_option->getPaymentGatewayId());
    $form_state->set('payment_method_type', $default_option->getPaymentMethodTypeId());
    $form = $this->buildPaymentMethodForm($form, $form_state);
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit_payment_method'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      '#submit' => ['::submitForm'],
    ];

    return $form;
  }

  /**
   * Builds the payment options.
   *
   * This will build the payment options for payment gateways that support
   * creating payment methods.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\commerce_payment\PaymentOption[]
   *   The payment options.
   */
  protected function buildPaymentOptions(FormStateInterface $form_state) {
    $payment_gateway_storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');
    $payment_gateways = $payment_gateway_storage->loadByProperties(['status' => TRUE]);
    $payment_gateways = array_filter($payment_gateways, function ($payment_gateway) {
      return $payment_gateway->getPlugin() instanceof SupportsCreatingPaymentMethodsInterface;
    });

    if (!$payment_gateways) {
      return [];
    }
    $form_state->set('payment_gateways', $payment_gateways);
    $payment_options = [];
    // 3) Add options to create new stored payment methods of supported types.
    $payment_method_type_counts = [];
    // Count how many new payment method options will be built per gateway.
    foreach ($payment_gateways as $payment_gateway) {
      $payment_method_types = $payment_gateway->getPlugin()->getPaymentMethodTypes();

      foreach ($payment_method_types as $payment_method_type_id => $payment_method_type) {
        if (!isset($payment_method_type_counts[$payment_method_type_id])) {
          $payment_method_type_counts[$payment_method_type_id] = 1;
        }
        else {
          $payment_method_type_counts[$payment_method_type_id]++;
        }
      }
    }

    foreach ($payment_gateways as $payment_gateway) {
      $payment_gateway_plugin = $payment_gateway->getPlugin();
      $payment_method_types = $payment_gateway_plugin->getPaymentMethodTypes();

      foreach ($payment_method_types as $payment_method_type_id => $payment_method_type) {
        $option_id = 'new--' . $payment_method_type_id . '--' . $payment_gateway->id();
        $option_label = $payment_method_type->getCreateLabel();
        // If there is more than one option for this payment method type,
        // append the payment gateway label to avoid duplicate option labels.
        if ($payment_method_type_counts[$payment_method_type_id] > 1) {
          $option_label = $this->t('@payment_method_label (@payment_gateway_label)', [
            '@payment_method_label' => $payment_method_type->getCreateLabel(),
            '@payment_gateway_label' => $payment_gateway_plugin->getDisplayLabel(),
          ]);
        }

        $payment_options[$option_id] = new PaymentOption([
          'id' => $option_id,
          'label' => $option_label,
          'payment_gateway_id' => $payment_gateway->id(),
          'payment_method_type_id' => $payment_method_type_id,
        ]);
      }
    }

    return $payment_options;
  }

  /**
   * Builds the form for adding a payment method.
   *
   * @param array $form
   *   The parent form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   *
   * @return array
   *   The built form.
   */
  protected function buildPaymentMethodForm(array $form, FormStateInterface $form_state) {
    $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
    $payment_method = $payment_method_storage->create([
      'type' => $form_state->get('payment_method_type'),
      'payment_gateway' => $form_state->get('payment_gateway'),
      'uid' => $form_state->getBuildInfo()['args'][0]->id(),
    ]);
    $inline_form = $this->inlineFormManager->createInstance('payment_gateway_form', [
      'operation' => 'add-payment-method',
    ], $payment_method);

    $form['add_payment_method'] = [
      '#parents' => ['add_payment_method'],
      '#inline_form' => $inline_form,
    ];
    $form['add_payment_method'] = $inline_form->buildInlineForm($form['add_payment_method'], $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
    $inline_form = $form['add_payment_method']['#inline_form'];
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $inline_form->getEntity();
    $this->messenger()->addMessage($this->t('%label saved to your payment methods.', ['%label' => $payment_method->label()]));
    $form_state->setRedirect('entity.commerce_payment_method.collection', ['user' => $payment_method->getOwnerId()]);
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
      $form_input = NestedArray::getValue($user_input, $element['#parents']);
      unset($form_input['billing_information']);
      unset($form_input['add_payment_method']);
      NestedArray::setValue($user_input, $element['#parents'], $form_input);
    }

    return $element;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    return $form;
  }

}
