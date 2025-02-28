<?php

namespace Drupal\commerce_tax\Form;

use Drupal\commerce\InlineFormManager;
use Drupal\commerce_tax\TaxTypeManager;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TaxTypeForm extends EntityForm {

  /**
   * The tax type plugin manager.
   *
   * @var \Drupal\commerce_tax\TaxTypeManager
   */
  protected $pluginManager;

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * Constructs a new TaxTypeForm object.
   *
   * @param \Drupal\commerce_tax\TaxTypeManager $plugin_manager
   *   The tax type plugin manager.
   * @param \Drupal\commerce\InlineFormManager $inline_form_manager
   *   The inline form manager.
   */
  public function __construct(TaxTypeManager $plugin_manager, InlineFormManager $inline_form_manager) {
    $this->pluginManager = $plugin_manager;
    $this->inlineFormManager = $inline_form_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.commerce_tax_type'),
      $container->get('plugin.manager.commerce_inline_form')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\commerce_tax\Entity\TaxTypeInterface $type */
    $type = $this->entity;
    $plugins = $this->buildPluginOptions();

    // Use the first available plugin as the default value.
    if (!$type->getPluginId()) {
      $plugin_ids = array_keys($plugins);
      $plugin = reset($plugin_ids);
      $type->setPluginId($plugin);
    }
    // The form state will have a plugin value if #ajax was used.
    $plugin = $form_state->getValue('plugin', $type->getPluginId());
    // Pass the plugin configuration only if the plugin hasn't been changed via #ajax.
    $plugin_configuration = $type->getPluginId() == $plugin ? $type->getPluginConfiguration() : [];

    $wrapper_id = Html::getUniqueId('tax-type-form');
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    $form['#tree'] = TRUE;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#default_value' => $type->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\commerce_tax\Entity\TaxType::load',
      ],
      '#disabled' => !$type->isNew(),
    ];
    $form['plugin'] = [
      '#type' => 'radios',
      '#title' => $this->t('Plugin'),
      '#options' => $plugins,
      '#default_value' => $plugin,
      '#required' => TRUE,
      '#disabled' => !$type->isNew(),
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::ajaxRefresh',
        'wrapper' => $wrapper_id,
      ],
    ];
    $inline_form = $this->inlineFormManager->createInstance('plugin_configuration', [
      'plugin_type' => 'commerce_tax_type',
      'plugin_id' => $plugin,
      'plugin_configuration' => $plugin_configuration,
    ]);
    $form['configuration']['#inline_form'] = $inline_form;
    $form['configuration']['#parents'] = ['configuration'];
    $form['configuration'] = $inline_form->buildInlineForm($form['configuration'], $form_state);
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $type->status(),
    ];
    $form['conditions'] = [
      '#type' => 'commerce_conditions',
      '#title' => $this->t('Conditions'),
      '#entity_types' => ['commerce_order'],
      '#parent_entity_type' => 'commerce_tax_type',
      '#default_value' => $type->get('conditions'),
    ];
    $form['conditionOperator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Condition operator'),
      '#title_display' => 'invisible',
      '#options' => [
        'AND' => $this->t('All conditions must pass'),
        'OR' => $this->t('Only one condition must pass'),
      ],
      '#default_value' => $type->getConditionOperator(),
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

    /** @var \Drupal\commerce_tax\Entity\TaxTypeInterface $type */
    $type = $this->entity;
    $type->setPluginConfiguration($form_state->getValue(['configuration']));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
    $this->messenger()->addMessage($this->t('Saved the %label tax type.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.commerce_tax_type.collection');
  }

  /**
   * Retrieves the list of plugins to be listed in the tax type form.
   *
   * @return array
   *   The list of plugins to be listed.
   */
  protected function buildPluginOptions() {
    $plugins = array_column($this->pluginManager->getDefinitions(), 'label', 'id');
    asort($plugins);
    // Move the "custom" plugin to the front, if it exists.
    if (isset($plugins['custom'])) {
      $custom_label = $plugins['custom'];
      unset($plugins['custom']);
      $plugins = ['custom' => $custom_label] + $plugins;
    }

    return $plugins;
  }

}
