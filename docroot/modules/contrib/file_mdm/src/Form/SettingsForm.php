<?php

namespace Drupal\file_mdm\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file_mdm\Plugin\FileMetadataPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures file_mdm settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * An array containing the available metadata plugins.
   *
   * @var \Drupal\file_mdm\Plugin\FileMetadataPluginInterface[]
   */
  protected $metadataPlugins = [];

  /**
   * Constructs a SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\file_mdm\Plugin\FileMetadataPluginManager $manager
   *   The file metadata plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, FileMetadataPluginManager $manager) {
    parent::__construct($config_factory);
    foreach ($manager->getDefinitions() as $id => $definition) {
      $this->metadataPlugins[$id] = $manager->createInstance($id);
    }
    uasort($this->metadataPlugins, function ($a, $b) {
      return Unicode::strcasecmp((string) $a->getPluginDefinition()['title'], (string) $b->getPluginDefinition()['title']);
    });
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.file_metadata')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'file_mdm_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['file_mdm.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Cache metadata.
    $form['metadata_cache'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#collapsible' => FALSE,
      '#title' => $this->t('Metadata caching'),
      '#tree' => TRUE,
    ];
    $form['metadata_cache']['settings'] = [
      '#type' => 'file_mdm_caching',
      '#default_value' => $this->config('file_mdm.settings')->get('metadata_cache'),
    ];

    // Settings tabs.
    $form['plugins'] = [
      '#type' => 'vertical_tabs',
      '#tree' => FALSE,
    ];

    // Load subforms from each plugin.
    foreach ($this->metadataPlugins as $id => $plugin) {
      $definition = $plugin->getPluginDefinition();
      $form['file_mdm_plugin_settings'][$id] = [
        '#type' => 'details',
        '#title' => $definition['title'],
        '#description' => $definition['help'],
        '#open' => FALSE,
        '#tree' => TRUE,
        '#group' => 'plugins',
      ];
      $form['file_mdm_plugin_settings'][$id] += $plugin->buildConfigurationForm([], $form_state);
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // Call the form validation handler for each of the plugins.
    foreach ($this->metadataPlugins as $plugin) {
      $plugin->validateConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Call the form submit handler for each of the plugins.
    foreach ($this->metadataPlugins as $plugin) {
      $plugin->submitConfigurationForm($form, $form_state);
    }

    $this->config('file_mdm.settings')->set('metadata_cache', $form_state->getValue(['metadata_cache', 'settings']));

    // Only save settings if they have changed to prevent unnecessary cache
    // invalidations.
    if ($this->config('file_mdm.settings')->getOriginal() != $this->config('file_mdm.settings')->get()) {
      $this->config('file_mdm.settings')->save();
    }
    parent::submitForm($form, $form_state);
  }

}
