<?php

namespace Drupal\bootstrap_styles\Plugin\BootstrapStyles\Style;

use Drupal\bootstrap_styles\Style\StylePluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class Effect.
 *
 * @package Drupal\bootstrap_styles\Plugin\Style
 *
 * @Style(
 *   id = "scroll_effects",
 *   title = @Translation("Scroll Effects"),
 *   group_id = "animation",
 *   weight = 3
 * )
 */
class ScrollEffects extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->config();

    // Library assets.
    $form['animation']['scroll_effects_library_type'] = [
      '#type' => 'select',
      '#default_value' => $config->get('scroll_effects_library_type'),
      '#title' => $this->t('How should we load the animation library?'),
      '#required' => TRUE,
      '#options' => [
        'local' => $this->t('Do nothing, my theme handle it'),
        'external' => $this->t('Add the library for me please'),
      ],
      '#description' => $this->t('<p>Default uses the AOS library: <a href="https://michalsnik.github.io/aos" target="_blank">https://michalsnik.github.io/aos</a><br/> You can override the animation library in your theme by using <br /><code>libraries-override:<br />&nbsp;&nbsp;bootstrap_styles/plugin.scroll_effects.build: your_theme/your_new_library_definition</code><br /><small>For more information, please check: <a href="https://www.drupal.org/node/2497313" target="_blank">https://www.drupal.org/node/2497313</a></small></p>'),
    ];

    $form['animation']['scroll_effects'] = [
      '#type' => 'textarea',
      '#default_value' => $config->get('scroll_effects'),
      '#title' => $this->t('Scroll Effects'),
      '#description' => $this->t('<p>Enter one value per line, in the format <b>key|label</b> where <em>key</em> is the attribute\'s value, and <em>label</em> is the human readable name of the effect.</p>'),
      '#cols' => 60,
      '#rows' => 5,
    ];

    $form['animation']['scroll_effects_attr_type'] = [
      '#type' => 'checkbox',
      '#default_value' => $config->get('scroll_effects_attr_type'),
      '#title' => $this->t('Use data attribute instead of class.'),
    ];

    $form['animation']['scroll_effects_data_key'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('scroll_effects_data_key'),
      '#title' => $this->t('Data Key'),
      '#description' => $this->t('<p>The <strong>data_key</strong> will be used as the data attribute. Example: <code>data_key="key"</code></p>'),
      '#states' => [
        'visible' => [
          [
            [':input[name="scroll_effects_attr_type"]' => ['checked' => TRUE]],
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->config()
      ->set('scroll_effects_library_type', $form_state->getValue('scroll_effects_library_type'))
      ->set('scroll_effects_attr_type', $form_state->getValue('scroll_effects_attr_type'))
      ->set('scroll_effects_data_key', $form_state->getValue('scroll_effects_data_key'))
      ->set('scroll_effects', $form_state->getValue('scroll_effects'))
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildStyleFormElements(array &$form, FormStateInterface $form_state, $storage) {
    $form['scroll_effects'] = [
      '#type' => 'radios',
      '#options' => $this->getStyleOptions('scroll_effects'),
      '#title' => $this->t('Scroll Effects'),
      '#default_value' => $storage['scroll_effects']['class'] ?? NULL,
      '#validated' => TRUE,
      '#attributes' => [
        'class' => ['field-scroll-effects'],
      ],
      '#prefix' => '<span class="input-icon"></span>',
    ];

    // Add icons to the effets.
    foreach ($form['scroll_effects']['#options'] as $key => $value) {
      $form['scroll_effects']['#options'][$key] = '<span class="input-icon ' . $key . '"></span>' . $value;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitStyleFormElements(array $group_elements) {
    return [
      'scroll_effects' => [
        'class' => $group_elements['scroll_effects'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $build, array $storage, $theme_wrapper = NULL) {

    $library_type = $this->config()->get('scroll_effects_library_type');
    $attribute_type = $this->config()->get('scroll_effects_attr_type');
    $data_key = $this->config()->get('scroll_effects_data_key');

    // Assign the style to element or its theme wrapper if exist.
    if ($theme_wrapper && isset($build['#theme_wrappers'][$theme_wrapper])) {
      if (!empty($attribute_type)) {
        // Output some sort of data attribute.
        $build['#theme_wrappers'][$theme_wrapper]['#attributes'][$data_key][] = $storage['scroll_effects']['class'];
      }
      else {
        // Output classes.
        $build['#theme_wrappers'][$theme_wrapper]['#attributes']['class'][] = $storage['scroll_effects']['class'];
      }
    }
    else {
      if (!empty($attribute_type)) {
        // Output some sort of data attribute.
        $build['#attributes'][$data_key][] = $storage['scroll_effects']['class'];
      }
      else {
        // Output classes.
        $build['#attributes']['class'][] = $storage['scroll_effects']['class'];
      }
    }

    if (isset($library_type) && $library_type === 'external') {
      $build['#attached']['library'][] = 'bootstrap_styles/plugin.scroll_effects.build';
    }

    return $build;
  }

}
