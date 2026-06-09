<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The File action.
 */
#[FieldWidgetAction(
  id: 'automator_file',
  label: new TranslatableMarkup('Automator File'),
  widget_types: ['file_generic', 'file_default'],
  field_types: ['file'],
)]
class File extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  public string $formElementProperty = '';

  /**
   * Ajax handler for Automators.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    // Get the triggering element, as it contains the settings.
    $triggering_element = $form_state->getTriggeringElement();

    $key = $triggering_element['#field_widget_action_field_delta'] ?? NULL;
    $form_key = $triggering_element['#field_widget_action_field_name'] ?? NULL;

    if ($form_key === NULL) {
      $array_parents = $triggering_element['#array_parents'];
      array_pop($array_parents);
      $form_key = $array_parents[0];

      if ($key === NULL) {
        $potentialKey = $array_parents[2] ?? 0;
        $key = is_numeric($potentialKey) ? (int) $potentialKey : NULL;
      }
    }

    if ($key !== NULL) {
      $key = (int) $key;
    }

    $this->populateAutomatorValues($form, $form_state, $form_key, $key);

    // Re-process the widget to generate display elements for the new file.
    $process_widget = function (&$element) use ($form_state, &$form) {
      if (!empty($element['#process'])) {
        foreach ($element['#process'] as $callback) {
          if (is_callable($callback)) {
            $element = call_user_func_array($callback, [&$element, $form_state, &$form]);
          }
        }
      }
    };

    if ($key !== NULL) {
      if (isset($form[$form_key]['widget'][$key])) {
        $process_widget($form[$form_key]['widget'][$key]);
      }
    }
    else {
      foreach ($form[$form_key]['widget'] as $index => $item) {
        if (is_numeric($index) && isset($form[$form_key]['widget'][$index])) {
          $process_widget($form[$form_key]['widget'][$index]);
        }
      }
    }

    return $form[$form_key];
  }

  /**
   * {@inheritdoc}
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    $setValue = function ($index, $item) use (&$form, $form_key) {
      if (isset($form[$form_key]['widget'][$index]) && $item->target_id) {
        // Set the file ID in the widget.
        $form[$form_key]['widget'][$index]['#value'] = ['fids' => [$item->target_id]];

        // Also set the default fids to ensure the file is displayed.
        if (isset($form[$form_key]['widget'][$index]['#default_value'])) {
          $form[$form_key]['widget'][$index]['#default_value'] = ['fids' => [$item->target_id]];
        }
      }
    };

    if (is_null($key)) {
      // If no key is provided, we should iterate through all items.
      foreach ($entity->get($form_key) as $index => $item) {
        if ($item->target_id) {
          $setValue($index, $item);
        }
      }
    }
    else {
      // Handle specific key/index.
      if (isset($entity->get($form_key)[$key])) {
        $item = $entity->get($form_key)[$key];
        if ($item && $item->target_id) {
          $setValue($key, $item);
        }
      }
    }

    return $form[$form_key];
  }

}
