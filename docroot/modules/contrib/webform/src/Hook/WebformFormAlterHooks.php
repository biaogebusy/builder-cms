<?php

namespace Drupal\webform\Hook;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformHtmlEditor;
use Drupal\webform\Element\WebformMessage;
use Drupal\webform\WebformSubmissionForm;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform.
 */
class WebformFormAlterHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, $form_id) {
    switch ($form_id) {
      case 'user_admin_permissions':
        // We need to hide the 'Use the Webform (Default) - DO NOT EDIT text format'
        // permission.
        if (isset($form['permissions']['use text format webform_default'])) {
          $form['permissions']['use text format webform_default']['#access'] = FALSE;
        }
        break;

      case 'field_config_edit_form':
        // Remove the 'Webform (Default) - DO NOT EDIT' options from allowed formats.
        NestedArray::unsetValue($form, [
          'settings',
          'allowed_formats',
          '#options',
          WebformHtmlEditor::DEFAULT_FILTER_FORMAT,
        ]);
        break;
    }
    if (!str_contains($form_id, 'webform_') || str_starts_with($form_id, 'node_')) {
      return;
    }
    // Get form object.
    $form_object = $form_state->getFormObject();
    // Alter the webform submission form.
    if (str_starts_with($form_id, 'webform_submission') && $form_object instanceof WebformSubmissionForm) {
      // Make sure webform libraries are always attached to submission form.
      _webform_page_attachments($form);
      // After build.
      $form['#after_build'][] = '_webform_form_webform_submission_form_after_build';
    }
    // Display editing original language warning.
    if (\Drupal::moduleHandler()->moduleExists('config_translation') && preg_match('/^entity.webform.(?:edit|settings|assets|access|handlers|third_party_settings)_form$/', \Drupal::routeMatch()->getRouteName() ?? '')) {
      /** @var \Drupal\webform\WebformInterface $webform */
      $webform = \Drupal::routeMatch()->getParameter('webform');
      /** @var \Drupal\Core\Language\LanguageManagerInterface $language_manager */
      $language_manager = \Drupal::service('language_manager');
      // If current webform is translated, load the base (default) webform and apply
      // the translation to the elements.
      if ($webform->getLangcode() !== $language_manager->getCurrentLanguage()->getId()) {
        $original_language = $language_manager->getLanguage($webform->getLangcode());
        if ($original_language) {
          $form['langcode_message'] = [
            '#type' => 'webform_message',
            '#message_type' => 'warning',
            '#message_message' => $this->t('You are editing the original %language language for this webform.', [
              '%language' => $original_language->getName(),
            ]),
            '#message_close' => TRUE,
            '#message_storage' => WebformMessage::STORAGE_LOCAL,
            '#message_id' => $webform->id() . '.original_language',
            '#weight' => -100,
          ];
        }
      }
    }
    // Add details 'toggle all' to all webforms (except submission forms).
    if (!$form_object instanceof WebformSubmissionForm) {
      $form['#attributes']['class'][] = 'js-webform-details-toggle';
      $form['#attributes']['class'][] = 'webform-details-toggle';
      $form['#attached']['library'][] = 'webform/webform.element.details.toggle';
      return;
    }
  }

}
