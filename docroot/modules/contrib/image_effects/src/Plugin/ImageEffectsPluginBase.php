<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base image_effects plugin.
 */
abstract class ImageEffectsPluginBase extends PluginBase implements ImageEffectsPluginBaseInterface {

  /**
   * The image_effects plugin type.
   */
  protected string $pluginType;

  /**
   * Configuration object for image_effects.
   */
  protected Config $config;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    ConfigFactoryInterface $config_factory,
    protected LoggerInterface $logger,
  ) {
    $this->config = $config_factory->getEditable('image_effects.settings');
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->pluginType = $configuration['plugin_type'];
    $config = $this->config->get($this->pluginType . '.plugin_settings.' . $plugin_id);
    $this->setConfiguration(array_merge($this->defaultConfiguration(), is_array($config) ? $config : []));
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('logger.channel.image_effects')
    );
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
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public static function isAvailable(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return $this->pluginType;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $ajax_settings = []) {
    return [];
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
  }

  /**
   * {@inheritdoc}
   */
  public function selectionElement(array $options = []): array {
    return [];
  }

}
