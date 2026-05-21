<?php

namespace Drupal\simple_oauth\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Base class for Scope Granularity plugins.
 */
abstract class ScopeGranularityBase extends PluginBase implements ConfigurableInterface, PluginFormInterface, ScopeGranularityInterface {

  public function __construct(
    array $configuration,
    string $pluginId,
    array $pluginDefinition,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->setConfiguration($configuration);
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
    // Do not validate the configuration here as invalid configuration may be
    // set on the scope add form.
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration($form_state->getValues());
  }

}
