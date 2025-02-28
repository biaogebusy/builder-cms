<?php

namespace Drupal\bootstrap_styles\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\bootstrap_styles\StylesGroup\StylesGroupManager;
use Drupal\bootstrap_styles\Style\StyleManager;

/**
 * Configure Bootstrap Styles settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The styles group plugin manager.
   *
   * @var \Drupal\bootstrap_styles\StylesGroup\StylesGroupManager
   */
  protected $stylesGroupManager;

  /**
   * The styles plugin manager.
   *
   * @var \Drupal\bootstrap_styles\Style\StyleManager
   */
  protected $styleManager;

  /**
   * Constructs a SettingsForm object.
   *
   * @param \Drupal\bootstrap_styles\StylesGroup\StylesGroupManager $styles_group_manager
   *   The styles group plugin manager.
   * @param \Drupal\bootstrap_styles\Style\StyleManager $style_manager
   *   The styles plugin manager.
   */
  public function __construct(StylesGroupManager $styles_group_manager, StyleManager $style_manager) {
    $this->stylesGroupManager = $styles_group_manager;
    $this->styleManager = $style_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.bootstrap_styles_group'),
      $container->get('plugin.manager.bootstrap_styles')
    );
  }

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'bootstrap_styles.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bootstrap_styles_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $this->messenger()->addWarning('Important: the following classes are only used as an example to explain how to use this module. You should use your own theme classes.');
    // Layout builder theme toggler.
    $options = [
      'dark' => $this->t('Dark'),
      'light' => $this->t('Light'),
    ];

    $form['layout_builder_theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Layout Builder Theme'),
      '#options' => $options,
      '#default_value' => $this->config(static::SETTINGS)->get('layout_builder_theme') ?? 'dark',
    ];

    // Loop through styles groups plugins.
    foreach ($this->stylesGroupManager->getStylesGroups() as $group_plugin_id => $styles_group) {
      // Style group form.
      $group_instance = $this->stylesGroupManager->createInstance($group_plugin_id);
      $form = $group_instance->buildConfigurationForm($form, $form_state);
      if (isset($styles_group['styles'])) {
        // Loop through styles plugins.
        foreach (array_keys($styles_group['styles']) as $style_plugin_id) {
          // Style plugin form.
          $style_instance = $this->styleManager->createInstance($style_plugin_id);
          $form = $style_instance->buildConfigurationForm($form, $form_state);
        }
      }
    }

    // Attach admin form style.
    $form['#attached']['library'][] = 'bootstrap_styles/layout_builder_admin_form_style';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Save layout builder theme.
    $this->config(static::SETTINGS)
      ->set('layout_builder_theme', $form_state->getValue('layout_builder_theme'))
      ->save();

    foreach ($this->stylesGroupManager->getStylesGroups() as $group_plugin_id => $style_group) {
      // Submit group form.
      $group_instance = $this->stylesGroupManager->createInstance($group_plugin_id);
      $group_instance->submitConfigurationForm($form, $form_state);
      if (isset($style_group['styles'])) {
        foreach ($style_group['styles'] as $style_plugin_id => $style) {
          // Submit style form.
          $style_instance = $this->styleManager->createInstance($style_plugin_id);
          $style_instance->submitConfigurationForm($form, $form_state);
        }
      }
    }
  }

}
