<?php

namespace Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the base checkout pane class.
 */
abstract class CheckoutPaneBase extends PluginBase implements CheckoutPaneInterface, ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The parent checkout flow.
   *
   * @var \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesInterface
   */
  protected $checkoutFlow;

  /**
   * The current order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * Constructs a new CheckoutPaneBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface $checkout_flow
   *   The parent checkout flow.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->checkoutFlow = $checkout_flow;
    $this->order = $checkout_flow->getOrder();
    $this->entityTypeManager = $entity_type_manager;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [
      'module' => [$this->pluginDefinition['provider']],
    ];
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
    $available_steps = array_keys($this->checkoutFlow->getSteps());
    $available_steps[] = '_sidebar';
    $default_step = $this->pluginDefinition['default_step'];
    if (!$default_step || !in_array($default_step, $available_steps)) {
      // The specified default step isn't available on the parent checkout flow.
      $default_step = '_disabled';
    }

    return [
      'step' => $default_step,
      'display_label' => NULL,
      'wrapper_element' => $this->pluginDefinition['wrapper_element'],
      'weight' => 10,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $summary = [];
    $summary[] = $this->t('Display label: @display_label', [
      '@display_label' => $this->getDisplayLabel(),
    ]);
    $wrapper_element = $this->getWrapperElement();
    if (!empty($wrapper_element)) {
      $wrapper_element_options = $this->getWrapperElementOptions();
      $summary[] = $this->t('Wrapper element: @wrapper_element', [
        '@wrapper_element' => $wrapper_element_options[$wrapper_element] ?? $wrapper_element,
      ]);
    }

    return implode('<br>', $summary);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['display_label_override'] = [
      '#title' => $this->t('Override the display label'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->configuration['display_label']),
    ];
    $form['display_label'] = [
      '#title' => $this->t('Display label'),
      '#description' => $this->t('Specify the display label to use in checkout, overriding the default value set.'),
      '#type' => 'textfield',
      '#default_value' => $this->getDisplayLabel(),
      '#states' => [
        'visible' => [
          ':input[name="configuration[panes][' . $this->pluginId . '][configuration][display_label_override]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    if (isset($this->pluginDefinition['wrapper_element'])) {
      $form['wrapper_element'] = [
        '#title' => $this->t('Wrapper element'),
        '#options' => $this->getWrapperElementOptions(),
        '#type' => 'select',
        '#default_value' => $this->getWrapperElement(),
      ];
    }

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
      if (!empty($values['wrapper_element'])) {
        $this->configuration['wrapper_element'] = $values['wrapper_element'];
      }
      if (!empty($values['display_label_override']) && !empty($values['display_label'])) {
        $this->configuration['display_label'] = $values['display_label'];
      }
      else {
        unset($this->configuration['display_label']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setOrder(OrderInterface $order) {
    $this->order = $order;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->pluginId;
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
    return $this->configuration['display_label'] ?? $this->pluginDefinition['display_label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAdminDescription(): ?string {
    return $this->pluginDefinition['admin_description'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getWrapperElement() {
    return $this->configuration['wrapper_element'] ?? $this->pluginDefinition['wrapper_element'];
  }

  /**
   * {@inheritdoc}
   */
  public function getStepId() {
    return $this->configuration['step'];
  }

  /**
   * {@inheritdoc}
   */
  public function setStepId($step_id) {
    $this->configuration['step'] = $step_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->configuration['weight'];
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->configuration['weight'] = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {}

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {}

  /**
   * Gets the "wrapper_element" allowed options.
   *
   * @return array
   *   The "wrapper_element" options.
   */
  protected function getWrapperElementOptions(): array {
    return [
      'container' => $this->t('Container'),
      'fieldset' => $this->t('Fieldset'),
      'details' => $this->t('Details (Closed)'),
      'details_open' => $this->t('Details (Open)'),
    ];
  }

}
