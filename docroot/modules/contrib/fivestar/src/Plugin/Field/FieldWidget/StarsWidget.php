<?php

namespace Drupal\fivestar\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Plugin implementation of the 'fivestar_stars' widget.
 *
 * @FieldWidget(
 *   id = "fivestar_stars",
 *   label = @Translation("Stars"),
 *   field_types = {
 *     "fivestar"
 *   }
 * )
 */
class StarsWidget extends FivestarWidgetBase implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'display_format' => 'average',
      'text_format' => 'none',
      'fivestar_widget' => 'basic',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['fivestar_widget'] = [
      '#type' => 'radios',
      '#options' => $this->widgetManager->getWidgetsOptionSet(),
      '#default_value' => $this->getSelectedWidgetKey(),
      '#attributes' => ['class' => ['fivestar-widgets', 'clearfix']],
      '#pre_render' => [[$this, 'previewsExpand']],
      '#attached' => [
        'library' => [
          'fivestar/fivestar.admin',
          'fivestar/fivestar.base',
        ],
      ],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['previewsExpand'];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary[] = $this->t('Style: @widget', [
      '@widget' => $this->widgetManager->getWidgetLabel($this->getSelectedWidgetKey()),
    ]);
    $summary[] = $this->t('Stars display: @style, Text display: @text', [
      '@style' => $this->getSetting('display_format'),
      '@text' => $this->getSetting('text_format'),
    ]);

    return $summary;
  }

  /**
   * Prepares the widget's render element for rendering.
   *
   * @param array $element
   *   The element to transform.
   *
   * @return array
   *   The transformed element.
   *
   * @see ::formElement()
   */
  public function previewsExpand(array $element) {
    foreach (Element::children($element) as $widget_name) {
      $static_preview = [
        '#theme' => 'fivestar_static',
        '#widget' => ['name' => $widget_name],
        '#attached' => [
          'library' => [$this->widgetManager->getWidgetLibrary($widget_name)],
        ],
      ];
      $element[$widget_name]['#description'] = $this->renderer->render($static_preview);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $widget_active_key = $this->getSelectedWidgetKey();
    $display_settings = [
      'name' => $this->widgetManager->getWidgetInfo($widget_active_key) ? $widget_active_key : 'default',
    ] + $this->getSettings();
    $settings = $items[$delta]->getFieldDefinition()->getSettings();
    $display_settings += $settings;
    $display_settings['entity_type_id'] = $items->getEntity()->getEntityTypeId();
    $display_settings['entity_id'] = $items->getEntity()->id();

    $is_field_config_form = ($form_state->getBuildInfo()['form_id'] == 'field_config_edit_form');
    $voting_is_allowed = (bool) ($settings['rated_while'] == 'editing') || $is_field_config_form;

    $element['rating'] = [
      '#type' => 'fivestar',
      '#title' => $element['#title'],
      '#stars' => $settings['stars'],
      '#allow_clear' => $settings['allow_clear'],
      '#allow_revote' => $settings['allow_revote'],
      '#allow_ownvote' => $settings['allow_ownvote'],
      '#vote_type' => $settings['vote_type'],
      '#default_value' => $items[$delta]->rating ?? 0,
      '#widget' => $display_settings,
      '#settings' => $display_settings,
      '#show_static_result' => !$voting_is_allowed,
    ];

    return $element;
  }

}
