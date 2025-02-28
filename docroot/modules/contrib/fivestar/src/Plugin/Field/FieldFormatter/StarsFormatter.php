<?php

namespace Drupal\fivestar\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\fivestar\Form\FivestarForm;

/**
 * Plugin implementation of the 'fivestar_stars' formatter.
 *
 * @FieldFormatter(
 *   id = "fivestar_stars",
 *   label = @Translation("As stars"),
 *   field_types = {
 *     "fivestar"
 *   },
 *   weight = 1
 * )
 */
class StarsFormatter extends FivestarFormatterBase implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'text_format' => 'average',
      'display_format' => 'average',
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
      '#default_value' => $this->getSetting('fivestar_widget'),
      '#attributes' => [
        'class' => [
          'fivestar-widgets',
          'clearfix',
        ],
      ],
      '#pre_render' => [
        [$this, 'previewsExpand'],
      ],
      '#attached' => [
        'library' => [
          'fivestar/fivestar.admin',
          'fivestar/fivestar.base',
        ],
      ],
    ];

    $elements['display_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Value to display as stars'),
      '#options' => [
        'average' => $this->t('Average vote'),
      ],
      '#default_value' => $this->getSetting('display_format'),
    ];

    $elements['text_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Text to display under the stars'),
      '#options' => [
        'none' => $this->t('No text'),
        'average' => $this->t('Average vote'),
      ],
      '#default_value' => $this->getSetting('text_format'),
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
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $entity = $items->getEntity();
    $form_builder = \Drupal::formBuilder();
    $widget_active_key = $this->getSelectedWidgetKey();
    $display_settings = [
      'name' => $this->widgetManager->getWidgetInfo($widget_active_key) ? $widget_active_key : 'default',
      'entity_type_id' => $items->getEntity()->getEntityTypeId(),
      'entity_id' => $items->getEntity()->id(),
    ] + $this->getSettings();

    if (!$items->isEmpty()) {
      /** @var \Drupal\fivestar\Plugin\Field\FieldType\FivestarItem $item */
      foreach ($items as $delta => $item) {
        $context = [
          'entity' => $entity,
          'field_definition' => $item->getFieldDefinition(),
          'display_settings' => $display_settings,
        ];

        $elements[$delta] = $form_builder->getForm(
          FivestarForm::class, $context
        );
      }
    }
    // Load empty form ('No votes yet') if there are no items.
    else {
      $bundle_fields = \Drupal::getContainer()->get('entity_field.manager')->getFieldDefinitions($entity->getEntityType()->id(), $entity->bundle());
      $field_definition = $bundle_fields[$items->getName()];

      $context = [
        'entity' => $entity,
        'field_definition' => $field_definition,
        'display_settings' => $display_settings,
      ];

      $elements[] = $form_builder->getForm(
        FivestarForm::class, $context
      );
    }

    return $elements;
  }

  /**
   * Gets the selected widget key.
   *
   * Sites that used an older version of the module will have
   * a stale key set for their selected widget. This returns
   * the proper, cleaned up version if that's the case.
   *
   * @return string
   *   The active widget's key
   */
  protected function getSelectedWidgetKey() {
    $setting = $this->getSetting('fivestar_widget') ?: 'default';
    if (strpos($setting, '.css') === FALSE) {
      return $setting;
    }

    $file_name = basename($setting);
    $file_name_exploded = explode('.', $file_name);
    $setting = reset($file_name_exploded);
    return $setting;
  }

}
