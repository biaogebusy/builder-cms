<?php

namespace Drupal\bootstrap_styles\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\bootstrap_styles\StylesGroup\StylesGroupManager;

/**
 * Configure and filter styles' plugins.
 */
class StylesFilterConfigForm extends ConfigFormBase {

  /**
   * The styles group plugin manager.
   *
   * @var \Drupal\bootstrap_styles\StylesGroup\StylesGroupManager
   */
  protected $stylesGroupManager;

  /**
   * Constructs a StylesFilterConfigForm object.
   *
   * @param \Drupal\bootstrap_styles\StylesGroup\StylesGroupManager $styles_group_manager
   *   The styles group plugin manager.
   */
  public function __construct(StylesGroupManager $styles_group_manager) {
    $this->stylesGroupManager = $styles_group_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.bootstrap_styles_group')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bootstrap_styles_filter';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::CONFIG,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::CONFIG);

    $form['styles_groups'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    foreach ($this->stylesGroupManager->getStylesGroups() as $group_key => $style_group) {
      // Styles Group.
      if (isset($style_group['styles'])) {
        $form['styles_groups'][$group_key] = [
          '#type' => 'details',
          '#title' => $style_group['title']->__toString(),
          '#open' => TRUE,
          '#tree' => TRUE,
        ];

        foreach ($style_group['styles'] as $style_key => $style) {
          $config_key = 'plugins.' . $group_key . '.' . $style_key . '.enabled';
          $form['styles_groups'][$group_key][$style_key]['enabled'] = [
            '#type' => 'checkbox',
            '#title' => $style['title']->__toString(),
            '#default_value' => $config->get($config_key),
          ];
        }
      }
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(static::CONFIG);
    if ($form_state->getValue('styles_groups')) {
      $styles_group = $form_state->getValue('styles_groups');
      foreach ($styles_group as $group_key => $styles) {
        foreach ($styles as $style_key => $style) {
          foreach ($style as $key => $value) {
            $config_option_name = 'plugins.' . $group_key . '.' . $style_key . '.' . $key;
            $config->set($config_option_name, $value);
          }
        }
      }
      $config->save();
    }

    parent::submitForm($form, $form_state);
  }

}
