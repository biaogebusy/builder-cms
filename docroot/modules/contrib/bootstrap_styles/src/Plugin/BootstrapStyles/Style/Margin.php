<?php

namespace Drupal\bootstrap_styles\Plugin\BootstrapStyles\Style;

use Drupal\bootstrap_styles\Style\StylePluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class Margin.
 *
 * @package Drupal\bootstrap_styles\Plugin\Style
 *
 * @Style(
 *   id = "margin",
 *   title = @Translation("Margin"),
 *   group_id = "spacing",
 *   weight = 2
 * )
 */
class Margin extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->config();

    $directions = [
      'left',
      'top',
      'right',
      'bottom',
    ];

    $form['spacing']['margin_description'] = [
      '#type' => 'item',
      '#title' => $this->t('Margin'),
      '#markup' => $this->t('<p>Enter one value per line, in the format <b>key|label</b> where <em>key</em> is the CSS class name (without the .), and <em>label</em> is the human readable name of the margin. <br /> <b>Note:</b> These options will be reflected on the range slider at the Layout Builder form, so make sure to sort them from lowest to greatest.</p>'),
    ];

    $form['spacing']['margin_group'] = [
      '#type' => 'container',
      '#title' => $this->t('Margin'),
      '#title_display' => 'invisible',
      '#tree' => FALSE,
      '#attributes' => [
        'class' => [
          'bs-admin-d-lg-flex',
          'bs-admin-group-form-item-lg-ml',
        ],
      ],
    ];

    $form['spacing']['margin_group']['margin'] = [
      '#type' => 'textarea',
      '#default_value' => $config->get('margin'),
      '#title' => $this->t('Margin (classes)'),
      '#cols' => 60,
      '#rows' => 5,
    ];

    for ($i = 0; $i < 4; $i++) {
      $form['spacing']['margin_group']['margin_' . $directions[$i]] = [
        '#type' => 'textarea',
        '#default_value' => $config->get('margin_' . $directions[$i]),
        '#title' => $this->t('Margin @direction (classes)', ['@direction' => $directions[$i]]),
        '#cols' => 60,
        '#rows' => 5,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->config()
      ->set('margin', $form_state->getValue('margin'))
      ->set('margin_left', $form_state->getValue('margin_left'))
      ->set('margin_top', $form_state->getValue('margin_top'))
      ->set('margin_right', $form_state->getValue('margin_right'))
      ->set('margin_bottom', $form_state->getValue('margin_bottom'))
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildStyleFormElements(array &$form, FormStateInterface $form_state, $storage) {
    $directions = [
      'left',
      'top',
      'right',
      'bottom',
    ];

    // This only for frontend no storage needed for this field.
    $form['margin_type'] = [
      '#type' => 'radios',
      '#options' => [
        'margin' => $this->t('Margin') . '<div class="bs_tooltip" data-placement="top" role="tooltip">' . $this->t('All') . '</div>',
        'margin_left' => $this->t('Left') . '<div class="bs_tooltip" data-placement="top" role="tooltip">' . $this->t('Left') . '</div>',
        'margin_top' => $this->t('Top') . '<div class="bs_tooltip" data-placement="top" role="tooltip">' . $this->t('Top') . '</div>',
        'margin_right' => $this->t('Right') . '<div class="bs_tooltip" data-placement="top" role="tooltip">' . $this->t('Right') . '</div>',
        'margin_bottom' => $this->t('Bottom') . '<div class="bs_tooltip" data-placement="top" role="tooltip">' . $this->t('Bottom') . '</div>',
      ],
      '#title' => $this->t('margin type'),
      '#title_display' => 'invisible',
      '#default_value' => 'margin',
      '#validated' => TRUE,
      '#attributes' => [
        'class' => ['bs_col--full', 'bs_input-boxes', 'bs_input-boxes--box-model', 'bs_margin--type'],
      ],
      '#disable_live_preview' => TRUE,
    ];

    $default_value = 0;
    if (isset($storage['margin']['class'])) {
      $default_value = $this->getStyleOptionIndexByClass('margin', $storage['margin']['class']);
    }

    $form['margin'] = [
      '#type' => 'range',
      '#title' => $this->t('Margin'),
      '#min' => 0,
      '#max' => $this->getStyleOptionsCount('margin'),
      '#step' => 1,
      '#default_value' => $default_value,
      '#attributes' => [
        'class' => ['bs-field-margin'],
      ],
      '#states' => [
        'visible' => [
          ':input.bs_margin--type' => ['value' => 'margin'],
        ],
      ],
    ];

    // Loop through the directions.
    for ($i = 0; $i < 4; $i++) {
      $default_value = 0;
      if (isset($storage['margin_' . $directions[$i]]['class'])) {
        $default_value = $this->getStyleOptionIndexByClass('margin_' . $directions[$i], $storage['margin_' . $directions[$i]]['class']);
      }

      $form['margin_' . $directions[$i]] = [
        '#type' => 'range',
        '#title' => $this->t('Margin @direction', ['@direction' => $directions[$i]]),
        '#min' => 0,
        '#max' => $this->getStyleOptionsCount('margin_' . $directions[$i]),
        '#step' => 1,
        '#default_value' => $default_value,
        '#attributes' => [
          'class' => ['bs-field-margin-' . $directions[$i]],
        ],
        '#states' => [
          'visible' => [
            ':input.bs_margin--type' => ['value' => 'margin_' . $directions[$i]],
          ],
        ],
      ];
    }

    // Pass margin options to drupal settings.
    $margin_options = [];
    $margin_options['margin'] = array_keys($this->getStyleOptions('margin'));
    for ($i = 0; $i < 4; $i++) {
      $margin_options['margin_' . $directions[$i]] = array_keys($this->getStyleOptions('margin_' . $directions[$i]));
    }
    $form['#attached']['drupalSettings']['bootstrap_styles']['spacing']['margin_classes_options'] = $margin_options;

    // Attach the Layout Builder form style for this plugin.
    $form['#attached']['library'][] = 'bootstrap_styles/plugin.margin.layout_builder_form';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitStyleFormElements(array $group_elements) {
    $directions = [
      'left',
      'top',
      'right',
      'bottom',
    ];

    $schema = [
      'margin' => [
        'class' => $this->getStyleOptionClassByIndex('margin', $group_elements['margin']),
      ],
    ];

    for ($i = 0; $i < 4; $i++) {
      $schema['margin_' . $directions[$i]]['class'] = $this->getStyleOptionClassByIndex('margin_' . $directions[$i], $group_elements['margin_' . $directions[$i]]);
    }

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $build, array $storage, $theme_wrapper = NULL) {
    $classes = [];
    $directions = [
      'left',
      'top',
      'right',
      'bottom',
    ];

    if (isset($storage['margin']['class'])) {
      $classes[] = $storage['margin']['class'];
    }

    for ($i = 0; $i < 4; $i++) {
      if (isset($storage['margin_' . $directions[$i]]['class'])) {
        $classes[] = $storage['margin_' . $directions[$i]]['class'];
      }
    }

    // Add the classes to the build.
    $build = $this->addClassesToBuild($build, $classes, $theme_wrapper);

    // Attach bs-classes to the build.
    $build['#attached']['library'][] = 'bootstrap_styles/plugin.margin.build';

    return $build;
  }

}
