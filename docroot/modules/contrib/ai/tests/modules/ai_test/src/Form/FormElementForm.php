<?php

namespace Drupal\ai_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form to showcase the AI form elements.
 */
class FormElementForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_test_form_element_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['chat_history'] = [
      '#type' => 'chat_history',
      '#title' => $this->t('Chat History'),
      '#description' => $this->t('A form element for managing chat history.'),
      '#default_value' => [
        [
          'role' => 'user',
          'content' => 'Hello, how can you help me?',
        ],
      ],
    ];

    $form['hr_1'] = [
      '#type' => 'markup',
      '#markup' => '<hr>',
    ];

    $request = $this->getRequest();

    $form['provider_config'] = [
      '#type' => 'ai_provider_configuration',
      '#title' => $this->t('AI Provider Configuration'),
      '#description' => $this->t('Select an AI provider and model.'),
      '#operation_type' => $request->query->get('operation_type', 'chat') ?? 'chat',
      '#advanced_config' => $request->query->get('advanced_config', TRUE) ?? FALSE,
      '#default_provider_allowed' => $request->query->get('default_provider_allowed', TRUE) ?? FALSE,
      '#required' => FALSE,
    ];

    $form['hr_2'] = [
      '#type' => 'markup',
      '#markup' => '<hr>',
    ];

    $form['ai_tools_library'] = [
      '#type' => 'ai_tools_library',
      '#title' => $this->t('AI Tools Library'),
      '#description' => $this->t('A form element for selecting AI tools.'),
      '#default_value' => '',
    ];

    $form['hr_3'] = [
      '#type' => 'markup',
      '#markup' => '<hr>',
    ];

    $form['json_schema'] = [
      '#type' => 'ai_json_schema',
      '#title' => $this->t('JSON Schema'),
      '#description' => $this->t('Enter a valid JSON schema for structured content.'),
      '#default_value' => '{"type": "object", "properties": {}}',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['result'] = [
      '#type' => 'markup',
      '#markup' => '<div id="form-result"></div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $chat_history = $form_state->getValue('chat_history');
    $provider_config = $form_state->getValue('provider_config');
    $tools = $form_state->getValue('ai_tools_library');
    $json_schema = $form_state->getValue('json_schema');
    $this->messenger()->addStatus($this->t('Form submitted with the following values: <br>Chat History - @chat_history<br>Provider Config - @provider_config<br>AI Tools Library - @tools<br>JSON Schema - @json_schema', [
      '@provider_config' => print_r($provider_config, TRUE),
      '@chat_history' => print_r($chat_history, TRUE),
      '@tools' => print_r($tools, TRUE),
      '@json_schema' => print_r($json_schema, TRUE),
    ]));
  }

}
