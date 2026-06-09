<?php

namespace Drupal\ai\Element;

use Drupal\ai\Entity\AiPrompt as AiPromptEntity;
use Drupal\ai\Entity\AiPromptInterface;
use Drupal\ai\Entity\AiPromptType;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Link;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Extends the table select from core to allow Prompt select and create.
 *
 * @see \Drupal\Core\Render\Element\TableSelect
 */
#[FormElement('ai_prompt')]
class AiPrompt extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#tree' => FALSE,
      '#process' => [
        [static::class, 'processElement'],
        [static::class, 'processFinalizeElement'],
      ],
      '#after_build' => [
        [static::class, 'afterBuild'],
      ],
      '#value_callback' => [static::class, 'valueCallback'],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if (is_array($element['#default_value']) && !empty($element['#default_value']['table'])) {
      $element['#default_value'] = $element['#default_value']['table'];
    }
    if (is_string($input)) {
      return $input;
    }
    elseif (is_array($input) && !empty($input['table'])) {
      return $input['table'];
    }
    return NULL;
  }

  /**
   * Handle saving the prompt callback conversion.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The element.
   */
  public static function processFinalizeElement(array &$element, FormStateInterface $form_state, array &$complete_form): array {

    // When the value is still a keyed array from multiple nested form elements
    // we need to do some conversion first.
    $user_input = $form_state->getUserInput();
    $parents = array_merge(['ai_prompt_subform'], $element['#parents']);
    $add_prompt_values = NestedArray::getValue($user_input, $parents);
    if (!empty($add_prompt_values)) {

      // Persist the save form values so the new prompt can get created.
      $trigger_name = $user_input['_triggering_element_name'] ?? '';
      if ($trigger_name === 'save_prompt') {

        // Only pass along what we need rather than all form values.
        $form_state->setTemporaryValue($parents, $add_prompt_values);
      }
    }

    $element['#default_value'] = $element['table']['#default_value'] ?? NULL;
    return $element;
  }

  /**
   * After build callback for the element.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The element.
   */
  public static function afterBuild(array $element, FormStateInterface $form_state): array {
    // Massage the nested elements back into the single value of the selection
    // in the 'tableselect' element.
    if ($form_state->hasValue($element['#parents'])) {
      $value = $form_state->getValue($element['#parents']);
      if (is_array($value) && !empty($value['table'])) {
        $form_state->setValue($element['#parents'], $value['table']);
      }
    }
    return $element;
  }

  /**
   * Build the nested elements and subform.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The element.
   */
  public static function processElement(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $element = self::prepareElementTable($element, $form_state);

    // Title and required have been moved to the table element so states are
    // inherited and applied to the title as well.
    $element['#title'] = '';
    $element['#required'] = FALSE;

    // Mimic FormBuilder::handleInputElement() to build the name of nested
    // input elements as well. This is needed to differentiate nested form
    // inputs when there are multiple AI Prompt elements on the page.
    $element_parents = $element['#parents'];
    $name_parents = array_shift($element_parents);
    $name_parents .= '[' . implode('][', $element_parents) . ']';
    unset($element_parents);

    // Build the add new prompt and manage prompt functionality.
    $element['add_prompt'] = [
      '#type' => 'container',
    ];

    $element['#prefix'] = '<div id="js-add-prompt-wrapper-for-' . $element['#id'] . '">';
    $element['#suffix'] = '</div>';

    // Add new prompt state. This controls whether the new prompt form is shown
    // or not. Unlike in Forms, within a Form Element, the Form State
    // processing seems to be in an earlier state and gives us less flexibility
    // forcing us to rely on more rudimentary approaches like this.
    $current_prompt_state = $form_state->getValue(array_merge($element['#parents'], ['prompt_state']), FALSE);
    $element['add_prompt']['prompt_state'] = [
      '#type' => 'checkbox',
      '#name' => $name_parents . '[prompt_state]',
      '#title' => t('Hidden checkbox to track open or closed state of the Add New Prompt form.'),
      '#default_value' => $current_prompt_state,
      '#wrapper_attributes' => [
        // Hides the checkbox visually and from screen readers.
        'style' => 'display: none;',
      ],
      '#parents' => array_merge($element['#parents'], ['prompt_state']),
    ];
    if ($current_prompt_state) {
      $element['add_prompt']['prompt_state']['#attributes']['checked'] = 'checked';
    }

    // Add new prompt.
    $element['add_prompt']['add'] = [
      '#type' => 'submit',
      '#value' => t('Create new prompt'),
      '#name' => $name_parents . '[open_add_prompt]',
      '#submit' => [self::class . '::promptCallbackOpenAdd'],
      '#ajax' => [
        'callback' => [self::class, 'promptCallbackProcessOpenAdd'],
        'wrapper' => 'js-add-prompt-wrapper-for-' . $element['#id'],
        'parents' => $element['#parents'],
      ],
      '#attributes' => [
        'class' => [
          'button--small',
        ],
      ],
      '#states' => [
        'visible' => [
          // @todo allow creation of a second prompt immediately, figure out
          // how to get this to show up again after new prompt form closes.
          // This appears to be an issue with Drupal Core #states because
          // the target checkbox is actually unchecked. Checked false similarly
          // does not work. Do we need to roll our own JS file?
          ':input[name="' . $name_parents . '[prompt_state]"]' => ['unchecked' => TRUE],
        ],
      ],
      '#limit_validation_errors' => [],
      '#parents' => array_merge($element['#parents'], ['add']),
    ];

    // Manage prompts.
    $url = Url::fromRoute('entity.ai_prompt.collection', [], [
      'attributes' => [
        'class' => [
          'button',
          'button--small',
        ],
        'target' => '_blank',
      ],
    ]);
    $link = Link::fromTextAndUrl(t('Manage prompts'), $url)->toRenderable();
    $element['add_prompt']['manage_button'] = $link;
    $element['add_prompt']['manage_button']['#weight'] = 32;
    $element['add_prompt']['manage_button']['#parents'] = array_merge($element['#parents'], ['manage_button']);

    // Save new prompt.
    $element['add_prompt']['save'] = [
      '#type' => 'submit',
      '#value' => t('Save prompt'),
      '#name' => $name_parents . '[save_prompt]',
      '#submit' => [self::class . '::promptCallbackSaveAdd'],
      '#ajax' => [
        'callback' => [self::class, 'promptCallbackProcessSaveAdd'],
        'wrapper' => 'js-add-prompt-wrapper-for-' . $element['#id'],
        'parents' => $element['#parents'],
      ],
      '#attributes' => [
        'class' => [
          'button--small',
          'button--primary',
        ],
      ],
      '#states' => [
        'visible' => [
          ':input[name="' . $name_parents . '[prompt_state]"]' => ['checked' => TRUE],
        ],
      ],
      '#weight' => 30,
      '#limit_validation_errors' => [],
      '#parents' => array_merge($element['#parents'], ['save']),
    ];

    // Cancel adding new prompt.
    $element['add_prompt']['cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#name' => $name_parents . '[cancel_add_prompt]',
      '#submit' => [self::class . '::promptCallbackCancelAdd'],
      '#ajax' => [
        'callback' => [self::class, 'promptCallbackProcessCancelAdd'],
        'wrapper' => 'js-add-prompt-wrapper-for-' . $element['#id'],
        'parents' => $element['#parents'],
      ],
      '#attributes' => [
        'class' => [
          'button--small',
        ],
      ],
      '#states' => [
        'visible' => [
          ':input[name="' . $name_parents . '[prompt_state]"]' => ['checked' => TRUE],
        ],
      ],
      '#weight' => 31,
      '#limit_validation_errors' => [],
      '#parents' => array_merge($element['#parents'], ['cancel']),
    ];

    $element['add_prompt']['add_form'] = [
      '#type' => 'fieldset',
      '#title' => t('New prompt details'),
      '#states' => [
        'visible' => [
          ':input[name="' . $name_parents . '[prompt_state]"]' => ['checked' => TRUE],
        ],
      ],
      '#parents' => array_merge($element['#parents'], ['add_form']),
    ];

    // Prepare the empty form details. For now we assume there is a single
    // prompt type and if someone needs multiple, the additional complexity
    // can be contributed to the ajax form building here.
    /** @var \Drupal\ai\Form\AiPromptSubform $subform_helper */
    $subform_helper = \Drupal::service('ai.prompt_subform');
    // The ID and parents are used to nest the subform to ensure multiple
    // AI Prompt elements in the same form are unique and compatible.
    $subform = [
      '#id' => $form_state->getFormObject()->getFormId(),
      '#parents' => array_merge(($element['#parents'] ?? []), ['add_prompt']),
    ];
    $prompt_type_id = reset($element['#prompt_types']);
    $prompt_type = AiPromptType::load($prompt_type_id);
    $prompt = AiPromptEntity::create([
      'type' => $prompt_type_id,
    ]);

    // @todo Figure out why machine name JS does not work here in subform. At
    // the moment the user must manually fill in the machine name, instead of it
    // being automatically generated from the label.
    $element['add_prompt']['add_form']['subform'] = $subform_helper->buildForm(
      $subform,
      $form_state,
      $prompt,
      $prompt_type,
      TRUE,
    );

    // Build default values for the prompt subform.
    $user_input = $form_state->getUserInput();
    $parents = array_merge(['ai_prompt_subform'], $element['#parents']);
    $add_prompt_values = NestedArray::getValue($user_input, $parents);
    if (!empty($add_prompt_values['add_prompt'])) {
      foreach ($add_prompt_values['add_prompt'] as $key => $value) {
        if (!isset($element['add_prompt']['add_form']['subform'][$key]['#type'])) {
          continue;
        }
        $element['add_prompt']['add_form']['subform'][$key]['#default_value'] = $value;
      }
    }

    // Carry over form states from parent element.
    if (!empty($element['#states'])) {
      $element['table']['#states'] = NestedArray::mergeDeep($element['#states'], $element['table']['#states'] ?? []);
      $element['add_prompt']['#states'] = NestedArray::mergeDeep($element['#states'], $element['add_prompt']['#states'] ?? []);
      $element['add_prompt']['add']['#states'] = NestedArray::mergeDeep($element['#states'], $element['add_prompt']['add']['#states'] ?? []);
      $element['add_prompt']['manage_button']['#states'] = NestedArray::mergeDeep($element['#states'], $element['add_prompt']['manage_button']['#states'] ?? []);
    }

    return $element;
  }

  /**
   * Open the add new prompt form.
   */
  public static function promptCallbackOpenAdd(array &$form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#ajax']['parents'];
    $form_state->setValue(array_merge($parents, ['prompt_state']), TRUE);
    $form_state->setRebuild();
    return NestedArray::getValue($form, $parents);
  }

  /**
   * Close the add new prompt form.
   */
  public static function promptCallbackCancelAdd(array &$form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#ajax']['parents'];
    $form_state->setValue(array_merge($parents, ['prompt_state']), FALSE);
    $user_input = $form_state->getUserInput();
    NestedArray::setValue($user_input, array_merge($parents, ['prompt_state']), FALSE);
    $form_state->setUserInput($user_input);
    $form_state->setRebuild();
    return NestedArray::getValue($form, $parents);
  }

  /**
   * Save the prompt and close the add new prompt form.
   */
  public static function promptCallbackSaveAdd(array &$form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#ajax']['parents'];
    $element = NestedArray::getValue($form, $parents);

    // Validate the subform.
    /** @var \Drupal\ai\Form\AiPromptSubform $subform_helper */
    $subform_helper = \Drupal::service('ai.prompt_subform');
    $subform_state = SubformState::createForSubform($element['add_prompt']['add_form']['subform'], $form, $form_state);
    $subform_state->setLimitValidationErrors(NULL);
    $subform_state->setValidationComplete(FALSE);
    $subform_state->disableCache();

    // Get save prompt values passed along by the ::processFinalizeElement()
    // method before cleaning the form state.
    $values = [];
    $subform_parents = array_merge(['ai_prompt_subform'], $parents);
    $user_input = $form_state->getUserInput();
    if (!empty($user_input)) {
      $values = NestedArray::getValue($user_input, $subform_parents);
    }
    if (!empty($values['add_prompt'])) {
      $subform_helper->validateForm(
        $element['add_prompt']['add_form']['subform'],
        $values['add_prompt'],
        $subform_state,
      );
      if ($errors = $subform_state->getErrors()) {

        // With errors, apply the errors to the parent form.
        foreach ($errors as $name => $error_message) {
          $form_state->setErrorByName($name, $error_message);
        }
        $form_state->setValue(array_merge($parents, ['prompt_state']), TRUE);
        $form_state->setRebuild();
      }
      elseif (!empty($values['add_prompt']['id']) && !empty($values['add_prompt']['label'])) {

        // No errors, save the Prompt.
        $prompt_type_id = reset($element['#prompt_types']);
        $values['add_prompt'] = array_merge([
          'type' => $prompt_type_id,
        ], $values['add_prompt']);

        /** @var \Drupal\ai\Entity\AiPrompt $ai_prompt */
        $ai_prompt = AiPromptEntity::create($values['add_prompt']);
        $ai_prompt->save();

        // Form state is not respected on refresh, also remove from user input.
        $user_input = $form_state->getUserInput();

        // Update the selected value in the table to be the newly created
        // prompt.
        NestedArray::setValue($user_input, array_merge($parents, ['table']), $ai_prompt->id());
        NestedArray::setValue($user_input, array_merge($parents, ['prompt_state']), FALSE);
        $form_state->setUserInput($user_input);

        // Close the add new form.
        $form_state->setValue(array_merge($parents, ['prompt_state']), FALSE);
        $form_state->setRebuild();
      }
    }
    return NestedArray::getValue($form, $parents);
  }

  /**
   * Process the changes made in the prompt cancel callback.
   */
  public static function promptCallbackProcessCancelAdd(array &$form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#ajax']['parents'];
    return NestedArray::getValue($form, $parents);
  }

  /**
   * Process the changes made in the prompt save callback.
   */
  public static function promptCallbackProcessSaveAdd(array &$form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#ajax']['parents'];
    $element = NestedArray::getValue($form, $parents);

    // The form validation occurs in ::promptCallbackSaveAdd(), here we persist
    // the effects so the form stays open and status messages are shown instead.
    $user_input = $form_state->getUserInput();
    $prompt_state_parents = array_merge($parents, ['prompt_state']);
    $prompt_state = NestedArray::getValue($user_input, $prompt_state_parents);
    if ($prompt_state && $form_state->getErrors()) {

      // Render the error messages directly into the callback rather than
      // waiting for lazy builder (which will not kick in here).
      $message_list = [
        'error' => array_values($form_state->getErrors()),
      ];
      $element['add_prompt']['status_messages'] = [
        '#theme' => 'status_messages',
        '#message_list' => $message_list,
        '#status_headings' => [
          'error' => t('Error message'),
        ],
        '#weight' => -1,
      ];

      // Keep it open.
      $element['add_prompt']['prompt_state']['#attributes']['checked'] = 'checked';
    }
    elseif (isset($element['add_prompt']['prompt_state']['#attributes']['checked'])) {
      unset($element['add_prompt']['prompt_state']['#attributes']['checked']);
    }

    // If we just created a prompt, select it by default.
    $temporary = $form_state->getTemporaryValue(array_merge($parents, ['set_default_value']));
    if (!empty($temporary)) {
      $form_state->setTemporaryValue(array_merge($parents, ['set_default_value']), NULL);
    }

    return $element;
  }

  /**
   * Process the changes made in the prompt open callback.
   */
  public static function promptCallbackProcessOpenAdd(array &$form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#ajax']['parents'];
    return NestedArray::getValue($form, $parents);
  }

  /**
   * Automatically prepare a curated table for AI Prompts.
   *
   * @param array $element
   *   The original element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated element.
   */
  public static function prepareElementTable($element, FormStateInterface $form_state): array {
    $element['table'] = [
      '#type' => 'tableselect',
      '#title' => $element['#title'] ?? '',
      '#states' => $element['#states'] ?? [],
      '#multiple' => FALSE,
      '#header' => [
        'prompt_label' => t('Prompt Label'),
        'prompt' => t('Prompt'),
        'edit' => t('Edit'),
      ],
      '#options' => [],
      '#empty' => t('No prompts found'),
      '#parents' => array_merge($element['#parents'], ['table']),
      // Required for form states to behave on containers, see
      // https://www.drupal.org/project/drupal/issues/3283715.
      '#theme_wrappers' => ['form_element'],
    ];

    // Set default value for table.
    $values = $form_state->getValues();
    $value = NestedArray::getValue($values, $element['#parents']);
    if (is_string($value)) {
      $element['table']['#default_value'] = $value;
    }
    elseif (is_array($value) && !empty($value['table'])) {
      $element['table']['#default_value'] = $value['table'];
    }

    // Automatically build rows from the given types.
    if (!empty($element['#prompt_types'])) {

      /** @var \Drupal\ai\Service\AiPromptManager $manager */
      $manager = \Drupal::service('ai.prompt_manager');
      $prompts = $manager->getPromptsByTypes($element['#prompt_types']);
      $current_url = \Drupal::request()->getRequestUri();
      foreach ($prompts as $prompt) {
        if ($prompt instanceof AiPromptInterface) {

          // Build an edit button.
          $url = Url::fromRoute('entity.ai_prompt.edit_form', [
            'ai_prompt' => $prompt->id(),
          ], [
            'query' => [
              'destination' => $current_url,
            ],
            'attributes' => [
              'class' => [
                'button',
                'button--small',
              ],
              'target' => '_blank',
            ],
          ]);
          $link = Link::fromTextAndUrl('Edit', $url);

          // Add the row.
          $element['table']['#options'][$prompt->id()] = [
            'prompt_label' => $prompt->label(),
            'prompt' => Markup::create(nl2br(Html::escape($prompt->getPrompt()))),
            'edit' => $link,
          ];
        }
      }
    }
    else {
      // Developer has not filled in #prompt_types.
      $element['table']['#empty'] = t('The "#prompt_types" attribute must not be empty');
    }

    return $element;
  }

}
