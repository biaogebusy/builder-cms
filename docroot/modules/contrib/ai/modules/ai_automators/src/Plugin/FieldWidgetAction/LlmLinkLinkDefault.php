<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * Field Widget Action for LLM Link on link_default widget.
 */
#[FieldWidgetAction(
  id: 'llm_link_link_default',
  label: new TranslatableMarkup('LLM Link Generator'),
  widget_types: ['link_default'],
  field_types: ['link'],
  category: new TranslatableMarkup('AI Automators'),
)]
class LlmLinkLinkDefault extends AutomatorBaseAction {

  /**
   * The form element property.
   */
  public string $formElementProperty = 'uri';

  /**
   * Ajax handler for Automators.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    $array_parents[] = static::FORM_ELEMENT_PROPERTY;
    $form_key = $array_parents[0];
    $key = $array_parents[2] ?? 0;

    return $this->populateAutomatorValues($form, $form_state, $form_key, $key);
  }

  /**
   * {@inheritdoc}
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    if (is_null($key)) {
      // If not key is provided, we should iterate through all items.
      foreach ($entity->get($form_key) as $index => $item) {
        if ($item->get($this->formElementProperty)) {
          if ($item && $item->get($this->formElementProperty)) {
            $form[$form_key]['widget'][$index][$this->formElementProperty]['#value'] = $item->get($this->formElementProperty)->getValue();
            $form[$form_key]['widget'][$index]['title']['#value'] = $item->title ?? '';
          }
        }
      }
    }
    else {
      if (isset($entity->get($form_key)->getValue()[$key])) {
        $item = NULL;
        foreach ($entity->get($form_key) as $index => $item) {
          if ($index === $key) {
            break;
          }
        }
        if ($item && $item->get($this->formElementProperty)) {
          $form[$form_key]['widget'][$key][$this->formElementProperty]['#value'] = $item->get($this->formElementProperty)->getValue();
          $form[$form_key]['widget'][$key]['title']['#value'] = $item->title ?? '';
        }
      }
    }

    return $form[$form_key];
  }

}
