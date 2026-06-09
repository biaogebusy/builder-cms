<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The FAQ Field action.
 */
#[FieldWidgetAction(
  id: 'automator_faqfield',
  label: new TranslatableMarkup('Automator FAQ Field'),
  widget_types: ['faqfield_default'],
  field_types: ['faqfield'],
  multiple: FALSE,
)]
class FaqField extends AutomatorBaseAction {

  /**
   * {@inheritdoc}
   */
  public string $formElementProperty = 'question';

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

    // Run the automator.
    $entity = $this->entityModifier->saveEntity($entity, FALSE, $field_name, FALSE);

    $items = $entity->get($field_name);
    $items_count = count($items);

    if ($items_count === 0) {
      return;
    }

    // Set user input so the form rebuild creates proper faq sub-fields
    // with the automator values. This ensures the cached form has the
    // correct structure, so Save works correctly.
    $input = $form_state->getUserInput();
    foreach ($items as $index => $item) {
      $item_array = $item->toArray();
      if (array_key_exists('answer_format', $item_array)) {
        $item_array['answer'] = [
          'value' => $item_array['answer'],
          'format' => $item_array['answer_format'],
        ];
        unset($item_array['answer_format']);
      }
      $input[$field_name][$index] = $item_array;
    }
    $form_state->setUserInput($input);

    // Update widget state so the form rebuilds with enough deltas.
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
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $form_key = $triggering_element['#field_widget_action_field_name'] ?? NULL;

    if (!$form_key || !isset($form[$form_key])) {
      return [];
    }

    // Clear the rebuild flag set by runAutomatorSubmit so that
    // subsequent form submissions (e.g. Save) process normally.
    $form_state->setRebuild(FALSE);

    return $form[$form_key];
  }

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
    $form[$widgetId]['#submit'][] = [$this, 'runAutomatorSubmit'];
    $form[$widgetId]['#executes_submit_callback'] = TRUE;
    $form[$widgetId]['#automator_config'] = $this->getConfiguration();
  }

}
