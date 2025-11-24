<?php

namespace Drupal\commerce_payment\Plugin\Commerce\PaymentGateway;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginWithFormsTrait;
use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_price\Price;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the base class for payment gateways.
 */
abstract class PaymentGatewayBase extends PluginBase implements PaymentGatewayInterface, ContainerFactoryPluginInterface {

  use PluginWithFormsTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The parent config entity.
   *
   * Not available while the plugin is being configured.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface
   */
  protected $parentEntity;

  /**
   * The ID of the parent entity (used for serialization).
   *
   * @var string|int|null
   */
  // phpcs:ignore Drupal.Classes.PropertyDeclaration, Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing, PSR2.Classes.PropertyDeclaration.Underscore
  protected $_parentEntityId;

  /**
   * The payment type used by the gateway.
   *
   * @var \Drupal\commerce_payment\Plugin\Commerce\PaymentType\PaymentTypeInterface
   */
  protected $paymentType;

  /**
   * The payment method types handled by the gateway.
   *
   * @var \Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeInterface[]
   */
  protected $paymentMethodTypes = [];

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The minor units converter.
   *
   * @var \Drupal\commerce_price\MinorUnitsConverterInterface
   */
  protected $minorUnitsConverter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    /** @var \Drupal\commerce_payment\PaymentTypeManager $payment_type_manager */
    $payment_type_manager = $container->get('plugin.manager.commerce_payment_type');
    /** @var \Drupal\commerce_payment\PaymentMethodTypeManager $payment_method_type_manager */
    $payment_method_type_manager = $container->get('plugin.manager.commerce_payment_method_type');
    $instance->time = $container->get('datetime.time');
    $instance->minorUnitsConverter = $container->get('commerce_price.minor_units_converter');

    if (array_key_exists('_entity', $configuration)) {
      $instance->parentEntity = $configuration['_entity'];
      unset($configuration['_entity']);
    }
    $instance->paymentType = $payment_type_manager->createInstance($plugin_definition['payment_type']);
    foreach ($plugin_definition['payment_method_types'] as $plugin_id) {
      $instance->paymentMethodTypes[$plugin_id] = $payment_method_type_manager->createInstance($plugin_id);
    }
    $instance->pluginDefinition['forms'] += $instance->getDefaultForms();
    $instance->setConfiguration($configuration);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep(): array {
    if (!empty($this->parentEntity)) {
      $this->_parentEntityId = $this->parentEntity->id();
      $this->parentEntity = NULL;
    }

    return parent::__sleep();
  }

  /**
   * {@inheritdoc}
   */
  public function __wakeup(): void {
    parent::__wakeup();

    if (!empty($this->_parentEntityId)) {
      $payment_gateway_storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');
      $this->parentEntity = $payment_gateway_storage->load($this->_parentEntityId);
      $this->_parentEntityId = NULL;
    }
  }

  /**
   * Gets the default payment gateway forms.
   *
   * @return array
   *   A list of plugin form classes keyed by operation.
   */
  protected function getDefaultForms() {
    $default_forms = [];
    if ($this instanceof SupportsStoredPaymentMethodsInterface) {
      $default_forms['add-payment-method'] = 'Drupal\commerce_payment\PluginForm\PaymentMethodAddForm';
    }
    if ($this instanceof SupportsUpdatingStoredPaymentMethodsInterface) {
      $default_forms['edit-payment-method'] = 'Drupal\commerce_payment\PluginForm\PaymentMethodEditForm';
    }
    if ($this instanceof SupportsAuthorizationsInterface) {
      $default_forms['capture-payment'] = 'Drupal\commerce_payment\PluginForm\PaymentCaptureForm';
    }
    if ($this instanceof SupportsVoidsInterface) {
      $default_forms['void-payment'] = 'Drupal\commerce_payment\PluginForm\PaymentVoidForm';
    }
    if ($this instanceof SupportsRefundsInterface) {
      $default_forms['refund-payment'] = 'Drupal\commerce_payment\PluginForm\PaymentRefundForm';
    }

    return $default_forms;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel() {
    return $this->configuration['display_label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getMode() {
    return $this->configuration['mode'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedModes() {
    // If modes are not explicitly set on the payment gateway plugin, supported
    // modes will default to test and live in the payment gateway annotation.
    // @see \Drupal\commerce_payment\Annotation\CommercePaymentGateway
    return $this->pluginDefinition['modes'];
  }

  /**
   * {@inheritdoc}
   */
  public function getJsLibrary() {
    $js_library = NULL;
    if (!empty($this->pluginDefinition['js_library'])) {
      $js_library = $this->pluginDefinition['js_library'];
    }
    return $js_library;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(): array {
    $libraries = $this->pluginDefinition['libraries'];
    if (!empty($this->pluginDefinition['js_library'])) {
      @trigger_error('\Drupal\commerce_payment\Attribute\CommercePaymentGateway::jsLibrary has been deprecated in favor of \Drupal\commerce_payment\Attribute\CommercePaymentGateway::libraries. Use that instead.');
      $libraries[] = $this->pluginDefinition['js_library'];
      // Remove duplication if occurs.
      $libraries = array_unique($libraries);
    }
    return $libraries;
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentType() {
    return $this->paymentType;
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentMethodTypes() {
    // Filter out payment method types disabled by the merchant.
    return array_intersect_key($this->paymentMethodTypes, array_flip($this->configuration['payment_method_types']));
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultPaymentMethodType() {
    $default_payment_method_type = $this->pluginDefinition['default_payment_method_type'];
    if (!isset($this->paymentMethodTypes[$default_payment_method_type])) {
      throw new \InvalidArgumentException('Invalid default_payment_method_type specified.');
    }
    return $this->paymentMethodTypes[$default_payment_method_type];
  }

  /**
   * {@inheritdoc}
   */
  public function getCreditCardTypes() {
    // @todo Allow the list to be restricted by the merchant.
    return array_intersect_key(CreditCard::getTypes(), array_flip($this->pluginDefinition['credit_card_types']));
  }

  /**
   * {@inheritdoc}
   */
  public function collectsBillingInformation() {
    return $this->configuration['collect_billing_information'];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
    // Providing a default for payment_method_types in defaultConfiguration()
    // doesn't work because NestedArray::mergeDeep causes duplicates.
    if (empty($this->configuration['payment_method_types'])) {
      $this->configuration['payment_method_types'][] = 'credit_card';
    }
    else {
      // Remove any duplicates caused by NestedArray::mergeDeep.
      $this->configuration['payment_method_types'] = array_unique($this->configuration['payment_method_types']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $modes = array_keys($this->getSupportedModes());

    return [
      'display_label' => $this->pluginDefinition['display_label'],
      'mode' => $modes ? reset($modes) : '',
      'payment_method_types' => [],
      'collect_billing_information' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $modes = $this->getSupportedModes();
    $payment_method_types = array_map(function ($payment_method_type) {
      return $payment_method_type->getLabel();
    }, $this->paymentMethodTypes);

    $form['display_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display name'),
      '#description' => $this->t('Shown to customers during checkout.'),
      '#default_value' => $this->configuration['display_label'],
      '#required' => TRUE,
    ];

    if (count($modes) > 1) {
      $form['mode'] = [
        '#type' => 'radios',
        '#title' => $this->t('Mode'),
        '#options' => $modes,
        '#default_value' => $this->configuration['mode'],
        '#required' => TRUE,
      ];
    }
    else {
      $mode_names = array_keys($modes);
      $form['mode'] = [
        '#type' => 'value',
        '#value' => reset($mode_names),
      ];
    }

    if (count($payment_method_types) > 1) {
      $form['payment_method_types'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Payment method types'),
        '#options' => $payment_method_types,
        '#default_value' => $this->configuration['payment_method_types'],
        '#required' => TRUE,
      ];
    }
    else {
      $form['payment_method_types'] = [
        '#type' => 'value',
        '#value' => $payment_method_types,
      ];
    }

    $form['collect_billing_information'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Collect billing information'),
      '#description' => $this->t('Before disabling, make sure you are not legally required to collect billing information.'),
      '#default_value' => $this->configuration['collect_billing_information'],
      // Merchants can disable collecting billing information only if the
      // payment gateway indicated that it doesn't require it.
      '#access' => !$this->pluginDefinition['requires_billing_information'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $values['payment_method_types'] = array_filter($values['payment_method_types']);

      $this->configuration = [];
      $this->configuration['display_label'] = $values['display_label'];
      $this->configuration['mode'] = $values['mode'];
      $this->configuration['payment_method_types'] = array_keys($values['payment_method_types']);
      $this->configuration['collect_billing_information'] = $values['collect_billing_information'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaymentOperations(PaymentInterface $payment) {
    $operations = [];
    if ($this instanceof SupportsAuthorizationsInterface) {
      $operations['capture'] = [
        'title' => $this->t('Capture'),
        'page_title' => $this->t('Capture payment'),
        'plugin_form' => 'capture-payment',
        'access' => $this->canCapturePayment($payment),
      ];
    }
    if ($this instanceof SupportsVoidsInterface) {
      $operations['void'] = [
        'title' => $this->t('Void'),
        'page_title' => $this->t('Void payment'),
        'plugin_form' => 'void-payment',
        'access' => $this->canVoidPayment($payment),
      ];
    }
    if ($this instanceof SupportsRefundsInterface) {
      $operations['refund'] = [
        'title' => $this->t('Refund'),
        'page_title' => $this->t('Refund payment'),
        'plugin_form' => 'refund-payment',
        'access' => $this->canRefundPayment($payment),
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function canCapturePayment(PaymentInterface $payment) {
    return $payment->getState()->getId() === 'authorization';
  }

  /**
   * {@inheritdoc}
   */
  public function canRefundPayment(PaymentInterface $payment) {
    return in_array($payment->getState()->getId(), ['completed', 'partially_refunded'], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function canVoidPayment(PaymentInterface $payment) {
    return $payment->getState()->getId() === 'authorization';
  }

  /**
   * {@inheritdoc}
   */
  public function buildAvsResponseCodeLabel($avs_response_code, $card_type) {
    $avs_code_meanings = CreditCard::getAvsResponseCodeMeanings();
    if (!isset($avs_code_meanings[$card_type][$avs_response_code])) {
      return NULL;
    }
    return $avs_code_meanings[$card_type][$avs_response_code];
  }

  /**
   * {@inheritDoc}
   */
  public function getRemoteCustomerId(UserInterface $account) {
    $remote_id = NULL;
    if (!$account->isAnonymous()) {
      $provider = $this->parentEntity->id() . '|' . $this->getMode();
      /** @var \Drupal\commerce\Plugin\Field\FieldType\RemoteIdFieldItemListInterface $remote_ids */
      $remote_ids = $account->get('commerce_remote_id');
      $remote_id = $remote_ids->getByProvider($provider);
      // Gateways used to key customer IDs by module name, migrate that data.
      if (!$remote_id) {
        $remote_id = $remote_ids->getByProvider($this->pluginDefinition['provider']);
        if ($remote_id) {
          $remote_ids->setByProvider($this->pluginDefinition['provider'], NULL);
          $remote_ids->setByProvider($provider, $remote_id);
          $account->save();
        }
      }
    }

    return $remote_id;
  }

  /**
   * Sets the remote customer ID for the given user.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   * @param string $remote_id
   *   The remote customer ID.
   */
  protected function setRemoteCustomerId(UserInterface $account, $remote_id) {
    if (!$account->isAnonymous()) {
      /** @var \Drupal\commerce\Plugin\Field\FieldType\RemoteIdFieldItemListInterface $remote_ids */
      $remote_ids = $account->get('commerce_remote_id');
      $remote_ids->setByProvider($this->parentEntity->id() . '|' . $this->getMode(), $remote_id);
    }
  }

  /**
   * Asserts that the payment state matches one of the allowed states.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   * @param string[] $states
   *   The allowed states.
   *
   * @throws \InvalidArgumentException
   *   Thrown if the payment state does not match the allowed states.
   */
  protected function assertPaymentState(PaymentInterface $payment, array $states) {
    $state = $payment->getState()->getId();
    if (!in_array($state, $states)) {
      throw new \InvalidArgumentException(sprintf('The provided payment is in an invalid state ("%s").', $state));
    }
  }

  /**
   * Asserts that the payment method is neither empty nor expired.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the payment method is empty.
   * @throws \Drupal\commerce_payment\Exception\HardDeclineException
   *   Thrown when the payment method has expired.
   */
  protected function assertPaymentMethod(?PaymentMethodInterface $payment_method = NULL) {
    if (empty($payment_method)) {
      throw new \InvalidArgumentException('The provided payment has no payment method referenced.');
    }
    if ($payment_method->isExpired()) {
      throw HardDeclineException::createForPayment($payment_method, 'The provided payment method has expired');
    }
  }

  /**
   * Asserts that the refund amount is valid.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   * @param \Drupal\commerce_price\Price $refund_amount
   *   The refund amount.
   *
   * @throws \Drupal\commerce_payment\Exception\InvalidRequestException
   *   Thrown when the refund amount is larger than the payment balance.
   */
  protected function assertRefundAmount(PaymentInterface $payment, Price $refund_amount) {
    $balance = $payment->getBalance();
    if ($refund_amount->greaterThan($balance)) {
      throw new InvalidRequestException(sprintf("Can't refund more than %s.", $balance->__toString()));
    }
  }

}
