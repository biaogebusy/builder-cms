<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Telephone action.
 */
#[FieldWidgetAction(
  id: 'automator_telephone',
  label: new TranslatableMarkup('Automator Telephone'),
  widget_types: ['telephone_default'],
  field_types: ['telephone'],
  multiple: FALSE,
)]
class Telephone extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  protected function actionButton(array &$form, FormStateInterface $form_state, array $context = []): void {
    parent::actionButton($form, $form_state, $context);

    $fieldName = $context['items']->getFieldDefinition()->getName();
    if (!empty($context['action_id'])) {
      $widgetId = $context['action_id'];
    }
    else {
      $widgetId = $fieldName . '_field_widget_action_' . $this->getPluginId();
    }
    if (!empty($context['delta'])) {
      $widgetId .= '_' . $context['delta'];
    }

    // Add submit handler to run the automator and update widget state
    // before the AJAX callback. This ensures the form is rebuilt with
    // enough delta slots for all automator results.
    $form[$widgetId]['#submit'] = [[$this, 'runAutomatorSubmit']];
    $form[$widgetId]['#executes_submit_callback'] = TRUE;
    $form[$widgetId]['#automator_config'] = $this->getConfiguration();
  }

  /**
   * Submit handler to run the automator and update widget state.
   */
  public function runAutomatorSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $field_name = $triggering_element['#field_widget_action_field_name'] ?? NULL;
    $config = $triggering_element['#automator_config'] ?? [];

    if (!$field_name) {
      return;
    }

    $field_element = $form[$field_name]['widget'] ?? NULL;
    if (!$field_element) {
      return;
    }

    $parents = $field_element['#field_parents'] ?? [];

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = static::buildEntity($form, $form_state);

    // Check if automator exists.
    $automator_id = $config['settings']['automator_id'] ?? NULL;
    if ($automator_id) {
      $automator = $this->entityTypeManager->getStorage('ai_automator')->load($automator_id);
      if (!$automator) {
        $this->loggerFactory->get('ai_automators')->warning('Automator @automator_id not found.', [
          '@automator_id' => $automator_id,
        ]);
        return;
      }
    }

    // Clear existing field values before running automator.
    $entity->get($field_name)->filterEmptyItems();
    $form_state->setValue($field_name, NULL);

    // Run the automator.
    $entity = $this->entityModifier->saveEntity($entity, FALSE, $field_name, FALSE);

    $items = $entity->get($field_name);
    $items_count = count($items);

    if ($items_count === 0) {
      return;
    }

    // Store values in form state for the AJAX callback.
    $automator_values = [];
    foreach ($items as $index => $item) {
      if ($item->get($this->formElementProperty)) {
        $automator_values[$index] = $item->get($this->formElementProperty)->getValue();
      }
    }
    $form_state->set('ai_automator_values_' . $field_name, $automator_values);

    // Update widget state so the form rebuilds with enough deltas.
    // formMultipleElements iterates from 0 to items_count, so
    // items_count = count - 1 gives us the right number of slots.
    $field_state = WidgetBase::getWidgetState($parents, $field_name, $form_state);
    $required_count = $items_count - 1;
    if ($required_count > ($field_state['items_count'] ?? 0)) {
      $field_state['items_count'] = $required_count;
      WidgetBase::setWidgetState($parents, $field_name, $form_state, $field_state);
    }

    $form_state->setRebuild();
  }

  /**
   * Ajax handler for Automators.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $form_key = $triggering_element['#field_widget_action_field_name'] ?? NULL;

    if (!$form_key || !isset($form[$form_key])) {
      return [];
    }

    $automator_values = $form_state->get('ai_automator_values_' . $form_key);
    if (empty($automator_values)) {
      return $form[$form_key];
    }

    return $this->saveFormValues($form, $form_key, $automator_values);
  }

  /**
   * {@inheritdoc}
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    // When called from aiAutomatorsAjax, $entity is the stored values array.
    $values = $entity;

    foreach ($values as $index => $value) {
      if (isset($form[$form_key]['widget'][$index][$this->formElementProperty])) {
        $form[$form_key]['widget'][$index][$this->formElementProperty]['#value'] = $value;
      }
    }

    return $form[$form_key];
  }

}
