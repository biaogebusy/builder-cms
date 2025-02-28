<?php

namespace Drupal\bootstrap_styles\Plugin\BootstrapStyles\Style;

use Drupal\bootstrap_styles\Style\StylePluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class Border.
 *
 * @package Drupal\bootstrap_styles\Plugin\Style
 *
 * @Style(
 *   id = "border",
 *   title = @Translation("Border"),
 *   group_id = "border",
 *   weight = 1
 * )
 */
class Border extends StylePluginBase {

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

    // Border style.
    $form['border']['style_description'] = [
      '#type' => 'item',
      '#title' => $this->t('Border style'),
      '#markup' => $this->t('<p>Enter one value per line, in the format <b>key|label</b> where <em>key</em> is the CSS class name (without the .), and <em>label</em> is the human readable name of the border style.</p>'),
    ];

    $form['border']['style_group'] = [
      '#type' => 'container',
      '#title' => $this->t('Border style group'),
      '#title_display' => 'invisible',
      '#tree' => FALSE,
      '#attributes' => [
        'class' => [
          'bs-admin-d-lg-flex',
          'bs-admin-group-form-item-lg-ml',
        ],
      ],
    ];

    $form['border']['style_group']['border_style'] = [
      '#type' => 'textarea',
      '#default_value' => $config->get('border_style'),
      '#title' => $this->t('Border style (classes)'),
      '#cols' => 60,
      '#rows' => 5,
    ];

    for ($i = 0; $i < 4; $i++) {
      $form['border']['style_group']['border_' . $directions[$i] . '_style'] = [
        '#type' => 'textarea',
        '#default_value' => $config->get('border_' . $directions[$i] . '_style'),
        '#title' => $this->t('Border @direction style (classes)', ['@direction' => $directions[$i]]),
        '#cols' => 60,
        '#rows' => 5,
      ];
    }

    // Border width.
    $form['border']['width_description'] = [
      '#type' => 'item',
      '#title' => $this->t('Border width'),
      '#markup' => $this->t('<p>Enter one value per line, in the format <b>key|label</b> where <em>key</em> is the CSS class name (without the .), and <em>label</em> is the human readable name of the border width. <br /> <b>Note:</b> These options will be reflected on the range slider at the Layout Builder form, so make sure to sort them from lowest to greatest.</p>'),
    ];

    $form['border']['width_group'] = [
      '#type' => 'container',
      '#title' => $this->t('Border width group'),
      '#title_display' => 'invisible',
      '#tree' => FALSE,
      '#attributes' => [
        'class' => [
          'bs-admin-d-lg-flex',
          'bs-admin-group-form-item-lg-ml',
        ],
      ],
    ];

    $form['border']['width_group']['border_width'] = [
      '#type' => 'textarea',
      '#default_value' => $config->get('border_width'),
      '#title' => $this->t('Border width (classes)'),
      '#cols' => 60,
      '#rows' => 5,
    ];

    for ($i = 0; $i < 4; $i++) {
      $form['border']['width_group']['border_' . $directions[$i] . '_width'] = [
        '#type' => 'textarea',
        '#default_value' => $config->get('border_' . $directions[$i] . '_width'),
        '#title' => $this->t('Border @direction width (classes)', ['@direction' => $directions[$i]]),
        '#cols' => 60,
        '#rows' => 5,
      ];
    }

    // Border colors.
    $form['border']['color_description'] = [
      '#type' => 'item',
      '#title' => $this->t('Border color'),
      '#markup' => $this->t('<p>Enter one value per line, in the format <b>key|label</b> where <em>key</em> is the CSS class name (without the .), and <em>label</em> is the human readable name of the border color.</p>'),
    ];

    $form['border']['color_group'] = [
      '#type' => 'container',
      '#title' => $this->t('Border color group'),
      '#title_display' => 'invisible',
      '#tree' => FALSE,
      '#attributes' => [
        'class' => [
          'bs-admin-d-lg-flex',
          'bs-admin-group-form-item-lg-ml',
        ],
      ],
    ];

    $form['border']['color_group']['border_color'] = [
      '#type' => 'textarea',
      '#default_value' => $config->get('border_color'),
      '#title' => $this->t('Border colors (classes)'),
      '#cols' => 60,
      '#rows' => 5,
    ];

    for ($i = 0; $i < 4; $i++) {
      $form['border']['color_group']['border_' . $directions[$i] . '_color'] = [
        '#type' => 'textarea',
        '#default_value' => $config->get('border_' . $directions[$i] . '_color'),
        '#title' => $this->t('Border @direction colors (classes)', ['@direction' => $directions[$i]]),
        '#cols' => 60,
        '#rows' => 5,
      ];
    }

    // Border rounded corners.
    $form['border']['rounded_corners_description'] = [
      '#type' => 'item',
      '#title' => $this->t('Rounded Corners'),
      '#markup' => $this->t('<p>Enter one value per line, in the format <b>key|label</b> where <em>key</em> is the CSS class name (without the .), and <em>label</em> is the human readable name of the rounded corner. <br /> <b>Note:</b> These options will be reflected on the range slider at the Layout Builder form, so make sure to sort them from lowest to greatest.</p>'),
    ];

    $form['border']['rounded_corners'] = [
      '#type' => 'container',
      '#title' => $this->t('Rounded cornerns'),
      '#title_display' => 'invisible',
      '#tree' => FALSE,
      '#attributes' => [
        'class' => [
          'bs-admin-d-lg-flex',
          'bs-admin-group-form-item-lg-ml',
        ],
      ],
    ];

    $form['border']['rounded_corners']['rounded_corners'] = [
      '#type' => 'textarea',
      '#default_value' => $config->get('rounded_corners'),
      '#title' => $this->t('Rounded corners (classes)'),
      '#cols' => 60,
      '#rows' => 5,
    ];

    $corners = [
      'top_left' => 'Top Left',
      'top_right' => 'Top Right',
      'bottom_left' => 'Bottom Left',
      'bottom_right' => 'Bottom Right',
    ];

    foreach ($corners as $corner_key => $corner_value) {
      $form['border']['rounded_corners']['rounded_corner_' . $corner_key] = [
        '#type' => 'textarea',
        '#default_value' => $config->get('rounded_corner_' . $corner_key),
        '#title' => $this->t('@corner rounded corner (classes)', ['@corner' => $corner_value]),
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
      ->set('border_style', $form_state->getValue('border_style'))
      ->set('border_left_style', $form_state->getValue('border_left_style'))
      ->set('border_top_style', $form_state->getValue('border_top_style'))
      ->set('border_right_style', $form_state->getValue('border_right_style'))
      ->set('border_bottom_style', $form_state->getValue('border_bottom_style'))
      ->set('border_width', $form_state->getValue('border_width'))
      ->set('border_left_width', $form_state->getValue('border_left_width'))
      ->set('border_top_width', $form_state->getValue('border_top_width'))
      ->set('border_right_width', $form_state->getValue('border_right_width'))
      ->set('border_bottom_width', $form_state->getValue('border_bottom_width'))
      ->set('border_color', $form_state->getValue('border_color'))
      ->set('border_left_color', $form_state->getValue('border_left_color'))
      ->set('border_top_color', $form_state->getValue('border_top_color'))
      ->set('border_right_color', $form_state->getValue('border_right_color'))
      ->set('border_bottom_color', $form_state->getValue('border_bottom_color'))
      ->set('rounded_corners', $form_state->getValue('rounded_corners'))
      ->set('rounded_corner_top_left', $form_state->getValue('rounded_corner_top_left'))
      ->set('rounded_corner_top_right', $form_state->getValue('rounded_corner_top_right'))
      ->set('rounded_corner_bottom_left', $form_state->getValue('rounded_corner_bottom_left'))
      ->set('rounded_corner_bottom_right', $form_state->getValue('rounded_corner_bottom_right'))
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
    $form['border_type'] = [
      '#type' => 'radios',
      '#options' => [
        'border' => $this->t('Border') . '<div class="bs_tooltip" data-placement="top" role="tooltip">' . $this->t('All') . '</div>',
        'border_left' => $this->t('Left') . '<div class="bs_tooltip" data-placement="top" role="tooltip">' . $this->t('Left') . '</div>',
        'border_top' => $this->t('Top') . '<div class="bs_tooltip" data-placement="top" role="tooltip">' . $this->t('Top') . '</div>',
        'border_right' => $this->t('Right') . '<div class="bs_tooltip" data-placement="top" role="tooltip">' . $this->t('Right') . '</div>',
        'border_bottom' => $this->t('Bottom') . '<div class="bs_tooltip" data-placement="top" role="tooltip">' . $this->t('Bottom') . '</div>',
      ],
      '#title' => $this->t('Border type'),
      '#title_display' => 'invisible',
      '#default_value' => 'border',
      '#validated' => TRUE,
      '#attributes' => [
        'class' => ['bs_col--full', 'bs_input-boxes', 'bs_input-boxes--box-model', 'bs_border--type'],
      ],
      '#disable_live_preview' => TRUE,
    ];

    $form['border_style'] = [
      '#type' => 'radios',
      '#options' => $this->getStyleOptions('border_style'),
      '#title' => $this->t('Border style'),
      '#default_value' => $storage['border']['border_style']['class'] ?? NULL,
      '#validated' => TRUE,
      '#attributes' => [
        'class' => ['bs-field-border-style', 'bs_input-circles'],
      ],
      '#states' => [
        'visible' => [
          ':input.bs_border--type' => ['value' => 'border'],
        ],
      ],
    ];

    $default_value = 0;
    if (isset($storage['border']['border_width']['class'])) {
      $default_value = $this->getStyleOptionIndexByClass('border_width', $storage['border']['border_width']['class']);
    }

    $form['border_width'] = [
      '#title' => $this->t('Border width'),
      '#type' => 'range',
      '#min' => 0,
      '#max' => $this->getStyleOptionsCount('border_width'),
      '#step' => 1,
      '#default_value' => $default_value,
      '#validated' => TRUE,
      '#attributes' => [
        'class' => ['bs-field-border-width'],
      ],
      '#states' => [
        'visible' => [
          ':input.bs_border--type' => ['value' => 'border'],
        ],
      ],
    ];

    $form['border_color'] = [
      '#type' => 'radios',
      '#options' => $this->getStyleOptions('border_color'),
      '#title' => $this->t('Border color'),
      '#default_value' => $storage['border']['border_color']['class'] ?? NULL,
      '#validated' => TRUE,
      '#attributes' => [
        'class' => ['bs-field-border-color', 'bs_input-circles'],
      ],
      '#states' => [
        'visible' => [
          ':input.bs_border--type' => ['value' => 'border'],
        ],
      ],
    ];

    for ($i = 0; $i < 4; $i++) {

      $form['border_' . $directions[$i] . '_style'] = [
        '#type' => 'radios',
        '#options' => $this->getStyleOptions('border_' . $directions[$i] . '_style'),
        '#title' => $this->t('Border style'),
        '#default_value' => $storage['border']['border_' . $directions[$i] . '_style']['class'] ?? NULL,
        '#validated' => TRUE,
        '#attributes' => [
          'class' => ['bs-field-border-style-' . $directions[$i], 'bs_input-circles'],
        ],
        '#states' => [
          'visible' => [
            ':input.bs_border--type' => ['value' => 'border_' . $directions[$i]],
          ],
        ],
      ];

      $default_value = 0;
      if (isset($storage['border']['border_' . $directions[$i] . '_width']['class'])) {
        $default_value = $this->getStyleOptionIndexByClass('border_' . $directions[$i] . '_width', $storage['border']['border_' . $directions[$i] . '_width']['class']);
      }

      $form['border_' . $directions[$i] . '_width'] = [
        '#type' => 'range',
        '#title' => $this->t('Border @direction width', ['@direction' => $directions[$i]]),
        '#min' => 0,
        '#max' => $this->getStyleOptionsCount('border_' . $directions[$i] . '_width'),
        '#step' => 1,
        '#default_value' => $default_value,
        '#validated' => TRUE,
        '#attributes' => [
          'class' => ['bs-field-border-width-' . $directions[$i]],
        ],
        '#states' => [
          'visible' => [
            ':input.bs_border--type' => ['value' => 'border_' . $directions[$i]],
          ],
        ],
      ];

      $form['border_' . $directions[$i] . '_color'] = [
        '#type' => 'radios',
        '#options' => $this->getStyleOptions('border_' . $directions[$i] . '_color'),
        '#title' => $this->t('Border color'),
        '#default_value' => $storage['border']['border_' . $directions[$i] . '_color']['class'] ?? NULL,
        '#validated' => TRUE,
        '#attributes' => [
          'class' => ['bs-field-border-color-' . $directions[$i], 'bs_input-circles'],
        ],
        '#states' => [
          'visible' => [
            ':input.bs_border--type' => ['value' => 'border_' . $directions[$i]],
          ],
        ],
      ];
    }

    // Rounded Corners.
    $corners = [
      'top_left' => 'Top Left',
      'top_right' => 'Top Right',
      'bottom_left' => 'Bottom Left',
      'bottom_right' => 'Bottom Right',
    ];

    $form['rounded_corners_description'] = [
      '#type' => 'inline_template',
      '#template' => "<strong>{% trans %}Border Radius <small>(Round Corners)</small>{% endtrans %}</strong>",
      '#prefix' => '<hr class="bs_divider"/>',
    ];

    $default_value = 0;
    if (isset($storage['border']['rounded_corners']['class'])) {
      $default_value = $this->getStyleOptionIndexByClass('rounded_corners', $storage['border']['rounded_corners']['class']);
    }

    $icon_path = \Drupal::service('extension.list.module')->getPath('bootstrap_styles') . '/images/';

    $form['rounded_corners'] = [
      '#type' => 'range',
      '#title' => '<span class="sr-only">' . $this->t('Corners') . '</span><div class="bs_tooltip" data-placement="top" role="tooltip">' . $this->t('All Corners') . '</div>',
      '#min' => 0,
      '#max' => $this->getStyleOptionsCount('rounded_corners'),
      '#step' => 1,
      '#default_value' => $default_value,
      '#validated' => TRUE,
      '#attributes' => [
        'class' => ['bs-field-rounded-corners'],
      ],
      '#description' => $this->getSvgIconMarkup($icon_path . 'plugins/border/border-radius.svg'),
    ];

    foreach ($corners as $corner_key => $corner_value) {
      $default_value = 0;
      if (isset($storage['border']['rounded_corner_' . $corner_key]['class'])) {
        $default_value = $this->getStyleOptionIndexByClass('rounded_corner_' . $corner_key, $storage['border']['rounded_corner_' . $corner_key]['class']);
      }

      $form['rounded_corner_' . $corner_key] = [
        '#type' => 'range',
        '#title' => '<span class="sr-only">' . $this->t('@corner', ['@corner' => $corner_value]) . '</span><div class="bs_tooltip" data-placement="top" role="tooltip">' . $this->t('@corner', ['@corner' => $corner_value]) . '</div>',
        '#min' => 0,
        '#max' => $this->getStyleOptionsCount('rounded_corner_' . $corner_key),
        '#step' => 1,
        '#default_value' => $default_value,
        '#validated' => TRUE,
        '#attributes' => [
          'class' => ['bs-field-rounded-corner-' . $corner_key],
        ],
        '#description' => $this->getSvgIconMarkup($icon_path . 'plugins/border/border-radius-' . $corner_key . '.svg'),
      ];

    }

    // Pass border width and round corners options to drupal settings.
    $border_width_options = [];
    $border_width_options['border_width'] = array_keys($this->getStyleOptions('border_width'));
    for ($i = 0; $i < 4; $i++) {
      $border_width_options['border_' . $directions[$i] . '_width'] = array_keys($this->getStyleOptions('border_' . $directions[$i] . '_width'));
    }
    $rounded_corners_options = [];
    $rounded_corners_options['rounded_corners'] = array_keys($this->getStyleOptions('rounded_corners'));
    foreach (array_keys($corners) as $corner_key) {
      $rounded_corners_options['rounded_corner_' . $corner_key] = array_keys($this->getStyleOptions('rounded_corner_' . $corner_key));
    }
    $border_options = [
      'border_width' => $border_width_options,
      'rounded_corners' => $rounded_corners_options,
    ];

    $form['#attached']['drupalSettings']['bootstrap_styles']['border'] = $border_options;

    // Attach the Layout Builder form style for this plugin.
    $form['#attached']['library'][] = 'bootstrap_styles/plugin.border.layout_builder_form';

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
      'border' => [
        'border_style' => [
          'class' => $group_elements['border_style'],
        ],
        'border_width' => [
          'class' => $this->getStyleOptionClassByIndex('border_width', $group_elements['border_width']),
        ],
        'border_color' => [
          'class' => $group_elements['border_color'],
        ],
        'rounded_corners' => [
          'class' => $this->getStyleOptionClassByIndex('rounded_corners', $group_elements['rounded_corners']),
        ],
      ],
    ];

    for ($i = 0; $i < 4; $i++) {
      $schema['border']['border_' . $directions[$i] . '_style']['class'] = $group_elements['border_' . $directions[$i] . '_style'];
      $schema['border']['border_' . $directions[$i] . '_width']['class'] = $this->getStyleOptionClassByIndex('border_' . $directions[$i] . '_width', $group_elements['border_' . $directions[$i] . '_width']);
      $schema['border']['border_' . $directions[$i] . '_color']['class'] = $group_elements['border_' . $directions[$i] . '_color'];
    }

    // Rounded corners.
    $corners = [
      'top_left' => 'Top Left',
      'top_right' => 'Top Right',
      'bottom_left' => 'Bottom Left',
      'bottom_right' => 'Bottom Right',
    ];

    foreach (array_keys($corners) as $corner_key) {
      $schema['border']['rounded_corner_' . $corner_key]['class'] = $this->getStyleOptionClassByIndex('rounded_corner_' . $corner_key, $group_elements['rounded_corner_' . $corner_key]);
    }

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $build, array $storage, $theme_wrapper = NULL) {
    $classes = [];
    $classes[] = $storage['border']['border_style']['class'];
    $classes[] = $storage['border']['border_width']['class'];
    $classes[] = $storage['border']['border_color']['class'];

    $directions = [
      'left',
      'top',
      'right',
      'bottom',
    ];

    $corners = [
      'top_left' => 'Top Left',
      'top_right' => 'Top Right',
      'bottom_left' => 'Bottom Left',
      'bottom_right' => 'Bottom Right',
    ];

    for ($i = 0; $i < 4; $i++) {
      $classes[] = $storage['border']['border_' . $directions[$i] . '_style']['class'];
      $classes[] = $storage['border']['border_' . $directions[$i] . '_width']['class'];
      $classes[] = $storage['border']['border_' . $directions[$i] . '_color']['class'];
    }

    // Rounded corners.
    if (isset($storage['border']['rounded_corners']['class'])) {
      $classes[] = $storage['border']['rounded_corners']['class'];
    }
    foreach (array_keys($corners) as $corner_key) {
      $classes[] = $storage['border']['rounded_corner_' . $corner_key]['class'];
    }

    // Add the classes to the build.
    $build = $this->addClassesToBuild($build, $classes, $theme_wrapper);

    // Attach bs-classes to the build.
    $build['#attached']['library'][] = 'bootstrap_styles/plugin.border.build';

    return $build;
  }

}
