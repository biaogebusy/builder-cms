<?php

namespace Drupal\ai\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\FormElementBase;

/**
 * Provides a form element for editing JSON schemas with CodeMirror.
 *
 * The element renders a CodeMirror 6 editor with JSON language support
 * (syntax highlighting, linting) and syncs the editor content to a hidden
 * textarea for normal form submission.
 *
 * Usage example:
 * @code
 *   $form['json_schema'] = [
 *     '#type' => 'ai_json_schema',
 *     '#title' => t('JSON Schema'),
 *     '#default_value' => '{}',
 *     '#description' => t('Enter a valid JSON schema.'),
 *   ];
 * @endcode
 */
#[FormElement('ai_json_schema')]
class AiJsonSchema extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#process' => [
              [$class, 'processElement'],
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE && $input !== NULL) {
      return $input;
    }
    return $element['#default_value'] ?? '';
  }

  /**
   * Process callback to build the CodeMirror editor element.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The processed form element.
   */
  public static function processElement(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $element_id = Html::getUniqueId('ai-json-schema');
    $default_value = $element['#default_value'] ?? '';

    // Build the form input name from parents.
    $name_parts = $element['#parents'];
    $name = array_shift($name_parts);
    if ($name_parts) {
      $name .= '[' . implode('][', $name_parts) . ']';
    }

    // Hidden input that carries the actual form value.
    // Rendered as raw html_tag to avoid render bubbling issues.
    $element['value'] = [
      '#type' => 'html_tag',
      '#tag' => 'input',
      '#attributes' => [
        'type' => 'hidden',
        'name' => $name,
        'value' => $default_value,
        'data-ai-json-schema-textarea' => $element_id,
      ],
      '#value' => '',
    ];

    // Visible textarea as a graceful fallback.
    // JS will hide this and show the CodeMirror editor instead.
    $escaped_value = Html::escape($default_value);
    $element['fallback'] = [
      '#type' => 'html_tag',
      '#tag' => 'textarea',
      '#value' => $escaped_value,
      '#attributes' => [
        'data-ai-json-schema-fallback' => $element_id,
        'rows' => 10,
        'class' => ['ai-json-schema-fallback'],
      ],
    ];

    // Container where CodeMirror will mount.
    $element['editor'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'data-ai-json-schema-editor' => $element_id,
        'class' => ['ai-json-schema-editor-wrapper'],
        'style' => 'display: none;',
      ],
    ];

    // Attach the library.
    $element['#attached']['library'][] = 'ai/json_schema_editor';

    return $element;
  }

}
