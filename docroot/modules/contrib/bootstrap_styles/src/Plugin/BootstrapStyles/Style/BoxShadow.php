<?php

namespace Drupal\bootstrap_styles\Plugin\BootstrapStyles\Style;

use Drupal\bootstrap_styles\Style\StylePluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class BoxShadow.
 *
 * @package Drupal\bootstrap_styles\Plugin\Style
 *
 * @Style(
 *   id = "box_shadow",
 *   title = @Translation("Box shadow"),
 *   group_id = "shadow",
 *   weight = 5
 * )
 */
class BoxShadow extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->config();

    $form['shadow']['box_shadow'] = [
      '#type' => 'textarea',
      '#default_value' => $config->get('box_shadow'),
      '#title' => $this->t('Box shadow (classes)'),
      '#description' => $this->t('<p>Enter one value per line, in the format <b>key|label</b> where <em>key</em> is the CSS class name (without the .), and <em>label</em> is the human readable name of the text.</p>'),
      '#cols' => 60,
      '#rows' => 5,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->config()
      ->set('box_shadow', $form_state->getValue('box_shadow'))
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildStyleFormElements(array &$form, FormStateInterface $form_state, $storage) {

    $form['box_shadow'] = [
      '#type' => 'radios',
      '#options' => $this->getStyleOptions('box_shadow'),
      '#title' => $this->t('Box Shadow'),
      '#title_display' => 'invisible',
      '#default_value' => $storage['box_shadow']['class'] ?? NULL,
      '#validated' => TRUE,
      '#attributes' => [
        'class' => ['bs-field-box-shadow'],
      ],
    ];

    // Add icons to the container types.
    foreach ($form['box_shadow']['#options'] as $key => $value) {
      $form['box_shadow']['#options'][$key] = '<span class="input-icon ' . $key . '"></span>' . $value;
    }

    // Attach the Layout Builder form style for this plugin.
    $form['#attached']['library'][] = 'bootstrap_styles/plugin.box_shadow.layout_builder_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitStyleFormElements(array $group_elements) {
    return [
      'box_shadow' => [
        'class' => $group_elements['box_shadow'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $build, array $storage, $theme_wrapper = NULL) {
    $classes = [];
    if (isset($storage['box_shadow']['class'])) {
      $classes[] = $storage['box_shadow']['class'];
    }

    // Add the classes to the build.
    $build = $this->addClassesToBuild($build, $classes, $theme_wrapper);

    // Attach bs-classes to the build.
    $build['#attached']['library'][] = 'bootstrap_styles/plugin.box_shadow.build';

    return $build;
  }

}
