<?php

namespace Drupal\entity_print\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface;
use Drupal\entity_print\Plugin\ExportTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that configures Entity Print settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The Print engine plugin manager.
   *
   * @var \Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface
   */
  protected $pluginManager;

  /**
   * The entity config storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The export type manager.
   *
   * @var \Drupal\entity_print\Plugin\ExportTypeManagerInterface
   */
  protected $exportTypeManager;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface $plugin_manager
   *   The plugin manager object.
   * @param \Drupal\entity_print\Plugin\ExportTypeManagerInterface $export_type_manager
   *   The export type manager interface.
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The config storage.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityPrintPluginManagerInterface $plugin_manager, ExportTypeManagerInterface $export_type_manager, EntityStorageInterface $entity_storage) {
    parent::__construct($config_factory);
    $this->pluginManager = $plugin_manager;
    $this->exportTypeManager = $export_type_manager;
    $this->storage = $entity_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.entity_print.print_engine'),
      $container->get('plugin.manager.entity_print.export_type'),
      $container->get('entity_type.manager')->getStorage('print_engine')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_print_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'entity_print.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $print_engines = [];
    foreach ($this->pluginManager->getDefinitions() as $plugin_id => $definition) {
      /** @var \Drupal\entity_print\Plugin\PrintEngineInterface $class */
      $class = $definition['class'];
      if ($class::dependenciesAvailable()) {
        $print_engines[$definition['export_type']][$plugin_id] = $definition['label'];
      }
    }

    // Show a notification for each disabled print engine.
    foreach (array_keys($this->exportTypeManager->getDefinitions()) as $export_type) {
      foreach ($this->pluginManager->getDisabledDefinitions($export_type) as $plugin_id => $definition) {
        $class = $definition['class'];
        // Show the user which Print engines are disabled, but only for
        // the page load not on AJAX requests.
        if (!$request->isXmlHttpRequest()) {
          $this->messenger()->addWarning($this->t('@name is not available because it is not configured. @installation.', [
            '@name' => $definition['label'],
            '@installation' => $class::getInstallationInstructions(),
          ]));
        }
      }
    }

    $config = $this->config('entity_print.settings');
    $form['entity_print'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Entity Print Config'),
    ];

    // Global settings.
    $form['entity_print']['default_css'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Default CSS'),
      '#description' => $this->t('Provides some very basic font and padding styles.'),
      '#default_value' => $config->get('default_css'),
    ];
    $form['entity_print']['force_download'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force Download'),
      '#description' => $this->t('This option will attempt to force the browser to download the Print with a filename from the node title.'),
      '#default_value' => $config->get('force_download'),
    ];

    $form['entity_print']['base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base URL'),
      '#description' => $this->t('Useful if you plan to print in <em>CLI</em> context like <em>drush</em> using queue workers or other background processing means. Leave blank in all other cases.'),
      '#attributes' => ['placeholder' => $this->getRequest()->getSchemeAndHttpHost()],
      '#default_value' => $config->get('base_url'),
    ];

    foreach ($this->exportTypeManager->getDefinitions() as $export_type => $definition) {
      // If we have a print_engine in the form_state then use that otherwise,
      // fall back to what was saved as this is a fresh form. Check explicitly
      // for NULL in case they selected the None option which is false'y.
      $selected_plugin_id = !is_null($form_state->getValue($export_type)) ? $form_state->getValue($export_type) : $config->get('print_engines.' . $export_type . '_engine');
      $form['entity_print'][$export_type] = [
        '#type' => 'select',
        '#title' => $definition['label'],
        '#description' => $this->t('Select the default %label engine for printing.', ['%label' => $definition['label']]),
        '#options' => !empty($print_engines[$export_type]) ? $print_engines[$export_type] : [],
        '#default_value' => $selected_plugin_id,
        '#empty_option' => $this->t('- None -'),
        '#ajax' => [
          'callback' => '::ajaxPluginFormCallback',
          'wrapper' => $export_type . '-config',
          'effect' => 'fade',
        ],
      ];
      $form['entity_print'][$export_type . '_config'] = [
        '#type' => 'container',
        '#id' => $export_type . '-config',
      ];

      if ($this->pluginManager->isPrintEngineEnabled($selected_plugin_id)) {
        $form['entity_print'][$export_type . '_config'][$selected_plugin_id] = $this->getPluginForm($selected_plugin_id, $form_state);
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax plugin form callback.
   */
  public function ajaxPluginFormCallback(&$form, FormStateInterface $form_state) {
    $export_type = $form_state->getTriggeringElement()['#name'];
    return $form['entity_print'][$export_type . '_config'];
  }

  /**
   * Gets a configuration form for the given plugin.
   *
   * @param string $plugin_id
   *   The plugin id for which we want the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The sub form structure for this plugin.
   */
  protected function getPluginForm($plugin_id, FormStateInterface $form_state) {
    $plugin = $this->pluginManager->createInstance($plugin_id);
    $form = [
      '#type' => 'fieldset',
      '#title' => $this->t('@engine Settings', ['@engine' => $plugin->getPluginDefinition()['label']]),
      '#tree' => TRUE,
    ];
    return $form + $plugin->buildConfigurationForm([], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    foreach ($this->exportTypeManager->getDefinitions() as $export_type => $definition) {
      if ($plugin_id = $form_state->getValue($export_type)) {
        // Load the config entity, submit the relevant plugin form and then save
        // it.
        $entity = $this->loadConfigEntity($plugin_id);
        /** @var \Drupal\entity_print\Plugin\PrintEngineInterface $plugin */
        $plugin = $entity->getPrintEnginePluginCollection()->get($entity->id());
        $plugin->validateConfigurationForm($form, $form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('entity_print.settings');
    foreach ($this->exportTypeManager->getDefinitions() as $export_type => $definition) {
      if ($plugin_id = $form_state->getValue($export_type)) {
        $entity = $this->loadConfigEntity($plugin_id);
        /** @var \Drupal\entity_print\Plugin\PrintEngineInterface $plugin */
        $plugin = $entity->getPrintEnginePluginCollection()->get($entity->id());
        $plugin->submitConfigurationForm($form, $form_state);
        $entity->save();
      }

      // Save the plugin as the default for this engine type.
      $config->set('print_engines.' . $export_type . '_engine', $plugin_id);
    }

    // Save the global settings.
    $values = $form_state->getValues();
    $config
      ->set('default_css', $values['default_css'])
      ->set('force_download', $values['force_download'])
      ->set('base_url', $values['base_url'])
      ->save();

    $this->messenger()->addStatus($this->t('Configuration saved.'));
  }

  /**
   * Gets the config entity backing the specified plugin.
   *
   * @param string $plugin_id
   *   The Print engine plugin id.
   *
   * @return \Drupal\entity_print\Entity\PrintEngineStorage
   *   The loaded config object backing the plugin.
   */
  protected function loadConfigEntity($plugin_id) {
    if (!$entity = $this->storage->load($plugin_id)) {
      $entity = $this->storage->create(['id' => $plugin_id]);
    }
    return $entity;
  }

}
