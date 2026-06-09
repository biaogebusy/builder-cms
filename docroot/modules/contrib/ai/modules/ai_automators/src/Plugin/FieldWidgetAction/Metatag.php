<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Metatag Firehose action for AI-assisted metatag generation.
 *
 * This plugin integrates with the Metatag module's metatag_firehose widget
 * to enable AI-powered generation and refinement of metatag data.
 *
 * Known limitations:
 * - The metatag_firehose widget renders in the sidebar, which may affect
 *   the AJAX response targeting and form element discovery.
 * - Metatag stores data as serialized arrays rather than typical field values,
 *   requiring special handling in saveFormValues().
 * - The widget structure uses nested fieldsets for tag groups, which differs
 *   from standard field widget patterns.
 *
 * @see \Drupal\metatag\Plugin\Field\FieldWidget\MetatagFirehose
 */
#[FieldWidgetAction(
  id: 'automator_metatag',
  label: new TranslatableMarkup('Automator Metatag'),
  widget_types: ['metatag_firehose'],
  field_types: ['metatag'],
)]
class Metatag extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   *
   * Metatag fields don't use a simple 'value' property. Instead, they store
   * individual metatag values (title, description, keywords, etc.) as an
   * associative array. We'll need to handle this differently in our methods.
   */
  public string $formElementProperty = 'value';

  /**
   * Ajax handler for Metatag Automators.
   *
   * This method handles the AJAX callback when an automator action is
   * triggered on the metatag_firehose widget. Due to the sidebar placement
   * and nested structure of the Metatag UI, special care is needed to
   * locate and return the correct form element.
   *
   * {@inheritdoc}
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    // Get the triggering element, as it contains the settings.
    $triggering_element = $form_state->getTriggeringElement();
    // Attempt to get context directly from the triggering element properties.
    $form_key = $triggering_element['#field_widget_action_field_name'] ?? NULL;

    // Fallback logic if properties are missing.
    if ($form_key === NULL) {
      $array_parents = $triggering_element['#array_parents'];
      array_pop($array_parents);
      // Determine form key from parents (usually index 0).
      $form_key = $array_parents[0];
    }

    return $this->populateAutomatorValues($form, $form_state, $form_key, NULL);
  }

  /**
   * {@inheritdoc}
   *
   * Saves AI-generated metatag values back to the form.
   *
   * We decode the JSON and extract the first element.
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    if (is_null($key)) {
      foreach ($entity->get($form_key) as $index => $item) {
        $raw_value = $item->getValue();

        if (isset($raw_value['value'])) {
          $decoded = Json::decode($raw_value['value']);
          $metatag_values = $this->convertMetatagFormat($decoded);

          if (!empty($metatag_values)) {
            // Capture and re-assign the returned widget element.
            $form[$form_key]['widget'][$index] = $this->applyMetatagValuesToForm($form, $form_key, $index, $metatag_values);
          }
        }
      }
    }
    else {
      if (isset($entity->get($form_key)[$key])) {
        $item = $entity->get($form_key)[$key];
        $raw_value = $item->getValue();

        if (isset($raw_value['value'])) {
          $decoded = Json::decode($raw_value['value']);
          $metatag_values = $this->convertMetatagFormat($decoded);

          if (!empty($metatag_values)) {
            $form[$form_key]['widget'][$key] = $this->applyMetatagValuesToForm($form, $form_key, $key, $metatag_values);
          }
        }
      }
    }
    if (!empty($form[$form_key]['widget'][0]['#group'])) {
      if (!empty($form[$form_key]['widget'][0]['#type']) && $form[$form_key]['widget'][0]['#type'] == 'details') {
        $form[$form_key]['widget'][0]['#open'] = TRUE;
      }
      $form_key = $form[$form_key]['widget'][0]['#group'];
    }
    return $form[$form_key];
  }

  /**
   * Converts metatag data from name/content format to key-value format.
   *
   * @param array $decoded
   *   The decoded JSON array.
   *
   * @return array
   *   Associative array of metatag values.
   */
  protected function convertMetatagFormat(array $decoded): array {
    $metatag_values = [];

    if (!is_array($decoded)) {
      return $metatag_values;
    }

    foreach ($decoded as $key => $item) {
      if (!is_array($item) && !is_string($item)) {
        continue;
      }
      if (is_string($item)) {
        $metatag_values[$key] = $item;
      }
      elseif (count($item) === 1) {
        $metatag_values = array_merge($metatag_values, $item);
      }
      else {
        $item = array_values($item);
        $meta_tag = $item[0];
        if (str_starts_with($meta_tag, 'meta_')) {
          $meta_tag = substr($meta_tag, strlen('meta_'));
        }
        $metatag_values[$meta_tag] = $item[1];
      }
    }

    return $metatag_values;
  }

  /**
   * Applies metatag values to the form structure.
   *
   * The metatag_firehose widget organizes tags into groups (fieldsets).
   * We need to traverse the form structure to find where each tag should
   * be placed based on its group assignment.
   *
   * @param array $form
   *   The form array.
   * @param string $form_key
   *   The field key (e.g., 'field_metatag').
   * @param int $delta
   *   The field item delta/index.
   * @param array $metatag_values
   *   Associative array of metatag values keyed by tag name.
   *
   * @return array
   *   The updated widget element.
   */
  protected function applyMetatagValuesToForm(array &$form, string $form_key, int $delta, array $metatag_values): array {
    if (!isset($form[$form_key]['widget'][$delta])) {
      return [];
    }

    $widget_element = $form[$form_key]['widget'][$delta];

    foreach (Element::getVisibleChildren($widget_element) as $group_key) {
      foreach (Element::getVisibleChildren($widget_element[$group_key]) as $tag_key) {
        if (isset($metatag_values[$tag_key]) && isset($widget_element[$group_key][$tag_key]['#type'])) {
          // Just set the value properties - that's usually enough.
          $widget_element[$group_key][$tag_key]['#value'] = $metatag_values[$tag_key];
          $widget_element[$group_key][$tag_key]['#default_value'] = $metatag_values[$tag_key];
        }
      }
    }
    // Return the modified widget element.
    return $widget_element;
  }

}
