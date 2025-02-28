<?php

namespace Drupal\webform_views\Plugin\views\field;

use Drupal\views\ResultRow;

/**
 * Webform submission composite field.
 *
 * @ViewsField("webform_submission_composite_field")
 */
class WebformSubmissionCompositeField extends WebformSubmissionField {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = $this->getEntity($values);

    if ($webform_submission && ($webform_submission->access('view') || !$this->options['webform_check_access'])) {
      $webform = $webform_submission->getWebform();

      // Get format and element key.
      $format = $this->options['webform_element_format'];
      $element_key = $this->definition['webform_submission_field'];
      $composite_key = $this->definition['webform_submission_property'];

      // Get element and element handler plugin.
      $element = $webform->getElement($element_key, TRUE);
      if (!$element) {
        return [];
      }

      // Set the format.
      $element['#format'] = $format;

      $composite_element = $element['#webform_composite_elements'][$composite_key];
      $composite_element['#webform_key'] = $element['#webform_key'];
      $options = [
        'composite_key' => $composite_key,
      ];

      // If this is a non-multiple element or a multiple element and we are only
      // showing a specific delta, we can transparently delegate it.
      if (!$this->webformElementManager->getElementInstance($element)->hasMultipleValues($element) || !$this->options['webform_multiple_value']) {
        if (!$this->options['webform_multiple_value']) {
          $options['delta'] = $this->options['webform_multiple_delta'];
        }

        return $this->webformElementManager->invokeMethod('formatHtml', $composite_element, $webform_submission, $options);
      }

      // On the other hand, if we are requested to show all deltas on a multiple
      // element, then we have to manually construct the list.
      $build = [
        '#theme' => 'item_list',
        '#items' => [],
      ];
      $i = 0;
      do {
        $options['delta'] = $i;
        $formatted_item = $this->webformElementManager->invokeMethod('formatHtml', $composite_element, $webform_submission, $options);
        $build['#items'][] = $formatted_item;
        $i++;
      } while ($formatted_item);
      // Strip the last (empty) delta.
      array_pop($build['#items']);

      return $build;
    }

    return [];
  }

}
