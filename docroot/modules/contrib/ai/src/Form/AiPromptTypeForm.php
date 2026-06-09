<?php

namespace Drupal\ai\Form;

use Drupal\ai\Entity\AiPromptTypeInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for AI Prompt Type add and edit forms.
 */
class AiPromptTypeForm extends EntityForm {

  /**
   * Constructs an AI Prompt Type Form object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $prompt_type = $this->entity;
    assert($prompt_type instanceof AiPromptTypeInterface);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Type label'),
      '#maxlength' => 255,
      '#default_value' => $prompt_type->label(),
      '#description' => $this->t('The label for this prompt type.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $prompt_type->id(),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
      ],
      '#disabled' => !$prompt_type->isNew(),
    ];

    // Variables.
    $form['variables_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Variables'),
      '#attributes' => [
        'id' => 'js-variables-wrapper',
      ],
    ];

    // Set default counts, open the details element if there is one or more
    // already set or being set.
    $default_variables = $prompt_type->getVariables();
    $variable_count = $form_state->get('variable_count');
    if ($variable_count === NULL) {
      $form_state->set('variable_count', (count($default_variables) + 1));
      $variable_count = $form_state->get('variable_count');
    }
    else {
      $form['variables_wrapper']['#open'] = TRUE;
    }
    if (count($default_variables) > 0) {
      $form['variables_wrapper']['#open'] = TRUE;
    }

    $form['variables_wrapper']['variables'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Variable name'),
        $this->t('Help text'),
        $this->t('Required'),
        $this->t('Actions'),
      ],
      '#caption' => $this->t('The variable name is the string that gets replaced. It is automatically wrapped with {}. Mark as required to ensure the prompt has this variable before it can be saved. Add help texts to help the prompt manager understand how to use the variable.'),
    ];
    for ($i = 0; $i < $variable_count; $i++) {

      // Prompt name.
      $form['variables_wrapper']['variables'][$i]['name'] = [
        '#type' => 'textfield',
        '#maxlength' => 255,
        '#size' => 20,
        '#source' => NULL,
      ];
      if (!empty($default_variables[$i]['name'])) {
        $form['variables_wrapper']['variables'][$i]['name']['#default_value'] = $default_variables[$i]['name'];
      }

      // Help text.
      $form['variables_wrapper']['variables'][$i]['help_text'] = [
        '#type' => 'textfield',
        '#maxlength' => 255,
        '#size' => 40,
      ];
      if (!empty($default_variables[$i]['help_text'])) {
        $form['variables_wrapper']['variables'][$i]['help_text']['#default_value'] = $default_variables[$i]['help_text'];
      }

      // Required or optional.
      $form['variables_wrapper']['variables'][$i]['required'] = [
        '#type' => 'checkbox',
      ];
      if (!empty($default_variables[$i]['required'])) {
        $form['variables_wrapper']['variables'][$i]['required']['#default_value'] = $default_variables[$i]['required'];
      }

      // Actions.
      $form['variables_wrapper']['variables'][$i]['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'remove_variable_' . $i,
        '#submit' => ['::removeVariableCallback'],
        '#ajax' => [
          'callback' => '::variablesCallback',
          'wrapper' => 'js-variables-wrapper',
        ],
        '#limit_validation_errors' => [],
      ];
    }
    $form['variables_wrapper']['add_variable'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another'),
      '#name' => 'add_variable',
      '#submit' => ['::addVariableCallback'],
      '#ajax' => [
        'callback' => '::variablesCallback',
        'wrapper' => 'js-variables-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    // Tokens.
    $form['tokens_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Tokens'),
      '#attributes' => [
        'id' => 'js-tokens-wrapper',
      ],
    ];

    // Set default counts, open the details element if there is one or more
    // already set or being set.
    $default_tokens = $prompt_type->getTokens();
    $token_count = $form_state->get('token_count');
    if ($token_count === NULL) {
      $form_state->set('token_count', (count($default_tokens) + 1));
      $token_count = $form_state->get('token_count');
    }
    else {
      $form['tokens_wrapper']['#open'] = TRUE;
    }
    if (count($default_tokens) > 0) {
      $form['tokens_wrapper']['#open'] = TRUE;
    }

    $form['tokens_wrapper']['tokens'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Token name'),
        $this->t('Help text'),
        $this->t('Required'),
        $this->t('Actions'),
      ],
      '#caption' => $this->t('While variables are direct replacements (effectively <code>str_replace()</code>), Tokens instead leverage the Token system in Drupal Core and therefore replace using the <code>Token::replace()</code> service in Drupal. Developers should ensure that any tokens required or suggested here are passed the appropriate context and run through <code>Token::replace()</code> themselves in the areas their prompts are implement.'),
    ];
    for ($i = 0; $i < $token_count; $i++) {

      // Prompt name.
      $form['tokens_wrapper']['tokens'][$i]['name'] = [
        '#type' => 'textfield',
        '#maxlength' => 255,
        '#size' => 20,
        '#source' => NULL,
      ];
      if (!empty($default_tokens[$i]['name'])) {
        $form['tokens_wrapper']['tokens'][$i]['name']['#default_value'] = $default_tokens[$i]['name'];
      }

      // Help text.
      $form['tokens_wrapper']['tokens'][$i]['help_text'] = [
        '#type' => 'textfield',
        '#maxlength' => 255,
        '#size' => 40,
      ];
      if (!empty($default_tokens[$i]['help_text'])) {
        $form['tokens_wrapper']['tokens'][$i]['help_text']['#default_value'] = $default_tokens[$i]['help_text'];
      }

      // Required or optional.
      $form['tokens_wrapper']['tokens'][$i]['required'] = [
        '#type' => 'checkbox',
      ];
      if (!empty($default_tokens[$i]['required'])) {
        $form['tokens_wrapper']['tokens'][$i]['required']['#default_value'] = $default_tokens[$i]['required'];
      }

      // Actions.
      $form['tokens_wrapper']['tokens'][$i]['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'remove_token_' . $i,
        '#submit' => ['::removeTokenCallback'],
        '#ajax' => [
          'callback' => '::tokensCallback',
          'wrapper' => 'js-tokens-wrapper',
        ],
        '#limit_validation_errors' => [],
      ];
    }
    $form['tokens_wrapper']['add_token'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another'),
      '#name' => 'add_token',
      '#submit' => ['::addTokenCallback'],
      '#ajax' => [
        'callback' => '::tokensCallback',
        'wrapper' => 'js-tokens-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * AJAX callback to refresh the variables section.
   */
  public function variablesCallback(array &$form, FormStateInterface $form_state) {
    return $form['variables_wrapper'];
  }

  /**
   * Add another variable.
   */
  public function addVariableCallback(array &$form, FormStateInterface $form_state) {
    $variable_count = $form_state->get('variable_count');
    $form_state->set('variable_count', $variable_count + 1);
    $form_state->setRebuild();
  }

  /**
   * Remove a variable.
   */
  public function removeVariableCallback(array &$form, FormStateInterface $form_state) {

    // Determine the row to remove.
    $trigger = $form_state->getTriggeringElement();
    $name = (string) $trigger['#name'];
    $index_to_remove = str_starts_with($name, 'remove_variable_') ? (int) substr($name, strlen('remove_variable_')) : NULL;

    // Remove the row from the current user input.
    $user_input = $form_state->getUserInput();
    if (isset($user_input['variables'][$index_to_remove])) {
      unset($user_input['variables'][$index_to_remove]);
      $user_input['variables'] = array_values($user_input['variables']);
      $form_state->setUserInput($user_input);
    }

    // Reduce the number of rows.
    $token_count = $form_state->get('variable_count');
    if ($token_count > 1) {
      $form_state->set('variable_count', $token_count - 1);
    }

    $form_state->setRebuild();
  }

  /**
   * AJAX callback to refresh the tokens section.
   */
  public function tokensCallback(array &$form, FormStateInterface $form_state) {
    return $form['tokens_wrapper'];
  }

  /**
   * Add another token.
   */
  public function addTokenCallback(array &$form, FormStateInterface $form_state) {
    $token_count = $form_state->get('token_count');
    $form_state->set('token_count', $token_count + 1);
    $form_state->setRebuild();
  }

  /**
   * Remove a token.
   */
  public function removeTokenCallback(array &$form, FormStateInterface $form_state) {

    // Determine the row to remove.
    $trigger = $form_state->getTriggeringElement();
    $name = (string) $trigger['#name'];
    $index_to_remove = str_starts_with($name, 'remove_token_') ? (int) substr($name, strlen('remove_token_')) : NULL;

    // Remove the row from the current user input.
    $user_input = $form_state->getUserInput();
    if (isset($user_input['tokens'][$index_to_remove])) {
      unset($user_input['tokens'][$index_to_remove]);
      $user_input['tokens'] = array_values($user_input['tokens']);
      $form_state->setUserInput($user_input);
    }

    // Reduce the number of rows.
    $token_count = $form_state->get('token_count');
    if ($token_count > 1) {
      $form_state->set('token_count', $token_count - 1);
    }

    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Validate variables.
    if ($variables = $form_state->getValue('variables')) {
      $names = [];
      foreach ($variables as $key => $variable) {

        // Name is required if help text or required are filled in.
        if (
          empty($variable['name'])
          && (!empty($variable['help_text']) || !empty($variable['required']))
        ) {
          $message = $this->t('The name cannot be blank if you are adding help text or setting required on a Variable.');
          $form_state->setErrorByName('variables][' . $key . '][name', $message);
        }

        if (!empty($variable['name'])) {

          // Ensure alphabetical.
          if (!ctype_alnum($variable['name'])) {
            $message = $this->t('Please only use alpha-numeric characters and leave out any braces.');
            $form_state->setErrorByName('variables][' . $key . '][name', $message);
          }

          // Prevent duplicates.
          if (in_array($variable['name'], $names)) {
            $message = $this->t('Duplicate variable names are not allowed.');
            $form_state->setErrorByName('variables][' . $key . '][name', $message);
          }
          else {
            $names[] = $variable['name'];
          }
        }
      }
    }

    // Validate tokens.
    if ($tokens = $form_state->getValue('tokens')) {
      $names = [];
      foreach ($tokens as $key => $token) {

        // Name is required if help text or required are filled in.
        if (
          empty($token['name'])
          && (!empty($token['help_text']) || !empty($token['required']))
        ) {
          $message = $this->t('The name cannot be blank if you are adding help text or setting required on a Variable.');
          $form_state->setErrorByName('tokens][' . $key . '][name', $message);
        }

        if (!empty($token['name'])) {

          // Ensure alphabetical.
          $plain_token = str_replace(':', '', $token['name']);
          if (!ctype_alnum($plain_token)) {
            $message = $this->t('Please only use alpha-numeric characters and colons (:), and leave out any braces.');
            $form_state->setErrorByName('tokens][' . $key . '][name', $message);
          }

          // Prevent duplicates.
          if (in_array($token['name'], $names)) {
            $message = $this->t('Duplicate token names are not allowed.');
            $form_state->setErrorByName('tokens][' . $key . '][name', $message);
          }
          else {
            $names[] = $token['name'];
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Clean empty values.
    $variables = $form_state->getValue('variables');
    foreach ($variables as $key => $variable) {
      if (empty($variable['name'])) {
        unset($variables[$key]);
      }
    }
    $form_state->setValue('variables', $variables);
    $this->entity->set('variables', $variables);

    // Clean empty values.
    $tokens = $form_state->getValue('tokens');
    foreach ($tokens as $key => $token) {
      if (empty($token['name'])) {
        unset($tokens[$key]);
      }
    }
    $form_state->setValue('tokens', $tokens);
    $this->entity->set('tokens', $tokens);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $prompt_type = $this->entity;
    $status = $prompt_type->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('The %label AI Prompt Type has been created.', [
        '%label' => $prompt_type->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label AI Prompt Type has been updated.', [
        '%label' => $prompt_type->label(),
      ]));
    }

    $form_state->setRedirect('entity.ai_prompt_type.collection');
    return $status;
  }

  /**
   * Helper function to check whether an AI Prompt Type entity exists.
   */
  public function exists($id) {

    // Skip access check since we want to prevent creation if it exists already
    // even if it reveals that it exists - we do not want a clash.
    $entity = $this->entityTypeManager->getStorage('ai_prompt_type')->getQuery()
      ->condition('id', $id)
      ->accessCheck(FALSE)
      ->execute();
    return (bool) $entity;
  }

}
