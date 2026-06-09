<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Text to Image action.
 */
#[FieldWidgetAction(
  id: 'text_to_image',
  label: new TranslatableMarkup('Text to Image'),
  widget_types: ['image_image'],
  field_types: ['image'],
)]
class TextToImage extends AutomatorBaseAction {

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

    if (!$field_name || !isset($form[$field_name]['widget'][0]['fids'])) {
      return;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = static::buildEntity($form, $form_state);

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

    $entity->get($field_name)->filterEmptyItems();
    $form_state->setValue($field_name, NULL);

    $entity = $this->entityModifier->saveEntity($entity, FALSE, $field_name, FALSE);
    if ($entity->get($field_name)->isEmpty()) {
      return;
    }
    $item = $entity->get($field_name)->first()->toArray();

    // ManagedFile::submit() uses internally. On rebuild,
    // ManagedFile::valueCallback()
    // reads fids as a space-separated string, then the full process chain
    // (ManagedFile -> FileWidget -> ImageWidget) rebuilds preview, buttons,
    // alt and title automatically.
    $fids_parents = $form[$field_name]['widget'][0]['fids']['#parents'];
    $delta_parents = array_slice($fids_parents, 0, -1);

    $user_input = $form_state->getUserInput();
    NestedArray::setValue($user_input, $fids_parents, (string) $item['target_id']);
    NestedArray::setValue($user_input, [...$delta_parents, 'alt'], $item['alt'] ?: $entity->label());
    NestedArray::setValue($user_input, [...$delta_parents, 'title'], $item['title'] ?? '');
    $form_state->setUserInput($user_input);
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

    return $form[$form_key];
  }

}
