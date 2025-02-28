<?php

namespace Drupal\toolbar_visibility\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ThemeHandler;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Toolbar Visibility Settings Form.
 *
 * @package Drupal\toolbar_visibility\Form
 */
class ToolbarVisibilitySettingsForm extends ConfigFormBase {

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandler
   */
  protected $themeHandler;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ThemeHandler $theme_handler
   *   The theme handler.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ThemeHandler $theme_handler,
    ModuleHandler $module_handler,
    EntityTypeManager $entity_type_manager
  ) {
    parent::__construct($config_factory);
    $this->themeHandler = $theme_handler;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('theme_handler'),
      $container->get('module_handler'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'toolbar_visibility.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'toolbar_visibility_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('toolbar_visibility.settings');

    $themes = [];
    if (!empty($config->get('themes'))) {
      $themes = $config->get('themes');
    }

    $list_themes = $this->themeHandler->listInfo();
    $all_themes = [];
    foreach ($list_themes as $list) {
      $all_themes[$list->getName()] = $list->getName();
    }
    $form['themes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select theme(s)'),
      '#empty_option' => $this->t('- Select -'),
      '#description' => $this->t('Select the theme(s) where you want to remove the Toolbar.'),
      '#options' => $all_themes,
      '#default_value' => $themes,
    ];

    // Support for domains.
    $domainOptions = [];
    if ($this->moduleHandler->moduleExists('domain')) {
      $domains = $this->entityTypeManager->getStorage('domain')
        ->loadByProperties();
      foreach ($domains as $key => $domain) {
        $domainOptions[$key] = $domain->label();
      }
    }
    if (!empty($domainOptions)) {
      $form['domains'] = [
        '#type' => 'select',
        '#multiple' => TRUE,
        '#title' => $this->t('Select domain(s)'),
        '#description' => $this->t('Select which domain(s) where you want to remove the Toolbar.'),
        '#options' => $domainOptions,
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => !empty($config->get('domains')) ? $config->get('domains') : [],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $values = $form_state->cleanValues()->getValues();

    $config = $this->config('toolbar_visibility.settings')
      ->set('themes', $values['themes']);

    if ($this->moduleHandler->moduleExists('domain')) {
      $config->set('domains', $values['domains']);
    }

    $config->save();
  }

}
