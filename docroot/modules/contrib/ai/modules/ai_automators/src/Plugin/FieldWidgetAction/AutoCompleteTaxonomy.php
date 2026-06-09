<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Autocomplete widget for entity reference fields with autocomplete widget.
 *
 * This handles entity reference fields where each value has its
 * own form element (unlike tags which uses a single comma-separated field).
 */
#[FieldWidgetAction(
  id: 'automator_autocomplete_taxonomy',
  label: new TranslatableMarkup('Automator Taxonomy'),
  widget_types: ['entity_reference_autocomplete'],
  field_types: ['entity_reference'],
  category: new TranslatableMarkup('AI Automators'),
  multiple: FALSE,
)]
class AutoCompleteTaxonomy extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  public string $formElementProperty = 'target_id';

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

    // Add submit handler to run the automator before AJAX callback.
    // @todo fix this to be common in base class.
    $form[$widgetId]['#submit'] = [[$this, 'runAutomatorSubmit']];
    $form[$widgetId]['#executes_submit_callback'] = TRUE;
    $form[$widgetId]['#limit_validation_errors'] = [];

    // Store configuration for the submit handler since it's static.
    $form[$widgetId]['#automator_config'] = $this->getConfiguration();
  }

  /**
   * Ajax submit handler to run the automator and store results.
   *
   * @todo fix to refactor to base class.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function runAutomatorSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $field_name = $triggering_element['#field_widget_action_field_name'] ?? NULL;
    $config = $triggering_element['#automator_config'] ?? [];

    if (!$field_name) {
      return;
    }

    // Get the field element to find the parents.
    $field_element = $form[$field_name]['widget'] ?? NULL;
    if (!$field_element) {
      return;
    }

    $parents = $field_element['#field_parents'] ?? [];

    // Build the entity with current form values.
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

    // Run the automator to get new values.
    $entity = $this->entityModifier->saveEntity($entity, FALSE, $field_name, FALSE);

    // Get the values returned by the automator.
    $items = $entity->get($field_name);
    $items_count = count($items);

    if ($items_count === 0) {
      return;
    }

    // Store values in form state for the AJAX callback to use.
    $automator_values = [];
    foreach ($items as $index => $item) {
      $target_entity = $item->entity;
      if ($target_entity) {
        $automator_values[$index] = [
          'label' => $target_entity->label() . ' (' . $target_entity->id() . ')',
          'target_id' => $target_entity->id(),
        ];
      }
    }
    $form_state->set('ai_automator_values_' . $field_name, $automator_values);

    // Update widget state to have enough items for the automator results.
    $field_state = WidgetBase::getWidgetState($parents, $field_name, $form_state);
    $current_count = $field_state['items_count'] ?? 0;

    // We need items_count to be at least (number of values - 1) because
    // formMultipleElements iterates from 0 to $max (items_count for unlimited).
    $required_count = $items_count - 1;
    if ($required_count > $current_count) {
      $field_state['items_count'] = $required_count;
      WidgetBase::setWidgetState($parents, $field_name, $form_state, $field_state);
    }

    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $form_key = $triggering_element['#field_widget_action_field_name'] ?? NULL;

    if (!$form_key || !isset($form[$form_key])) {
      return [];
    }

    // Get the values stored by the submit handler.
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
    // Populate the form with the stored values.
    $automator_values = $entity;
    foreach ($automator_values as $index => $value) {
      if (isset($form[$form_key]['widget'][$index][$this->formElementProperty])) {
        $form[$form_key]['widget'][$index][$this->formElementProperty]['#value'] = $value['label'];
        $form[$form_key]['widget'][$index][$this->formElementProperty]['#default_value'] = $value['label'];
      }
    }

    return $form[$form_key];
  }

}
