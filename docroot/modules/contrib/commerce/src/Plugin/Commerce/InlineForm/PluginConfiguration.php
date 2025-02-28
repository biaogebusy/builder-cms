<?php

namespace Drupal\commerce\Plugin\Commerce\InlineForm;

use Drupal\commerce\Element\CommerceElementTrait;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a plugin configuration inline form.
 *
 * If the plugin belongs to an entity, it's the embedding form's
 * responsibility to set the submitted configuration on the entity's plugin.
 *
 * @see \Drupal\commerce_payment\Form\PaymentGatewayForm::submitForm()
 *
 * @CommerceInlineForm(
 *   id = "plugin_configuration",
 *   label = @Translation("Plugin configuration"),
 * )
 */
class PluginConfiguration extends InlineFormBase {

  use CommerceElementTrait;

  /**
   * The plugin manager.
   *
   * @var \Drupal\Core\Executable\ExecutableManagerInterface
   */
  protected $pluginManager;

  /**
   * Constructs a new PluginConfiguration object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $plugin_manager
   *   The plugin manager interface.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PluginManagerInterface $plugin_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->setConfiguration($configuration);
    $this->validateConfiguration();
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.' . $configuration['plugin_type'])
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'plugin_type' => '',
      'plugin_id' => '',
      'plugin_configuration' => [],
      'enforce_unique_parents' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function requiredConfiguration() {
    return ['plugin_type', 'plugin_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildInlineForm(array $inline_form, FormStateInterface $form_state) {
    assert(!empty($this->configuration['plugin_type']));
    assert(!empty($this->configuration['plugin_id']));
    assert(is_array($this->configuration['plugin_configuration']));
    $inline_form['form'] = [];
    $inline_form = parent::buildInlineForm($inline_form, $form_state);

    // NestedArray::setValue() crashes when switching between two plugins
    // that share a configuration element of the same name, but not the
    // same type (e.g. "amount" of type number/commerce_price).
    // Configuration must be keyed by plugin ID in $form_state to prevent
    // that, either on this level, or in a parent form element.
    if ($this->configuration['enforce_unique_parents']) {
      $inline_form['form']['#parents'] = array_merge($inline_form['#parents'], [$this->configuration['plugin_id']]);
    }
    $plugin = $this->pluginManager->createInstance($this->configuration['plugin_id'], $this->configuration['plugin_configuration']);
    $inline_form['form'] = $plugin->buildConfigurationForm($inline_form['form'], $form_state);

    return $inline_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateInlineForm(array &$inline_form, FormStateInterface $form_state) {
    parent::validateInlineForm($inline_form, $form_state);

    $plugin = $this->pluginManager->createInstance($this->configuration['plugin_id'], $this->configuration['plugin_configuration']);
    $plugin->validateConfigurationForm($inline_form['form'], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitInlineForm(array &$inline_form, FormStateInterface $form_state) {
    parent::submitInlineForm($inline_form, $form_state);

    $plugin = $this->pluginManager->createInstance($this->configuration['plugin_id'], $this->configuration['plugin_configuration']);
    $plugin->submitConfigurationForm($inline_form['form'], $form_state);
    $form_state->setValueForElement($inline_form, $plugin->getConfiguration());
  }

}
