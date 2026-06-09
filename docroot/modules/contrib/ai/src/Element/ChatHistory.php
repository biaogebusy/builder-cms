<?php

namespace Drupal\ai\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\FormElementBase;

/**
 * Provides a form element for chat history.
 *
 * @see \Drupal\Core\Render\Element\FormElementBase
 */
#[FormElement('chat_history')]
class ChatHistory extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#input' => TRUE,
      '#tree' => TRUE,
      '#process' => [
        [static::class, 'processChatHistory'],
      ],
      '#element_validate' => [],
      '#default_value' => [],
      '#chat_history_add_more' => TRUE,
      '#rows' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function processChatHistory(&$element, FormStateInterface $form_state, &$complete_form) {
    // Attach javascript.
    $element['#attached']['library'][] = 'ai/chat_history_element';

    $trigger = $form_state->getTriggeringElement();
    $name = $element['#name'] ?? 'chat';
    $wrapper_id = $element['#wrapper_id'] ?? Html::getUniqueId('chat-history-wrapper');

    // Get value or default.
    $value = $element['#value'] ?? [];
    // Check removal.
    $remove_index = $form_state->get('chat_history_remove_index_' . $element['#parents'][0]);
    if (isset($remove_index)) {
      unset($value[$remove_index]);
      $value = array_values($value);
      $form_state->set('chat_history_remove_index_' . $element['#parents'][0], NULL);
    }

    $count = count($value);

    // Handle add more.
    if ($trigger && $trigger['#name'] === "{$name}_add_more") {
      $count++;
    }

    // Store count for rebuilding.
    $form_state->set("{$name}_count", $count);
    $element['#tree'] = TRUE;

    $element['#prefix'] = '<div id="' . $wrapper_id . '" class="chat-history-wrapper">';
    $element['#suffix'] = '</div>';

    // Add fields for each message.
    for ($i = 0; $i < $count; $i++) {
      $element[$i] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'chat-message',
            'chat-history-item',
            'chat-history-item-' . ($value[$i]['role'] ?? 'user'),
            'draggable',
          ],
          'draggable' => 'true',
        ],
      ];
      $element[$i]['#chat_index'] = $i;

      $element[$i]['remove'] = [
        '#type' => 'submit',
        '#name' => "chat_remove_{$i}",
        '#value' => '',
        '#submit' => [[static::class, 'removeRowSubmit']],
        '#ajax' => [
          'callback' => [static::class, 'ajaxCallback'],
          'wrapper' => $wrapper_id,
        ],
        '#attributes' => [
          'class' => ['ai-icon-button', 'ai-icon--trash'],
          'title' => t('Remove message'),
          'aria-label' => t('Remove message'),
        ],
        '#limit_validation_errors' => [],
      ];

      $element[$i]['role'] = [
        '#type' => 'select',
        '#title' => t('Role'),
        '#options' => [
          'user' => t('User'),
          'assistant' => t('Assistant'),
          'tool' => t('Tool'),
        ],
        '#attributes' => [
          'class' => ['chat-history-role'],
        ],
        '#default_value' => $value[$i]['role'] ?? '',
        '#value' => $value[$i]['role'] ?? '',
        '#ajax' => [
          'callback' => [static::class, 'ajaxCallback'],
          'wrapper' => $wrapper_id,
          'event' => 'change',
        ],
      ];
      $element[$i]['content'] = [
        '#type' => 'textarea',
        '#title' => t('Content'),
        '#default_value' => $value[$i]['content'] ?? '',
        '#value' => $value[$i]['content'] ?? '',
        '#rows' => 2,
        '#attributes' => [
          'class' => ['chat-history-content'],
          'placeholder' => t('Enter message content'),
        ],
      ];

      // Get current role value from form state or default.
      $current_role = $form_state->getValue(array_merge($element['#parents'], [$i, 'role'])) ?? $value[$i]['role'] ?? '';

      // Add tool_calls for assistant messages.
      if ($current_role === 'assistant') {
        $element[$i]['tool_calls'] = [
          '#type' => 'details',
          '#title' => t('Tool calls'),
          '#open' => TRUE,
          '#attributes' => ['class' => ['tool-calls-details']],
        ];

        $tool_calls = $value[$i]['tool_calls'] ?? [];

        // Check for tool call removal - simplified approach.
        for ($j = 0; $j < count($tool_calls); $j++) {
          $remove_key = "tool_call_remove_index_{$i}_{$j}";
          if ($form_state->get($remove_key)) {
            unset($tool_calls[$j]);
            $tool_calls = array_values($tool_calls);
            $form_state->set($remove_key, FALSE);
            break;
          }
        }

        $tool_calls_count = count($tool_calls);

        // Handle add more tool calls.
        if ($trigger && $trigger['#name'] === "tool_calls_add_more_{$i}") {
          $tool_calls_count++;
        }

        // Store count for rebuilding.
        $form_state->set("tool_calls_count_{$i}", $tool_calls_count);

        for ($j = 0; $j < $tool_calls_count; $j++) {
          $element[$i]['tool_calls'][$j] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['tool-call-item']],
          ];

          $element[$i]['tool_calls'][$j]['tool_call_id'] = [
            '#type' => 'textfield',
            '#title' => t('Tool Call ID'),
            '#default_value' => $tool_calls[$j]['tool_call_id'] ?? '',
            '#value' => $tool_calls[$j]['tool_call_id'] ?? '',
            '#attributes' => [
              'class' => ['tool-call-id'],
              'placeholder' => t('Tool call ID'),
            ],
          ];

          $element[$i]['tool_calls'][$j]['function_name'] = [
            '#type' => 'textfield',
            '#title' => t('Function Name'),
            '#default_value' => $tool_calls[$j]['function_name'] ?? '',
            '#value' => $tool_calls[$j]['function_name'] ?? '',
            '#attributes' => [
              'class' => ['function-name'],
              'placeholder' => t('Function name'),
            ],
          ];

          $element[$i]['tool_calls'][$j]['function_input'] = [
            '#type' => 'textarea',
            '#title' => t('Function Input'),
            '#default_value' => $tool_calls[$j]['function_input'] ?? '',
            '#value' => $tool_calls[$j]['function_input'] ?? '',
            '#rows' => 2,
            '#attributes' => [
              'class' => ['function-input'],
              'placeholder' => t('JSON or parameters to pass'),
            ],
          ];

          $element[$i]['tool_calls'][$j]['remove_tool_call'] = [
            '#type' => 'submit',
            '#name' => "tool_call_remove_{$i}_{$j}",
            '#value' => '',
            '#submit' => [[static::class, 'removeToolCallSubmit']],
            '#ajax' => [
              'callback' => [static::class, 'ajaxCallback'],
              'wrapper' => $wrapper_id,
            ],
            '#attributes' => [
              'class' => ['ai-icon-button', 'ai-icon--trash'],
              'title' => t('Remove tool call'),
              'aria-label' => t('Remove tool call'),
            ],
            '#limit_validation_errors' => [],
          ];
        }

        $element[$i]['tool_calls']['add_more_tool_call'] = [
          '#type' => 'submit',
          '#name' => "tool_calls_add_more_{$i}",
          '#value' => t('Add Tool Call'),
          '#submit' => [[static::class, 'addMoreToolCallSubmit']],
          '#ajax' => [
            'callback' => [static::class, 'ajaxCallback'],
            'wrapper' => $wrapper_id,
          ],
          '#attributes' => [
            'class' => ['add-more-tool-call-button'],
            'title' => t('Add another tool call'),
            'aria-label' => t('Add another tool call'),
          ],
          '#limit_validation_errors' => [],
        ];
      }

      // Add tool_call_id_reference for tool messages.
      if ($current_role === 'tool') {
        $element[$i]['tool_call_id_reference'] = [
          '#type' => 'textfield',
          '#title' => t('Tool Call ID Reference'),
          '#default_value' => $value[$i]['tool_call_id_reference'] ?? '',
          '#value' => $value[$i]['tool_call_id_reference'] ?? '',
          '#attributes' => [
            'class' => ['tool-call-id-reference'],
            'placeholder' => t('Related tool call ID'),
          ],
        ];
      }

      $element[$i]['weight'] = [
        '#type' => 'number',
        '#default_value' => $i,
        '#title' => t('Weight'),
        '#value' => $i,
        '#attributes' => [
          'class' => ['chat-history-weight'],
        ],
      ];
    }

    // Add "Add more" button.
    $element['add_more'] = [
      '#type' => 'submit',
      '#name' => "{$name}_add_more",
      '#value' => t('Add another message'),
      '#submit' => [[static::class, 'addMoreSubmit']],
      '#ajax' => [
        'callback' => [static::class, 'ajaxCallback'],
        'wrapper' => $wrapper_id,
      ],
      '#attributes' => [
        'class' => ['add-more-button'],
        'title' => t('Add another message'),
        'aria-label' => t('Add another message'),
      ],
      '#limit_validation_errors' => [],
      '#submit_handlers' => [],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function addMoreSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function removeRowSubmit(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();

    if (preg_match('/chat_remove_(\d+)/', $trigger['#name'], $matches)) {
      $remove_index = (int) $matches[1];
      $wrapper_id = self::getWrapperId($form, $form_state);
      $form_state->set('chat_history_remove_index_' . $wrapper_id, $remove_index);
      $form_state->setRebuild(TRUE);
    }
  }

  /**
   * Submit handler for adding more tool calls.
   */
  public static function addMoreToolCallSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for removing tool calls.
   */
  public static function removeToolCallSubmit(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();

    if (preg_match('/tool_call_remove_(\d+)_(\d+)/', $trigger['#name'], $matches)) {
      $message_index = (int) $matches[1];
      $tool_call_index = (int) $matches[2];
      $form_state->set("tool_call_remove_index_{$message_index}_{$tool_call_index}", TRUE);
      $form_state->setRebuild(TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function ajaxCallback(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();

    // This is the full path to the triggered button.
    $parents = $trigger['#array_parents'];

    // Remove the 'add_more' part to get the parent chat_history element.
    array_pop($parents);

    // Now walk the form to find the element.
    $element = &$form;
    foreach ($parents as $key) {
      if (is_numeric($key)) {
        break;
      }
      if (!isset($element[$key])) {
        return ['#markup' => t('Error: chat element not found.')];
      }
      $element = &$element[$key];
    }

    return $element;
  }

  /**
   * Helper function to get the wrapper id.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The wrapper id.
   */
  public static function getWrapperId(array $form, FormStateInterface $form_state): string {
    $trigger = $form_state->getTriggeringElement();
    // This is the full path to the triggered button.
    $parents = $trigger['#array_parents'];

    // Remove the 'add_more' part to get the parent chat_history element.
    array_pop($parents);

    // Now walk the form to find the element.
    $element = &$form;
    $string = '';
    foreach ($parents as $key) {
      if (is_numeric($key)) {
        break;
      }
      if (!isset($element[$key])) {
        return '';
      }
      $element = &$element[$key];
      $string = $key;
    }
    return $string;
  }

}
