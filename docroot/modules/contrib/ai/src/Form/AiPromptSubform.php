<?php

namespace Drupal\ai\Form;

use Drupal\ai\Entity\AiPrompt;
use Drupal\ai\Entity\AiPromptInterface;
use Drupal\ai\Entity\AiPromptTypeInterface;
use Drupal\ai\Utility\Textarea;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * This subform helper contains the shared elements of the AI Prompt creation.
 *
 * At this time, it is used in the following places:
 * - \Drupal\ai\Element\AiPromptTableSelect - Allows inline creation of prompts.
 * - \Drupal\ai\Form\AiPromptForm - The full entity form.
 */
class AiPromptSubform {

  use StringTranslationTrait;

  /**
   * The prompt being created or edited.
   *
   * @var \Drupal\ai\Entity\AiPromptInterface
   */
  protected AiPromptInterface $aiPrompt;

  /**
   * The prompt type.
   *
   * @var \Drupal\ai\Entity\AiPromptTypeInterface
   */
  protected AiPromptTypeInterface $aiPromptType;

  /**
   * Constructs a new AI Prompt Subform.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    protected FormBuilderInterface $formBuilder,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
  ) {
  }

  /**
   * Build the nested name for the form field.
   *
   * @param array $form
   *   The form to retrieve parents from.
   * @param string $name
   *   The final name.
   *
   * @return string
   *   The name for the form field.
   *
   * @see \Drupal\Core\Form\FormBuilder::handleInputElement()
   */
  protected function buildName(array $form, string $name): string {
    // E.g. ai_prompt_subform[grandparent][parent][add_prompt][label].
    $parents = $form['#parents'] ?? [];
    $parents[] = $name;
    return 'ai_prompt_subform[' . implode('][', $parents) . ']';
  }

  /**
   * This conditionally sets the form element name.
   *
   * The name is needed for the AI Prompt Element, but should be left to
   * auto-generation for the entity form.
   *
   * @param array $form
   *   The full form.
   * @param string $key
   *   The form element key.
   * @param bool $set_name
   *   Whether to set the name or not.
   *
   * @return array
   *   The updated form.
   */
  protected function setFormElementName(array $form, string $key, bool $set_name): array {
    if (!$set_name) {
      return $form;
    }
    $form[$key]['#name'] = $this->buildName($form, $key);
    return $form;
  }

  /**
   * Build the sub-form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The sub-form state.
   * @param \Drupal\ai\Entity\AiPromptInterface $ai_prompt
   *   The prompt being created or edited.
   * @param \Drupal\ai\Entity\AiPromptTypeInterface $ai_prompt_type
   *   The prompt type being created or edited.
   * @param bool $set_name
   *   The Form API Element must set the name; whereas the entity form is fine.
   *
   * @return array
   *   The subform.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    AiPromptInterface $ai_prompt,
    AiPromptTypeInterface $ai_prompt_type,
    bool $set_name = FALSE,
  ) {
    $this->aiPrompt = $ai_prompt;
    $this->aiPromptType = $ai_prompt_type;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->aiPrompt->isNew() ? '' : $this->aiPrompt->label(),
      '#description' => $this->t('Label for the AI Prompt.'),
    ];
    $form = $this->setFormElementName($form, 'label', $set_name);
    $source = ['label'];

    if (isset($form['#parents'])) {
      $source = array_merge($form['#parents'], ['add_form', 'subform', 'label']);
    }

    $form['id'] = [
      '#type' => 'machine_name',
      '#required' => FALSE,
      '#default_value' => $this->aiPrompt->id(),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'source' => $source,
      ],
      '#disabled' => !$this->aiPrompt->isNew(),
      '#attributes' => [
        'data-prompt-type' => $this->aiPromptType->id(),
      ],
    ];
    $form = $this->setFormElementName($form, 'id', $set_name);
    $editor_id = hash('sha256', $this->aiPromptType->id() . $this->time->getRequestTime() . mt_rand());
    $typeahead_config = $this->getTypeAheadConfig();
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#default_value' => $this->buildDefaultPrompt(),
      '#description' => $this->buildPromptDescription(),
      '#attributes' => [
        'data-context' => $this->aiPromptType->id(),
        'data-mdxeditor' => $editor_id,
      ],
      '#attached' => [
        'drupalSettings' => [
          'mdxeditor' => [
            $editor_id => [
              'plugins' => [
                'typeaheadPlugin' => $typeahead_config,
              ],
            ],
          ],
        ],
      ],
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];
    $form = $this->setFormElementName($form, 'prompt', $set_name);

    if ($variables = $this->aiPromptType->getVariables()) {
      $form['variables_wrapper'] = [
        '#type' => 'details',
        '#title' => $this->t('Variables'),
        '#open' => TRUE,
      ];
      $form['variables_wrapper']['variables'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Variable name'),
          $this->t('Details'),
          $this->t('Required'),
        ],
        '#caption' => $this->t('The variable name is the string that gets replaced.'),
      ];
      foreach ($variables as $i => $variable) {
        $form['variables_wrapper']['variables'][$i]['name'] = [
          '#plain_text' => '{' . $variable['name'] . '}',
        ];
        $form['variables_wrapper']['variables'][$i]['help_text'] = [
          '#plain_text' => $variable['help_text'],
        ];
        $form['variables_wrapper']['variables'][$i]['required'] = [
          '#plain_text' => $variable['required'] ? $this->t('Required') : $this->t('Optional'),
        ];
      }
    }

    if ($tokens = $this->aiPromptType->getTokens()) {
      $form['tokens_wrapper'] = [
        '#type' => 'details',
        '#title' => $this->t('Tokens'),
        '#open' => TRUE,
      ];
      $form['tokens_wrapper']['tokens'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Token name'),
          $this->t('Details'),
          $this->t('Required'),
        ],
        '#caption' => $this->t('The tokens listed will be automatically evaluated using the Drupal Token system.'),
      ];
      foreach ($tokens as $i => $token) {
        $form['tokens_wrapper']['tokens'][$i]['name'] = [
          '#plain_text' => '[' . $token['name'] . ']',
        ];
        $form['tokens_wrapper']['tokens'][$i]['help_text'] = [
          '#plain_text' => $token['help_text'],
        ];
        $form['tokens_wrapper']['tokens'][$i]['required'] = [
          '#plain_text' => $token['required'] ? $this->t('Required') : $this->t('Optional'),
        ];
      }
    }
    return $form;
  }

  /**
   * Helper function to check whether an AI Prompt entity exists.
   *
   * This is used as the 'exists' callback for the machine_name form element.
   *
   * @param string $id
   *   The machine name value submitted by the user.
   * @param array $element
   *   The complete form element array for the machine name field.
   *
   * @return bool
   *   TRUE if the machine name already exists, FALSE otherwise.
   */
  public function exists(string $id, array $element): bool {
    // Editing.
    if ($id === $this->aiPrompt->id()) {
      return FALSE;
    }

    // Prevent new from matching an existing.
    $entity = AiPrompt::load($element['#attributes']['data-prompt-type'] . "__" . $id);
    return !empty($entity);
  }

  /**
   * Build default prompt text to populate the textarea.
   *
   * @return string
   *   The default prompt value.
   */
  protected function buildDefaultPrompt(): string {
    if ($prompt = $this->aiPrompt->getPrompt()) {
      return $prompt;
    }

    $prompt_parts = [];
    foreach ($this->aiPromptType->getVariables() as $variable) {
      $prompt_parts[] = '`{{ ' . $variable['name'] . ' }}`';
    }
    foreach ($this->aiPromptType->getTokens() as $token) {
      $prompt_parts[] = '`[' . $token['name'] . ']`';
    }
    return implode("\n", $prompt_parts);
  }

  /**
   * Build the field description for the prompt type.
   *
   * @return string
   *   The description.
   */
  protected function buildPromptDescription(): string {
    if ($this->aiPromptType->getTokens() && $this->aiPromptType->getVariables()) {
      return (string) $this->t('Enter the prompt text here, filling in the Variables and Tokens using the requirements and suggestions in the details below.');
    }
    elseif ($this->aiPromptType->getTokens()) {
      return (string) $this->t('Enter the prompt text here, filling in the Tokens using the requirements and suggestions in the details below.');
    }
    elseif ($this->aiPromptType->getVariables()) {
      return (string) $this->t('Enter the prompt text here, filling in the Variables using the requirements and suggestions in the details below.');
    }
    return (string) $this->t('Enter the prompt text here.');
  }

  /**
   * Run normal validation on the subform.
   *
   * @param array $form
   *   The subform.
   * @param array $values
   *   The form state values.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state to set errors on.
   */
  public function validateForm(array &$form, array $values, FormStateInterface $form_state): void {
    // We possibly need to target the nested subform name rather than just the
    // name in order to correctly place errors.
    $base_name = '';
    if (!empty($form['#parents'])) {
      $base_name .= implode('][', $form['#parents']) . '][';
    }

    // If there is both no label and no ID, skip.
    if (empty($values['label']) && empty($values['id'])) {
      return;
    }

    // Now run through the validations. We need to do these manually to handle
    // the AiPrompt form element side of things.
    if (empty($values['label'])) {
      $form_state->setErrorByName($base_name . 'label', $this->t('Label is required.'));
    }
    if (empty($values['id'])) {
      $form_state->setErrorByName($base_name . 'id', $this->t('Machine name is required.'));
    }
    elseif ($this->exists($values['id'], $form['id'])) {
      $form_state->setErrorByName($base_name . 'id', $this->t('The machine-readable name is already in use. It must be unique.'));
    }

    // Validate prompt.
    if (empty($values['prompt'])) {
      $form_state->setErrorByName($base_name . 'prompt', $this->t('Please enter a prompt text.'));
    }

    if (isset($this->aiPromptType) && $variables = $this->aiPromptType->getVariables()) {
      foreach ($variables as $variable) {
        $full_name = '{' . $variable['name'] . '}';
        if ($variable['required'] && !str_contains($values['prompt'], $full_name)) {
          $form_state->setErrorByName($base_name . 'prompt', $this->t('The prompt text must contain "@variable".', [
            '@variable' => $full_name,
          ]));
        }
      }
    }

    if (isset($this->aiPromptType) && $tokens = $this->aiPromptType->getTokens()) {
      foreach ($tokens as $token) {
        $full_name = '{' . $token['name'] . '}';
        if ($token['required'] && !str_contains($values['prompt'], $full_name)) {
          $form_state->setErrorByName($base_name . 'prompt', $this->t('The prompt text must contain "@token".', [
            '@token' => $full_name,
          ]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Do nothing here, we need to save independently at the two
    // implementations of this subform.
  }

  /**
   * Collects typeahead types and values.
   */
  protected function getTypeAheadConfig() {
    $typeahead_config = [];
    $token_values = [];
    foreach ($this->aiPromptType->getTokens() as $token) {
      $token_values[] = [
        'value' => '[' . $token['name'] . ']',
        'displayValue' => $token['help_text'],
        'description' => $token['required'] ? $this->t('This token is required. Prompt validation will check it.') : '',
      ];
    }
    if (!empty($token_values)) {
      $typeahead_config = [
        'types' => [
          [
            'name' => 'tokens',
            'trigger' => '[',
            'values' => $token_values,
          ],
        ],
      ];
    }
    $variable_values = [];
    foreach ($this->aiPromptType->getVariables() as $variable) {
      $variable_values[] = [
        'value' => '{{ ' . $variable['name'] . ' }}',
        'displayValue' => $variable['help_text'],
        'description' => $variable['required'] ? $this->t('This variable is required. Prompt validation will check it.') : '',
      ];
    }
    if (!empty($variable_values)) {
      $typeahead_config = NestedArray::mergeDeep($typeahead_config, [
        'types' => [
          [
            'name' => 'variables',
            'trigger' => '{{',
            'values' => $variable_values,
          ],
        ],
      ]);
    }
    return $typeahead_config;
  }

}
