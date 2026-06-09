<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Text to Audio Media Library action.
 */
#[FieldWidgetAction(
  id: 'text_to_audio_media_library',
  label: new TranslatableMarkup('Text to Audio Media Library'),
  widget_types: ['media_library_widget'],
  field_types: ['entity_reference'],
)]
class TextToAudioMediaLibrary extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  public string $formElementProperty = 'target_id';

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    if (!parent::isAvailable()) {
      return FALSE;
    }
    $field_definition = $this->getFieldDefinition();
    if (!$field_definition) {
      return TRUE;
    }
    if ($field_definition->getSetting('target_type') !== 'media') {
      return FALSE;
    }
    $settings = $field_definition->getSetting('handler_settings');
    $target_bundles = $settings['target_bundles'] ?? NULL;

    $audio_bundles = [];
    $media_types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
    foreach ($media_types as $media_type) {
      if ($media_type->getSource()->getPluginId() === 'audio_file') {
        $audio_bundles[] = $media_type->id();
      }
    }

    if (!is_null($target_bundles)) {
      if (empty(array_intersect($target_bundles, $audio_bundles))) {
        return FALSE;
      }
    }
    elseif (empty($audio_bundles)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Ajax handler for Automators.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
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

    $new_ids = $form[$form_key]['widget']['#ai_automator_media_ids'] ?? NULL;

    $selection_selector = $form[$form_key]['widget']['media_library_selection']['#attributes']['data-drupal-selector'] ?? NULL;
    $update_button_selector = $form[$form_key]['widget']['media_library_update_widget']['#attributes']['data-drupal-selector'] ?? NULL;

    if ($new_ids !== NULL && $selection_selector && $update_button_selector) {
      $response = new AjaxResponse();
      $response->addCommand(new InvokeCommand('[data-drupal-selector="' . $selection_selector . '"]', 'val', [$new_ids]));
      $response->addCommand(new InvokeCommand('[data-drupal-selector="' . $update_button_selector . '"]', 'trigger', ['mousedown']));
      return $response;
    }
    return $form[$form_key];
  }

  /**
   * {@inheritdoc}
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    $ids = [];
    foreach ($entity->get($form_key) as $item) {
      if ($item->target_id) {
        $ids[] = $item->target_id;
      }
    }

    $form[$form_key]['widget']['#ai_automator_media_ids'] = implode(',', $ids);

    return $form[$form_key];
  }

}
