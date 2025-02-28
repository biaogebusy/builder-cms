<?php

namespace Drupal\commerce_payment\Form;

use Drupal\commerce\InlineFormManager;
use Drupal\commerce_payment\PaymentGatewayManager;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity\Form\EntityDuplicateFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PaymentGatewayForm extends EntityForm {

  use EntityDuplicateFormTrait;

  /**
   * The payment gateway plugin manager.
   *
   * @var \Drupal\commerce_payment\PaymentGatewayManager
   */
  protected $pluginManager;

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * Constructs a new PaymentGatewayForm object.
   *
   * @param \Drupal\commerce_payment\PaymentGatewayManager $plugin_manager
   *   The payment gateway plugin manager.
   * @param \Drupal\commerce\InlineFormManager $inline_form_manager
   *   The inline form manager.
   */
  public function __construct(PaymentGatewayManager $plugin_manager, InlineFormManager $inline_form_manager) {
    $this->pluginManager = $plugin_manager;
    $this->inlineFormManager = $inline_form_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.commerce_payment_gateway'),
      $container->get('plugin.manager.commerce_inline_form')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (empty($this->pluginManager->getDefinitions())) {
      $form['warning'] = [
        '#markup' => $this->t('No payment gateway plugins found. Please install a module which provides one.'),
      ];
      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $gateway */
    $gateway = $this->entity;
    $plugins = array_column($this->pluginManager->getDefinitions(), 'label', 'id');
    asort($plugins);

    // Use the first available plugin as the default value.
    if (!$gateway->getPluginId()) {
      $plugin_ids = array_keys($plugins);
      $plugin = reset($plugin_ids);
      $gateway->setPluginId($plugin);
    }
    // The form state will have a plugin value if #ajax was used.
    $plugin = $form_state->getValue('plugin', $gateway->getPluginId());
    // Pass the plugin configuration only if the plugin hasn't been changed via #ajax.
    $plugin_configuration = $gateway->getPluginId() == $plugin ? $gateway->getPluginConfiguration() : [];

    $wrapper_id = Html::getUniqueId('payment-gateway-form');
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';
    $form['#tree'] = TRUE;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#default_value' => $gateway->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $gateway->id(),
      '#machine_name' => [
        'exists' => '\Drupal\commerce_payment\Entity\PaymentGateway::load',
      ],
      '#disabled' => !$gateway->isNew(),
    ];
    $form['plugin'] = [
      '#type' => 'radios',
      '#title' => $this->t('Plugin'),
      '#options' => $plugins,
      '#default_value' => $plugin,
      '#required' => TRUE,
      '#disabled' => !$gateway->isNew(),
      '#ajax' => [
        'callback' => '::ajaxRefresh',
        'wrapper' => $wrapper_id,
      ],
    ];

    $inline_form = $this->inlineFormManager->createInstance('plugin_configuration', [
      'plugin_type' => 'commerce_payment_gateway',
      'plugin_id' => $plugin,
      'plugin_configuration' => $plugin_configuration,
    ]);
    $form['configuration']['#inline_form'] = $inline_form;
    $form['configuration']['#parents'] = ['configuration'];
    $form['configuration'] = $inline_form->buildInlineForm($form['configuration'], $form_state);

    $form['conditions'] = [
      '#type' => 'commerce_conditions',
      '#title' => $this->t('Conditions'),
      '#parent_entity_type' => 'commerce_payment_gateway',
      '#entity_types' => ['commerce_order'],
      '#default_value' => $gateway->get('conditions'),
    ];
    $form['conditionOperator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Condition operator'),
      '#title_display' => 'invisible',
      '#options' => [
        'AND' => $this->t('All conditions must pass'),
        'OR' => $this->t('Only one condition must pass'),
      ],
      '#default_value' => $gateway->getConditionOperator(),
    ];
    $form['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#options' => [
        0 => $this->t('Disabled'),
        1  => $this->t('Enabled'),
      ],
      '#default_value' => (int) $gateway->status(),
    ];

    return $form;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $gateway */
    $gateway = $this->entity;
    $gateway->setPluginConfiguration($form_state->getValue(['configuration']));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
    $this->postSave($this->entity, $this->operation);
    $this->messenger()->addMessage($this->t('Saved the %label payment gateway.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.commerce_payment_gateway.collection');
  }

}
