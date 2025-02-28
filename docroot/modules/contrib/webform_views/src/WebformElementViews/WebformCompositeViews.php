<?php

namespace Drupal\webform_views\WebformElementViews;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webform\Plugin\WebformElementInterface;
use Drupal\webform\WebformInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default webform views handler for composite webform elements.
 */
class WebformCompositeViews extends WebformElementViewsAbstract {

  /**
   * {@inheritdoc}
   */
  public function getViewsData($element, WebformInterface $webform) {
    $data = parent::getViewsData($element, $webform);

    // Additionally enrich the data with sub-properties of this composite
    // element.
    $table_alias = 'webform_submission_field_' . $webform->id() . '_' . $element['#webform_key'];
    $element_title = (isset($element['#title']) && $element['#title']) ? $element['#title'] : $element['#webform_key'];
    $element_plugin = $this->webformElementManager->getElementInstance($element);

    if (isset($element['#webform_composite_elements'])) {
      $composite_elements = array_keys($element['#webform_composite_elements']);
      foreach ($composite_elements as $composite_key) {
        $data[$table_alias . '__' . $composite_key]['table']['group'] = $this->t('Webform @webform submission data', [
          '@webform' => $webform->label(),
        ]);

        $data[$table_alias . '__' . $composite_key]['table']['join'][$this->entityType->getBaseTable()] = [
          'table' => 'webform_submission_data',
          'field' => 'sid',
          'left_field' => 'sid',
          'extra' => [
            ['field' => 'name', 'value' => $element['#webform_key']],
            ['field' => 'property', 'value' => $composite_key],
          ],
        ];

        $title = isset($element['#webform_composite_elements'][$composite_key]['#title']) ? $element['#webform_composite_elements'][$composite_key]['#title'] : $composite_key;
        $data[$table_alias . '__' . $composite_key]['webform_submission_value'] = [
          'title' => Html::escape($element_title . ': ' . $title),
          'help' => $this->t('Value of the field %field property in webform %webform submission.', [
            '%field' => $element_title,
            '%webform' => $webform->label(),
          ]),
        ];

        foreach ($this->getCompositeViewsData($element_plugin, $element, $composite_key) as $k => $v) {
          $v += [
            'webform_id' => $webform->id(),
            'webform_submission_field' => $element['#webform_key'],
            'webform_submission_property' => $composite_key,
          ];
          $data[$table_alias . '__' . $composite_key]['webform_submission_value'][$k] = $v;
        }
      }
    }

    return $data;
  }

  /**
   * Generate views data for a given composite key of a webform element.
   *
   * @param \Drupal\webform\Plugin\WebformElementInterface $element_plugin
   *   Webform element plugin whose views data definition is requested
   * @param array $element
   *   Webform element whose views data definition is requested
   * @param string $composite_key
   *   Composite key for which views data to generate
   *
   * @return array
   *   Views data definition array that corresponds to the given webform
   *   composite key
   */
  protected function getCompositeViewsData(WebformElementInterface $element_plugin, array $element, $composite_key) {
    $views_data = [];

    $views_data['field'] = [
      'id' => 'webform_submission_composite_field',
      'real field' => $this->entityType->getKey('id'),
      'click sortable' => TRUE,
      'multiple' => $element_plugin->hasMultipleValues($element),
    ];

    $views_data['sort'] = [
      'id' => 'webform_submission_field_sort',
      'real field' => 'value',
    ];

    $views_data['filter'] = [
      'id' => 'webform_submission_composite_field_filter',
      'real field' => 'value',
    ];

    return $views_data;
  }

}
