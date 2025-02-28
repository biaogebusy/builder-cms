<?php

namespace Drupal\entity_print\Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The PrintEngineBase class.
 */
abstract class PrintEngineBase extends PluginBase implements PrintEngineInterface, ContainerFactoryPluginInterface {

  const PORTRAIT = 'portrait';
  const LANDSCAPE = 'landscape';

  /**
   * The export type plugin.
   *
   * @var \Drupal\entity_print\Plugin\ExportTypeInterface
   */
  protected $exportType;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ExportTypeInterface $export_type) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
    $this->exportType = $export_type;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.entity_print.export_type')->createInstance($plugin_definition['export_type'])
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getExportType() {
    return $this->exportType;
  }

  /**
   * {@inheritdoc}
   */
  public static function getInstallationInstructions() {
    return 'Please install using composer';
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // No validation required by default.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = OptGroup::flattenOptions($form_state->getValues());
    foreach ($this->defaultConfiguration() as $key => $value) {
      $this->configuration[$key] = $values[$key] ?? NULL;
    }
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
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

}
