<?php

namespace Drupal\commerce_payment\Plugin\Commerce\InlineForm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce\Attribute\CommerceInlineForm;
use Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\EntityWithPaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Event\FailedPaymentEvent;
use Drupal\commerce_payment\Event\PaymentEvents;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentGatewayFormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a form element for embedding payment gateway forms.
 *
 * Each payment gateway plugin defines its own plugin forms, keyed by operation.
 * The plugin forms operate on a payment or a payment method entity.
 * When the plugin form is submitted, an API call is usually performed, and the
 * updated entity is saved.
 *
 * This inline form takes a payment or a payment method entity, initializes
 * the appropriate plugin form, then lets it do its thing, while ensuring
 * that any thrown exception is correctly handled.
 */
#[CommerceInlineForm(
  id: "payment_gateway_form",
  label: new TranslatableMarkup("Payment gateway form"),
)]
class PaymentGatewayForm extends EntityInlineFormBase {

  /**
   * The plugin form factory.
   *
   * @var \Drupal\Core\Plugin\PluginFormFactoryInterface
   */
  protected $pluginFormFactory;

  /**
   * The plugin form.
   *
   * @var \Drupal\commerce_payment\PluginForm\PaymentGatewayFormInterface
   */
  protected $pluginForm;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->pluginFormFactory = $container->get('plugin_form.factory');
    $instance->routeMatch = $container->get('current_route_match');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'operation' => NULL,
      // Allows parent forms to handle exceptions themselves (in order to
      // perform a redirect, or some other logic).
      'catch_build_exceptions' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function requiredConfiguration() {
    return ['operation'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildInlineForm(array $inline_form, FormStateInterface $form_state) {
    $inline_form = parent::buildInlineForm($inline_form, $form_state);

    assert($this->entity instanceof EntityWithPaymentGatewayInterface);
    $plugin = $this->entity->getPaymentGateway()->getPlugin();
    $this->pluginForm = $this->pluginFormFactory->createInstance($plugin, $this->configuration['operation']);
    assert($this->pluginForm instanceof PaymentGatewayFormInterface);
    $this->pluginForm->setEntity($this->entity);

    try {
      $inline_form = $this->pluginForm->buildConfigurationForm($inline_form, $form_state);
    }
    catch (PaymentGatewayException $e) {
      if (empty($this->configuration['catch_build_exceptions'])) {
        throw $e;
      }

      $inline_form['error'] = [
        '#markup' => $this->t('An error occurred while contacting the gateway. Please try again later.'),
      ];
      $inline_form['#process'][] = [get_class($this), 'preventSubmit'];
    }

    return $inline_form;
  }

  /**
   * Prevents the form from being submitted, by removing the actions element.
   *
   * Done in a #process callback because buildInlineForm() doesn't have access
   * to the complete form (since it's called while the initial form structure
   * is still being built).
   *
   * @param array $element
   *   The form element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The form element.
   */
  public static function preventSubmit(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $complete_form['actions']['#access'] = FALSE;
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateInlineForm(array &$inline_form, FormStateInterface $form_state) {
    parent::validateInlineForm($inline_form, $form_state);

    try {
      $this->pluginForm->validateConfigurationForm($inline_form, $form_state);
      $this->entity = $this->pluginForm->getEntity();
    }
    catch (PaymentGatewayException $e) {
      $this->dispatchFailedPaymentEvent($e);
      $error_element = $this->pluginForm->getErrorElement($inline_form, $form_state);
      $form_state->setError($error_element, $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitInlineForm(array &$inline_form, FormStateInterface $form_state) {
    parent::submitInlineForm($inline_form, $form_state);

    try {
      $this->pluginForm->submitConfigurationForm($inline_form, $form_state);
      $this->entity = $this->pluginForm->getEntity();
    }
    catch (PaymentGatewayException $e) {
      $this->dispatchFailedPaymentEvent($e);
      $error_element = $this->pluginForm->getErrorElement($inline_form, $form_state);
      $form_state->setError($error_element, $e->getMessage());
    }
  }

  /**
   * Dispatches a FailedPaymentEvent.
   *
   * @param \Drupal\commerce_payment\Exception\PaymentGatewayException $e
   *   The payment gateway exception.
   */
  private function dispatchFailedPaymentEvent(PaymentGatewayException $e): void {
    $payment_gateway = $this->entity->getPaymentGateway();
    $order = $this->routeMatch->getParameter('commerce_order');
    $payment = $this->entity instanceof PaymentInterface ? $this->entity : NULL;
    $payment_method = $this->entity instanceof PaymentMethodInterface ? $this->entity : NULL;
    if (
      $order instanceof OrderInterface &&
      $payment_gateway instanceof PaymentGatewayInterface
    ) {
      $event = new FailedPaymentEvent($order, $payment_gateway, $e, $payment);
      if ($payment_method) {
        $event->setPaymentMethod($payment_method);
      }
      $this->eventDispatcher->dispatch($event, PaymentEvents::PAYMENT_FAILURE);
    }
  }

}
