<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\Traits\ImageAltTextActionButtonTrait;

/**
 * The Image Filename Rewrite action.
 */
#[FieldWidgetAction(
  id: 'automator_image_filename_rewrite',
  label: new TranslatableMarkup('Automator Image Filename Rewrite'),
  widget_types: ['image_image', 'image_focal_point'],
  field_types: ['image'],
  category: new TranslatableMarkup('AI Automators'),
)]
class ImageFilename extends AutomatorBaseAction {

  use ImageAltTextActionButtonTrait;

  /**
   * {@inheritdoc}
   */
  public string $formElementProperty = 'value';

  /**
   * {@inheritdoc}
   */
  public bool $clearEntity = FALSE;

  /**
   * Ajax handler for Automators.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    // Get the triggering element, as it contains the settings.
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    $array_parents[] = $this->formElementProperty;
    $key = $array_parents[2] ?? 0;
    $form_key = $array_parents[0];
    return $this->populateAutomatorValues($form, $form_state, $form_key, $key);
  }

  /**
   * {@inheritdoc}
   */
  public function populateAutomatorValues(array &$form, FormStateInterface $form_state, string $form_key, ?int $key = NULL): array {
    $form = parent::populateAutomatorValues($form, $form_state, $form_key, $key);
    $preview = !empty($form['widget'][$key]['preview']['#uri']) && !empty($form['widget'][$key]['preview']) ?? '';
    // If a preview exists, we have to rerender it.
    if ($preview) {
      // Reset the #uri with the latest file url.
      if (!empty($form['widget'][$key]['#default_value']['target_id'])) {
        /** @var \Drupal\file\FileInterface $file */
        $file = $this->entityTypeManager->getStorage('file')->load($form['widget'][$key]['#default_value']['target_id']);
        if ($file) {
          $form['widget'][$key]['preview']['#uri'] = $file->getFileUri();
        }
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function saveFormValues(array &$form, string $form_key, $entity, ?int $key = NULL): array {
    // Image fields do not have a 'value' property. The automator
    // (LlmRewriteImageFilename) already renames and saves the File entity
    // directly in storeValues(), so there is nothing to write back into the
    // form element here.
    return $form[$form_key];
  }

}
