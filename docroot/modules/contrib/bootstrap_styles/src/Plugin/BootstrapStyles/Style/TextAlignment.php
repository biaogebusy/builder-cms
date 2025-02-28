<?php

namespace Drupal\bootstrap_styles\Plugin\BootstrapStyles\Style;

use Drupal\bootstrap_styles\Style\StylePluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class TextAlignment.
 *
 * @package Drupal\bootstrap_styles\Plugin\Style
 *
 * @Style(
 *   id = "text_alignment",
 *   title = @Translation("Text Alignment"),
 *   group_id = "typography",
 *   weight = 2
 * )
 */
class TextAlignment extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->config();

    $form['typography']['text_alignment'] = [
      '#type' => 'textarea',
      '#default_value' => $config->get('text_alignment'),
      '#title' => $this->t('Text alignment (classes)'),
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
      ->set('text_alignment', $form_state->getValue('text_alignment'))
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildStyleFormElements(array &$form, FormStateInterface $form_state, $storage) {

    $form['text_alignment'] = [
      '#type' => 'radios',
      '#options' => $this->getStyleOptions('text_alignment'),
      '#title' => $this->t('Alignment'),
      '#default_value' => $storage['text_alignment']['class'] ?? NULL,
      '#validated' => TRUE,
      '#attributes' => [
        'class' => ['field-text-alignment', 'bs_input-boxes'],
      ],
    ];

    // Add icons to the container types.
    foreach ($form['text_alignment']['#options'] as $key => $value) {
      $form['text_alignment']['#options'][$key] = '<span class="input-icon ' . $key . '"></span>' . $value;
    }

    // Attach the Layout Builder form style for this plugin.
    $form['#attached']['library'][] = 'bootstrap_styles/plugin.text_alignment.layout_builder_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitStyleFormElements(array $group_elements) {
    return [
      'text_alignment' => [
        'class' => $group_elements['text_alignment'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $build, array $storage, $theme_wrapper = NULL) {
    $classes = [];
    if (isset($storage['text_alignment']['class'])) {
      $classes[] = $storage['text_alignment']['class'];
    }

    // Add the classes to the build.
    $build = $this->addClassesToBuild($build, $classes, $theme_wrapper);

    // Attach bs-classes to the build.
    $build['#attached']['library'][] = 'bootstrap_styles/plugin.text_alignment.build';

    return $build;
  }

}
