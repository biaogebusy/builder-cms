<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\Traits\ImageAltTextActionButtonTrait;

/**
 * The AltText action.
 */
#[FieldWidgetAction(
  id: 'automator_alt_text',
  label: new TranslatableMarkup('Automator Alt Text'),
  widget_types: ['image_image', 'image_focal_point'],
  field_types: ['image'],
  category: new TranslatableMarkup('AI Automators'),
)]
class ImageAltText extends AutomatorBaseAction {

  use ImageAltTextActionButtonTrait;

  /**
   * {@inheritdoc}
   */
  public string $formElementProperty = 'alt';

  /**
   * {@inheritdoc}
   */
  public bool $clearEntity = FALSE;

  /**
   * Ajax handler for Automators.
   *
   * @param array<mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The updated form element.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    // Get the triggering element, as it contains the settings.
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    $array_parents[] = $this->formElementProperty;
    // Find 'widget' in array_parents to correctly identify the field name
    // and delta regardless of form nesting depth (e.g. media library wraps
    // fields under media/0/fields/...).
    $widget_index = array_search('widget', $array_parents);
    if (!is_int($widget_index) || $widget_index < 1) {
      return [];
    }
    $form_key = $array_parents[$widget_index - 1];
    $key = (int) ($array_parents[$widget_index + 1] ?? 0);

    // Handle media library add forms. These use a different form structure
    // where media entities are stored in form state and fields are nested
    // under $form['media'][$delta]['fields']. We pass the fields subtree
    // so that populateAutomatorValues() works with the correct form root.
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof BaseFormIdInterface && $form_object->getBaseFormId() === 'media_library_add_form') {
      $media_index = array_search('media', $array_parents);
      $media_delta = is_int($media_index) ? (int) ($array_parents[$media_index + 1] ?? 0) : 0;
      return $this->populateAutomatorValues($form['media'][$media_delta]['fields'], $form_state, $form_key, $key);
    }

    return $this->populateAutomatorValues($form, $form_state, $form_key, $key);
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    // Detect media library add forms by checking the form's base ID.
    // In this case, $form is the fields subtree with #parents
    // ['media', $delta, 'fields'], set by AddFormBase.
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof BaseFormIdInterface && $form_object->getBaseFormId() === 'media_library_add_form') {
      $media_delta = (int) ($form['#parents'][1] ?? 0);
      $media_items = $form_state->get('media') ?: [];
      if (!isset($media_items[$media_delta])) {
        return NULL;
      }
      $media = $media_items[$media_delta];
      $display = EntityFormDisplay::collectRenderDisplay($media, 'media_library');
      $display->extractFormValues($media, $form, $form_state);
      return $media;
    }
    return parent::buildEntity($form, $form_state);
  }

}
