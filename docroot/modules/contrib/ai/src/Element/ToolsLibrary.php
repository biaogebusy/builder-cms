<?php

namespace Drupal\ai\Element;

use Drupal\ai\AiToolsLibraryState;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\FormElementBase;

/**
 * Provides an AI Tools library form element.
 *
 * The #default_value accepted by this element is an IDs of plugins.
 *
 * Usage can include the following components:
 * @code
 *   $element['tools'] = [
 *     '#type' => 'ai_tools_library',
 *     '#title' => t('Tools for this agent'),
 *     '#default_value' => 'ai_tool_1,ai_tool_2,ai_tool_3',
 *     '#description' => t('Choose the tools you want to use with this agent.'),
 *   ];
 * @endcode
 */
#[FormElement('ai_tools_library')]
class ToolsLibrary extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processToolsLibrary'],
      ],
    ];
  }

  /**
   * Processes tools library form element.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The form element.
   */
  public static function processToolsLibrary(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $default_value = $element['#default_value'];
    if (is_array($element['#default_value'])) {
      $default_value = array_filter($element['#default_value']);
      if (!array_is_list($default_value)) {
        $default_value = array_keys($default_value);
      }
      $default_value = implode(',', $default_value);
    }
    $array_parents = $element['#array_parents'];
    $hidden_id = implode('-', $array_parents) . '-ids';
    $element['tools'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'data-ai-tools-library-form-element-value' => $hidden_id,
      ],
      '#default_value' => $default_value,
    ];
    $opener_parameters = [
      'field_widget_id' => $hidden_id,
    ];
    $allowed_groups = \Drupal::service('plugin.manager.ai.function_groups')->getDefinitions();
    $allowed_plugins = \Drupal::service('plugin.manager.ai.function_calls')->getDefinitions();
    $allowed_group_ids = array_keys($allowed_groups);
    array_unshift($allowed_group_ids, '_all');
    $selected_group = '_all';
    $state = AiToolsLibraryState::create('ai_tools_library.opener.form_element', $allowed_group_ids, $selected_group, $opener_parameters);
    $selected_tools = [];
    if (!empty(trim($default_value))) {
      foreach (explode(',', $default_value) as $tool) {
        $selected_tools[$tool] = [
          '#theme' => 'ai_tools_library_item',
          '#title' => $allowed_plugins[$tool]['name'],
          '#description' => $allowed_plugins[$tool]['description'],
          '#tool_id' => $tool,
          '#widget_id' => $hidden_id,
        ];
      }
    }
    if (empty($selected_tools)) {
      $selected_tools = [
        '#markup' => '<span>' . t('You have not selected any tools yet.') . '</span>',
      ];
    }
    array_pop($array_parents);
    array_pop($array_parents);
    $parent = NestedArray::getValue($complete_form, $array_parents);
    $wrapper_id = $parent['#id'];
    $element['tools_library'] = [
      '#theme_wrappers' => ['container'],
      '#attached' => [
        'library' => [
          'ai/tools_library_form_element',
        ],
      ],
      'selected_tools' => [
        '#theme' => 'ai_tools_library_wrapper',
        '#content' => $selected_tools,
        '#attached' => [
          'library' => [
            'ai/tools_library_item',
          ],
        ],
      ],
      'open_modal' => [
        '#type' => 'submit',
        '#value' => t('Select tools'),
        '#limit_validation_errors' => [],
        '#submit' => [],
        '#name' => 'open_tools_library',
        '#ajax' => [
          'callback' => '\Drupal\ai\Element\ToolsLibrary::openModal',
          'event' => 'click',
        ],
        '#ai_tools_library_state' => $state,
        '#executes_submit_callback' => FALSE,
      ],
      'update_widget' => [
        '#type' => 'submit',
        '#value' => t('Update widget'),
        '#name' => $hidden_id . '-ai-tools-library-update',
        '#attributes' => [
          'data-ai-tools-library-form-element-update' => $hidden_id,
          'class' => ['js-hide'],
        ],
        '#submit' => [[static::class, 'updateItem']],
        '#ajax' => [
          'callback' => [static::class, 'updateFormElement'],
          'wrapper' => $wrapper_id,
          'progress' => [
            'type' => 'throbber',
            'message' => t('Adding selection.'),
          ],
        ],
      ],
    ];
    return $element;
  }

  /**
   * AJAX callback to update the widget when the selection changes.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   An AJAX response to update the selection.
   */
  public static function updateFormElement(array $form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $length = -4;
    if (count($triggering_element['#array_parents']) < abs($length)) {
      throw new \LogicException(
        'The element that triggered the form element update was at an unexpected depth. Triggering element parents were: ' . implode(',', $triggering_element['#array_parents'])
      );
    }

    $parents = array_slice($triggering_element['#array_parents'], 0, $length);

    return NestedArray::getValue($form, $parents);
  }

  /**
   * Flags the form for rebuild.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function updateItem(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Generates the content and opens the modal.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response.
   */
  public static function openModal(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $library_ui = \Drupal::service('ai.tools_library.ui_builder')->buildUi($triggering_element['#ai_tools_library_state']);
    $dialog_options = [
      'classes' => [
        'ui-dialog' => 'ai-tools-library-widget-modal',
      ],
      'title' => t('Select tool'),
      'height' => '75%',
      'width' => '75%',
    ];
    return (new AjaxResponse())->addCommand(new OpenModalDialogCommand(t('AI tools library'), $library_ui, $dialog_options));
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if (empty($input)) {
      $tools = $element['#default_value'] ?? [];
    }
    else {
      $tools = static::processToolsIds($input);
    }

    return $tools;
  }

  /**
   * Processes tools IDs.
   *
   * @param array|string $ids
   *   Processes tools IDs as they are returned from the tools library. Array
   *   of IDs or a comma-delimited string is supported.
   *
   * @return string[]
   *   Array of tools ids.
   */
  public static function processToolsIds($ids) {
    if (!is_array($ids)) {
      $ids = array_filter(explode(',', $ids));
    }

    return $ids;
  }

}
