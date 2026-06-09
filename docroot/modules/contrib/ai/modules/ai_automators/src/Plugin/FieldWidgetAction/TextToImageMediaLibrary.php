<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Text to Image Media Library action.
 */
#[FieldWidgetAction(
  id: 'text_to_image_media_library',
  label: new TranslatableMarkup('Text to Image Media Library'),
  widget_types: ['media_library_widget'],
  field_types: ['entity_reference'],
)]
class TextToImageMediaLibrary extends AutomatorBaseAction {

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

    $image_bundles = [];
    $media_types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
    foreach ($media_types as $media_type) {
      if ($media_type->getSource()->getPluginId() === 'image') {
        $image_bundles[] = $media_type->id();
      }
    }

    if (!is_null($target_bundles)) {
      if (empty(array_intersect($target_bundles, $image_bundles))) {
        return FALSE;
      }
    }
    elseif (empty($image_bundles)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function singleElementFormAlter(array &$form, FormStateInterface $form_state, array $context = []) {
    $field_definition = $context['items']->getFieldDefinition();
    $cardinality = $field_definition->getFieldStorageDefinition()->getCardinality();
    // Hide the button when the field has reached its cardinality limit.
    if ($cardinality !== FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      $count = 0;
      foreach ($context['items'] as $item) {
        if (!$item->isEmpty()) {
          $count++;
        }
      }
      if ($count >= $cardinality) {
        return;
      }
    }
    parent::singleElementFormAlter($form, $form_state, $context);
  }

  /**
   * Ajax handler for Automators.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    // Get the triggering element, as it contains the settings.
    $triggering_element = $form_state->getTriggeringElement();

    // Attempt to get context directly from the triggering element properties
    // set in FieldWidgetActionBase::actionButton.
    $key = $triggering_element['#field_widget_action_field_delta'] ?? NULL;
    $form_key = $triggering_element['#field_widget_action_field_name'] ?? NULL;

    // Fallback logic if properties are missing.
    if ($form_key === NULL) {
      $array_parents = $triggering_element['#array_parents'];
      array_pop($array_parents);
      // Determine form key from parents (usually index 0).
      $form_key = $array_parents[0];

      if ($key === NULL) {
        // Try to guess delta from array parents (usually index 2).
        // Ensure we only pass an int or NULL.
        $potentialKey = $array_parents[2] ?? 0;
        $key = is_numeric($potentialKey) ? (int) $potentialKey : NULL;
      }
    }

    // Ensure key is strictly int or NULL.
    if ($key !== NULL) {
      $key = (int) $key;
    }

    // Run the automator.
    // This will update the entity and call saveFormValues.
    // We expect saveFormValues to stash the new IDs in the widget array.
    $this->populateAutomatorValues($form, $form_state, $form_key, $key);

    $new_ids = $form[$form_key]['widget']['#ai_automator_media_ids'] ?? NULL;

    // Use specific selectors if available in the form build.
    // This ensures we target the correct element even in complex forms.
    $selection_selector = $form[$form_key]['widget']['media_library_selection']['#attributes']['data-drupal-selector'] ?? NULL;
    $update_button_selector = $form[$form_key]['widget']['media_library_update_widget']['#attributes']['data-drupal-selector'] ?? NULL;

    if ($new_ids !== NULL && $selection_selector && $update_button_selector) {
      $response = new AjaxResponse();
      // Update the hidden field with the new IDs.
      $response->addCommand(new InvokeCommand('[data-drupal-selector="' . $selection_selector . '"]', 'val', [$new_ids]));
      // Trigger the update widget button to refresh the Media Library display.
      $response->addCommand(new InvokeCommand('[data-drupal-selector="' . $update_button_selector . '"]', 'trigger', ['mousedown']));
      return $response;
    }

    // Fallback if we can't find selectors or no IDs generated.
    return $form[$form_key];
  }

  /**
   * {@inheritdoc}
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    $ids = [];
    // Collect all target IDs from the entity field.
    foreach ($entity->get($form_key) as $item) {
      if ($item->target_id) {
        $ids[] = $item->target_id;
      }
    }

    // Store IDs in the form array for the AJAX handler to use.
    $form[$form_key]['widget']['#ai_automator_media_ids'] = implode(',', $ids);

    return $form[$form_key];
  }

}
