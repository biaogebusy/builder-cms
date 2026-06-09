<?php

namespace Drupal\ai_translate\Controller;

use Drupal\ai_translate\TranslationException;
use Drupal\Component\Gettext\PoItem;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for AI Translate AJAX callbacks.
 */
class AiTranslateInterfaceController extends ControllerBase {

  /**
   * Ajax callback to "translate" a string and update the form fields.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public static function translateInterface(
    array $form,
    FormStateInterface $form_state,
    Request $request,
  ) {
    $response = new AjaxResponse();

    $string_row_id = $request->query->get('string_row_id');
    $string_row_key = $request->query->get('string_row_key');
    $langcode = $request->query->get('langcode');
    if ($string_row_id === NULL || !$langcode || $string_row_key === NULL) {
      $response->addCommand(new MessageCommand('There was a problem with the request payload of the automatic interface translation.', NULL, ['type' => 'error']));
      return $response;
    }

    // We need to reconstruct the 'translations' part of the form for this
    // specific string. This structure needs to match what
    // TranslateEditForm::buildForm creates.
    $form_element_to_replace = [];

    // Get the source string to determine its characteristics (e.g., plural).
    // The original form uses $this->translateFilterLoadStrings(), which is
    // protected. We can use the locale storage to get the string by
    // string_row_id. The sources are not contained if translating pages after
    // page 1 due to how the form gets rebuilt, so this is the most reliable
    // method.
    /** @var \Drupal\locale\StringStorageInterface $local_storage */
    $local_storage = \Drupal::service('locale.storage');
    $source_strings = $local_storage->getStrings([
      'lid' => $string_row_id,
    ]);
    if (!$source_strings) {
      $response->addCommand(new MessageCommand('The string we are trying to translate for the automatic interface translation could not be found.', NULL, ['type' => 'error']));
      return $response;
    }

    // Get the first database result (which will be the one matching the ID if
    // it still exists).
    $string_object = reset($source_strings);
    if (!$string_object || !isset($string_object->source)) {
      $response->addCommand(new MessageCommand('The string we are trying to translate for the automatic interface translation does not exist anymore.', NULL, ['type' => 'error']));
      return $response;
    }
    $language = \Drupal::languageManager()->getLanguage($langcode);

    /** @var \Drupal\ai_translate\TextTranslatorInterface $translator */
    $ai_translator = \Drupal::service('ai_translate.text_translator');
    $source_array = explode(PoItem::DELIMITER, $string_object->source);

    // Approximate the number of rows to use in the default textarea.
    // @see \Drupal\locale\Form\TranslateEditForm.
    $rows = min(ceil(str_word_count($source_array[0]) / 12), 10);
    if (count($source_array) === 1) {
      $translation = '';
      try {
        $translation = $ai_translator->translateContent($source_array[0], $language);
      }
      catch (TranslationException $e) {
        // Add a warning but allow the code to continue as the user can
        // decide then to manually translate.
        $message = t('Failed to translate the source content "@source".', [
          '@source' => $source_array[0],
        ]);
        $response->addCommand(new MessageCommand($message, NULL, ['type' => 'error']));
      }
      $form_element_to_replace = [
        '#type' => 'textarea',
        '#title' => t('Translated string (@language)', [
          '@language' => $langcode,
        ]),
        '#title_display' => 'invisible',
        '#rows' => $rows,
        '#value' => $translation,
        '#attributes' => [
          'lang' => $langcode,
        ],
        '#name' => 'strings[' . $string_row_id . '][translations][0]',
      ];
    }
    else {
      // Add a textarea for each plural variant.
      foreach ($source_array as $key => $source) {
        $translation = '';
        try {
          $translation = $ai_translator->translateContent($source, $language);
        }
        catch (TranslationException $e) {
          // Add a warning but allow the code to continue as the user can
          // decide then to manually translate.
          $message = t('Failed to translate the source content "@source".', [
            '@source' => $source,
          ]);
          $response->addCommand(new MessageCommand($message, NULL, ['type' => 'error']));
        }
        $form_element_to_replace[$key] = [
          '#type' => 'textarea',
          '#title' => ($key === 0 ? t('Singular form') : t('Plural form')),
          '#rows' => $rows,
          '#value' => $translation,
          '#name' => 'strings[' . $string_row_id . '][translations][' . $key . ']',
          '#attributes' => [
            'lang' => $langcode,
          ],
          '#prefix' => $key == 0 ? ('<span class="visually-hidden">' . t('Translated string (@language)', [
            '@language' => $language->getName(),
          ]) . '</span>') : '',
        ];
      }
    }

    // Replace the existing form element with the updated one containing the
    // new value. Since we are not updating the $form array, we are replacing
    // raw HTML, we use #value instead of #default_value to render the
    // translation itself into the HTML.
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    $rendered_element = $renderer->render($form_element_to_replace);
    $response->addCommand(new ReplaceCommand(
      '#ai-translate-translations-wrapper-' . $string_row_id,
      '<div id="ai-translate-translations-wrapper-' . $string_row_id . '">' . $rendered_element . '</div>',
    ));

    return $response;
  }

}
