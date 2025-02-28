<?php

namespace Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow;

use Drupal\commerce\AjaxFormTrait;
use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides the base checkout flow class.
 *
 * Checkout flows should extend this class only if they don't want to use
 * checkout panes. Otherwise they should extend CheckoutFlowWithPanesBase.
 */
abstract class CheckoutFlowBase extends PluginBase implements CheckoutFlowInterface, ContainerFactoryPluginInterface {

  use AjaxFormTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The current order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The order ID (used for serialization).
   *
   * @var string|int|null
   */
  // phpcs:ignore Drupal.Classes.PropertyDeclaration
  protected $_orderId;

  /**
   * The parent config entity.
   *
   * Not available while the plugin is being configured.
   *
   * @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface
   */
  protected $parentEntity;

  /**
   * The ID of the parent entity (used for serialization).
   *
   * @var string|int|null
   */
  // phpcs:ignore Drupal.Classes.PropertyDeclaration
  protected $_parentEntityId;

  /**
   * Static cache of visible steps.
   *
   * @var array
   */
  protected $visibleSteps = [];

  /**
   * Constructs a new CheckoutFlowBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->order = $route_match->getParameter('commerce_order');
    if (array_key_exists('_entity', $configuration)) {
      $this->parentEntity = $configuration['_entity'];
      unset($configuration['_entity']);
    }
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    if (!empty($this->parentEntity)) {
      $this->_parentEntityId = $this->parentEntity->id();
      unset($this->parentEntity);
    }
    if (!empty($this->order)) {
      $this->_orderId = $this->order->id();
      unset($this->order);
    }

    return parent::__sleep();
  }

  /**
   * {@inheritdoc}
   */
  public function __wakeup() {
    parent::__wakeup();

    if (!empty($this->_parentEntityId)) {
      $checkout_flow_storage = $this->entityTypeManager->getStorage('commerce_checkout_flow');
      $this->parentEntity = $checkout_flow_storage->load($this->_parentEntityId);
      unset($this->_parentEntityId);
    }

    if (!empty($this->_orderId)) {
      $order_storage = $this->entityTypeManager->getStorage('commerce_order');
      $this->order = $order_storage->load($this->_orderId);
      unset($this->_orderId);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOrder() {
    if (!$this->order && $this->_orderId) {
      $order_storage = $this->entityTypeManager->getStorage('commerce_order');
      $this->order = $order_storage->load($this->_orderId);
    }

    return $this->order;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousStepId($step_id) {
    $step_ids = array_keys($this->getSteps());
    $previous_step_index = array_search($step_id, $step_ids) - 1;
    while (isset($step_ids[$previous_step_index])) {
      if (!$this->isStepVisible($step_ids[$previous_step_index])) {
        $previous_step_index--;
        continue;
      }
      return $step_ids[$previous_step_index];
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getNextStepId($step_id) {
    $step_ids = array_keys($this->getSteps());
    $next_step_index = array_search($step_id, $step_ids) + 1;
    while (isset($step_ids[$next_step_index])) {
      if (!$this->isStepVisible($step_ids[$next_step_index])) {
        $next_step_index++;
        continue;
      }
      return $step_ids[$next_step_index];
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function redirectToStep($step_id) {
    if (!$this->isStepVisible($step_id)) {
      throw new \InvalidArgumentException(sprintf('Invalid step ID "%s" passed to redirectToStep().', $step_id));
    }

    $order = $this->getOrder();
    $order->set('checkout_step', $step_id);
    $this->onStepChange($step_id);
    $order->save();

    throw new NeedsRedirectException(Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $order->id(),
      'step' => $step_id,
    ])->toString());
  }

  /**
   * {@inheritdoc}
   */
  public function getStepId($requested_step_id = NULL) {
    // Customers can't edit orders that have already been placed.
    if ($this->getOrder()->getState()->getId() != 'draft') {
      return 'complete';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    // Each checkout flow plugin defines its own steps.
    // These two steps are always expected to be present.
    return [
      'payment' => [
        'label' => $this->t('Payment'),
        'next_label' => $this->t('Pay and complete purchase'),
        'has_sidebar' => FALSE,
        'hidden' => TRUE,
      ],
      'complete' => [
        'label' => $this->t('Complete'),
        'next_label' => $this->t('Complete checkout'),
        'has_sidebar' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibleSteps() {
    if (empty($this->visibleSteps)) {
      $steps = $this->getSteps();
      foreach ($steps as $step_id => $step) {
        if (!$this->isStepVisible($step_id)) {
          unset($steps[$step_id]);
        }
      }
      $this->visibleSteps = $steps;
    }

    return $this->visibleSteps;
  }

  /**
   * Gets whether the given step is visible.
   *
   * @param string $step_id
   *   The step ID.
   *
   * @return bool
   *   TRUE if the step is visible, FALSE otherwise.
   */
  protected function isStepVisible($step_id) {
    // All available steps are visible by default.
    $step_ids = array_keys($this->getSteps());
    return in_array($step_id, $step_ids);
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
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'display_checkout_progress' => TRUE,
      'display_checkout_progress_breadcrumb_links' => FALSE,
      'guest_order_assign' => FALSE,
      'guest_new_account' => FALSE,
      'guest_new_account_notify' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['display_checkout_progress'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display checkout progress'),
      '#description' => $this->t('Used by the checkout progress block to determine visibility.'),
      '#default_value' => $this->configuration['display_checkout_progress'],
    ];
    $form['display_checkout_progress_breadcrumb_links'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display checkout progress breadcrumb as links'),
      '#description' => $this->t('Let the checkout progress block render the breadcrumb as links.'),
      '#default_value' => $this->configuration['display_checkout_progress_breadcrumb_links'],
    ];
    $form['guest_order_assign'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Assign an anonymous order to a pre-existing user'),
      '#description' => $this->t('If the email associated with the order matches an existing user account, the order will be assigned to that account.'),
      '#default_value' => $this->configuration['guest_order_assign'],
    ];
    $form['guest_new_account'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create a new account for an anonymous order'),
      '#description' => $this->t('Creates a new user account on checkout completion if the customer specified a non-existent e-mail address.'),
      '#default_value' => $this->configuration['guest_new_account'],
    ];
    $form['guest_new_account_notify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify customer of their new account'),
      '#description' => $this->t('Sends an email alerting the customer of their new account, with a password reset link to access their account.'),
      '#default_value' => $this->configuration['guest_new_account_notify'],
      '#states' => [
        'visible' => [
          ':input[name="configuration[guest_new_account]"]' => ['checked' => TRUE],
        ],
      ],
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
      $this->configuration = [];
      $this->configuration['display_checkout_progress'] = $values['display_checkout_progress'];
      $this->configuration['display_checkout_progress_breadcrumb_links'] = $values['display_checkout_progress_breadcrumb_links'];
      $this->configuration['guest_order_assign'] = $values['guest_order_assign'];
      $this->configuration['guest_new_account'] = $values['guest_new_account'];
      $this->configuration['guest_new_account_notify'] = $values['guest_new_account_notify'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return 'commerce_checkout_flow';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_checkout_flow_' . $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $step_id = NULL) {
    // The $step_id argument is optional only because PHP disallows adding
    // required arguments to an existing interface's method.
    if (empty($step_id)) {
      throw new \InvalidArgumentException('The $step_id cannot be empty.');
    }
    if ($form_state->isRebuilding()) {
      // Ensure a fresh order, in case an ajax submit has modified it.
      $order_storage = $this->entityTypeManager->getStorage('commerce_order');
      $this->order = $order_storage->load($this->getOrder()->id());
    }

    $steps = $this->getSteps();
    $form['#tree'] = TRUE;
    $form['#step_id'] = $step_id;
    $form['#title'] = $steps[$step_id]['label'];
    $form['#theme'] = ['commerce_checkout_form'];
    $form['#attached']['library'][] = 'commerce_checkout/form';
    // Workaround for core bug #2897377.
    $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
    if ($this->hasSidebar($step_id)) {
      $form['sidebar']['order_summary'] = [
        '#theme' => 'commerce_checkout_order_summary',
        '#order_entity' => $this->getOrder(),
        '#checkout_step' => $step_id,
      ];
    }
    $form['actions'] = $this->actions($form, $form_state);

    // Make sure the cache is removed if the parent entity or the order change.
    CacheableMetadata::createFromRenderArray($form)
      ->addCacheableDependency($this->parentEntity)
      ->addCacheableDependency($this->getOrder())
      ->applyTo($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $order = $this->getOrder();
    if ($next_step_id = $this->getNextStepId($form['#step_id'])) {
      $order->set('checkout_step', $next_step_id);
      $this->onStepChange($next_step_id);

      $form_state->setRedirect('commerce_checkout.form', [
        'commerce_order' => $order->id(),
        'step' => $next_step_id,
      ]);
    }

    $order->save();
  }

  /**
   * Reacts to the current step changing.
   *
   * Called before saving the order and redirecting.
   *
   * Handles the following logic
   * 1) Locks the order before the payment page,
   * 2) Unlocks the order when leaving the payment page
   * 3) Places the order before the complete page.
   *
   * @param string $step_id
   *   The new step ID.
   */
  protected function onStepChange($step_id) {
    $order = $this->getOrder();

    // Lock the order while on the 'payment' checkout step. Unlock elsewhere.
    if ($step_id == 'payment') {
      $order->lock();
    }
    elseif ($step_id != 'payment') {
      $order->unlock();
    }
    // Place the order.
    if ($step_id == 'complete' && $order->getState()->getId() == 'draft') {
      // Notify other modules.
      $event = new OrderEvent($order);
      $this->eventDispatcher->dispatch($event, CheckoutEvents::COMPLETION);
      $order->getState()->applyTransitionById('place');
    }
  }

  /**
   * Gets whether the given step has a sidebar.
   *
   * @param string $step_id
   *   The step ID.
   *
   * @return bool
   *   TRUE if the given step has a sidebar, FALSE otherwise.
   */
  protected function hasSidebar($step_id) {
    $steps = $this->getSteps();
    return !empty($steps[$step_id]['has_sidebar']);
  }

  /**
   * Builds the actions element for the current form.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The actions element.
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $steps = $this->getSteps();
    $next_step_id = $this->getNextStepId($form['#step_id']);
    $previous_step_id = $this->getPreviousStepId($form['#step_id']);
    $has_next_step = $next_step_id && isset($steps[$next_step_id]['next_label']);
    $has_previous_step = $previous_step_id && isset($steps[$previous_step_id]['previous_label']);

    $actions = [
      '#type' => 'actions',
      '#access' => $has_next_step,
    ];
    if ($has_next_step) {
      $actions['next'] = [
        '#type' => 'submit',
        '#value' => $steps[$next_step_id]['next_label'],
        '#button_type' => 'primary',
        '#submit' => ['::submitForm'],
      ];
      if ($has_previous_step) {
        $label = $steps[$previous_step_id]['previous_label'];
        $options = [
          'attributes' => [
            'class' => ['link--previous'],
          ],
        ];
        $actions['next']['#suffix'] = Link::createFromRoute($label, 'commerce_checkout.form', [
          'commerce_order' => $this->getOrder()->id(),
          'step' => $previous_step_id,
        ], $options)->toString();
      }
    }

    return $actions;
  }

}
