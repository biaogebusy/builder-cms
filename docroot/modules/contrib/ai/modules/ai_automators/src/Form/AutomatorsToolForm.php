<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Form;

use Drupal\ai\Utility\Textarea;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_automators\Entity\AutomatorsTool;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Automators Tool form.
 */
final class AutomatorsToolForm extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\ai_automators\Entity\AutomatorsTool
   */
  protected $entity;

  /**
   * Known outliers.
   *
   * @var array
   */
  protected array $knownOutliers = [
    'id',
    'ai_automator_status',
    'revision_timestamp',
    'revision_uid',
    'revision_log',
    'created',
    'changed',
    'sticky',
    'revision_default',
  ];

  /**
   * Allowed field input fields for now.
   *
   * @var array
   */
  protected array $inputFieldsType = [
    'string_long' => 'string',
    'string' => 'string',
    'text_long' => 'string',
    'text_with_summary' => 'string',
    'text' => 'string',
    'integer' => 'integer',
    'decimal' => 'number',
    'float' => 'number',
    'boolean' => 'boolean',
    'list_string' => 'string',
    'list_integer' => 'integer',
    'list_float' => 'number',
  ];

  /**
   * Allowed field output fields for now.
   *
   * @var array
   */
  protected array $outputFieldTypes = [
    'string_long' => 'text',
    'string' => 'text',
    'text_long' => 'text',
    'text_with_summary' => 'text',
    'text' => 'text',
  ];

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {

    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [AutomatorsTool::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
      '#required' => TRUE,
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#description' => $this->t('The administrative description used for this Automator Tool. It might be used by AI, so be descriptive.'),
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    $workflow = $form_state->getValue('workflow') ?? $this->entity->get('workflow');
    // A workflow is "confirmed" (a real autocomplete selection) only when
    // its value contains the '--' separator used by the autocomplete route.
    $workflowConfirmed = ($workflow && str_contains((string) $workflow, '--')) ? '1' : '0';

    $form['workflow'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Workflow'),
      '#default_value' => $workflow,
      '#required' => TRUE,
      '#description' => $this->t('This is the AI Automator workflow that will be used for this agent. <strong>Type at least 3 characters to search</strong>. <br />Workflows are created at <a target="_blank" href=":workflows_url">:workflows_url</a> and require the <a target="_blank" href=":module_url">AI Agents</a> module to be installed.', [
        ':workflows_url' => '/admin/config/ai/automator_chain_types/add',
        ':module_url' => 'https://www.drupal.org/project/ai_agents',
      ]),
      '#ajax' => [
        'callback' => '::getWorkflow',
        'wrapper' => 'field-connections-wrapper',
        'event' => 'change',
      ],
      '#autocomplete_route_name' => 'ai_automators.autocomplete.workflows',
    ];

    $form['remove_entity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Garbage Collect'),
      '#default_value' => $this->entity->get('remove_entity'),
      '#description' => $this->t('Remove the entity from the database when the agent has successfully completed the task or a task that was closed for other reasons. <br /><strong>Obviously do not enable this for workflows where this is the end product and end storage.</strong>'),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
      '#description' => $this->t('The status of the tool. If you disable an enabled tool that is used by an agent, you will run into errors.'),
    ];

    // Outer container: serves as the AJAX wrapper and hosts the hidden
    // 'workflow_confirmed' element that drives the #states visibility below.
    $form['field_connections_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'field-connections-wrapper'],
    ];

    // This hidden element is a proper Form API element (not raw HTML), so
    // Drupal renders it correctly and the states JS can read its value.
    $form['field_connections_wrapper']['workflow_confirmed'] = [
      '#type' => 'hidden',
      '#value' => $workflowConfirmed,
    ];

    $form['field_connections_wrapper']['field_connections'] = [
      '#type' => 'details',
      '#title' => $workflowConfirmed === '1' ? $this->t('Field Connection %workflow', [
        '%workflow' => $workflow,
      ]) : $this->t('Choose workflow first'),
      '#tree' => TRUE,
      '#open' => $workflowConfirmed === '1',
      '#states' => [
        'invisible' => [
          ':input[name="workflow_confirmed"]' => ['value' => '0'],
        ],
      ],
    ];

    // PHP reference to keep child assignments concise.
    $fc = &$form['field_connections_wrapper']['field_connections'];

    if ($workflow) {
      $bundleParts = explode('--', $workflow);
      $fields = $this->getFieldsForBundle($bundleParts[0], $bundleParts[1]);
      $i = 0;
      $defaultValues = $this->getDefaultValues();
      $initial = $this->getInitialValues($fields);
      foreach ($fields as $fieldName => $field) {
        //
        // If it is a key field, we do not allow it unless its the label.
        if ((empty($field['key']) || $field['key'] === 'label') && !in_array($fieldName, $this->knownOutliers)) {
          $fc[$i] = [
            '#type' => 'container',
          ];
          $fc[$i]['header'] = [
            '#markup' => '<strong>' . $field['label'] . '</strong>',
          ];
          $fc[$i]['field_name'] = [
            '#type' => 'value',
            '#value' => $field['id'],
          ];

          $options = [
            'ignore' => $this->t('Ignore'),
          ];

          if (in_array($field['type'], array_keys($this->inputFieldsType))) {
            $options['input'] = $this->t('Input');
            $options['default'] = $this->t('Set Default Value');
          }

          if (in_array($field['type'], array_keys($this->outputFieldTypes))) {
            $options['output'] = $this->t('Output');
          }

          $fc[$i]['agent_process'] = [
            '#type' => 'select',
            '#title' => $this->t('Agent Process'),
            '#default_value' => $defaultValues[$fieldName]['agent_process'] ?? $initial[$fieldName],
            '#options' => $options,
            '#description' => $this->t('This is the type of field this is for the tool. Note that not all field types can be input or output.'),
          ];

          $fc[$i]['tool_field_type'] = [
            '#type' => 'value',
            '#value' => $this->inputFieldsType[$field['type']] ?? $field['type'],
          ];

          $fc[$i]['drupal_field_type'] = [
            '#type' => 'value',
            '#value' => $field['type'],
          ];

          $fc[$i]['input_explanation'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Input Explanation'),
            '#default_value' => $defaultValues[$fieldName]['input_explanation'] ?? '',
            '#description' => $this->t('Write a little bit of what the Manager should put as input in this field.'),
            '#attributes' => [
              'rows' => 2,
              'placeholder' => $this->t('The text that should be summarized.'),
            ],
            '#states' => [
              'visible' => [
                ':input[name="field_connections[' . $i . '][agent_process]"]' => ['value' => 'input'],
              ],
            ],
            // This property will land into core soon, see
            // https://www.drupal.org/project/drupal/issues/3202631. It can stay
            // after this is added to Drupal core.
            '#normalize_newlines' => TRUE,
            // Until that the custom value callback is needed. Should be removed
            // after the issue mentioned above is merged into core and the
            // minimum supported Drupal version includes `#normalize_newlines`
            // property.
            '#value_callback' => [Textarea::class, 'valueCallback'],
          ];

          $fc[$i]['required'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Required'),
            '#default_value' => $defaultValues[$fieldName]['required'] ?? '',
            '#description' => $this->t('Is this field required or not. Most input fields should be required.'),
            '#states' => [
              'visible' => [
                ':input[name="field_connections[' . $i . '][agent_process]"]' => ['value' => 'input'],
              ],
            ],
          ];

          $fc[$i]['output_explanation'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Output Explanation'),
            '#default_value' => $defaultValues[$fieldName]['output_explanation'] ?? '',
            '#description' => $this->t('Write a little bit of what the Manager can except to get as output in this field.'),
            '#attributes' => [
              'rows' => 2,
              'placeholder' => $this->t('The textual summary.'),
            ],
            '#states' => [
              'visible' => [
                ':input[name="field_connections[' . $i . '][agent_process]"]' => ['value' => 'output'],
              ],
            ],
            // This property will land into core soon, see
            // https://www.drupal.org/project/drupal/issues/3202631. It can stay
            // after this is added to Drupal core.
            '#normalize_newlines' => TRUE,
            // Until that the custom value callback is needed. Should be removed
            // after the issue mentioned above is merged into core and the
            // minimum supported Drupal version includes `#normalize_newlines`
            // property.
            '#value_callback' => [Textarea::class, 'valueCallback'],
          ];

          $fc[$i]['default_value'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Default Value'),
            '#default_value' => $defaultValues[$fieldName]['default_value'] ?? '',
            '#description' => $this->t('This is the default value that will be set if the tools does not have any input.'),
            '#states' => [
              'visible' => [
                ':input[name="field_connections[' . $i . '][agent_process]"]' => [
                  ['value' => 'default'],
                  'or',
                  ['value' => 'input'],
                ],
              ],
            ],
          ];

          $fc[$i]['break'] = [
            '#markup' => '<br><hr><br>',
          ];
          $i++;
        }
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $formState) {
    // Validation is optional.
    $values = $formState->getValues();
    foreach ($values['field_connections'] as $key => $rules) {
      if ($rules['agent_process'] === 'default' && empty($rules['default_value'])) {
        $formState->setErrorByName('field_connections][' . $key . '][default_value', $this->t('You need to set a default value for the field.'));
      }
      if ($rules['agent_process'] === 'input' && empty($rules['input_explanation'])) {
        $formState->setErrorByName('field_connections][' . $key . '][input_explanation', $this->t('You need to set an input explanation for the field.'));
      }
      if ($rules['agent_process'] === 'output' && empty($rules['output_explanation'])) {
        $formState->setErrorByName('field_connections][' . $key . '][output_explanation', $this->t('You need to set an output explanation for the field.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $fields = $form_state->getValue('field_connections');
    if (empty($fields)) {
      $this->entity->set('field_connections', []);
    }
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match ($result) {
        \SAVED_NEW => $this->t('Created new example %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated example %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

  /**
   * Get the default values if nothing is set.
   */
  private function getInitialValues(array $fields) {
    $defaults = [];
    foreach ($fields as $fieldName => $field) {
      if (!empty($field['config'])) {
        $defaults[$fieldName] = 'output';
      }
      elseif ($field['key'] == 'label') {
        $defaults[$fieldName] = 'default';
      }
      elseif ($field['required']) {
        $defaults[$fieldName] = 'input';
      }
      else {
        $defaults[$fieldName] = 'ignore';
      }
    }
    return $defaults;
  }

  /**
   * Get the actual default values.
   */
  private function getDefaultValues() {
    $connections = $this->entity->get('field_connections');
    $defaults = [];
    if (is_array($connections)) {
      foreach ($connections as $field) {
        $defaults[$field['field_name']] = $field;
      }
    }
    return $defaults;
  }

  /**
   * Ajax callback for the workflow field.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function getWorkflow(array $form, FormStateInterface $form_state): array {
    return $form['field_connections_wrapper'];
  }

  /**
   * Get all fields for a bundle with their settings and configs.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   *
   * @return array
   *   An array of fields for the bundle.
   */
  public function getFieldsForBundle($entityType, $bundle) {
    $fields = [];
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);
    $keys = array_flip($this->entityTypeManager->getDefinition($entityType)->getKeys());

    foreach ($fieldDefinitions as $fieldName => $fieldDefinition) {
      $fields[$fieldName] = [
        'id' => $fieldName,
        'label' => $fieldDefinition->getLabel(),
        'type' => $fieldDefinition->getType(),
        'required' => $fieldDefinition->isRequired(),
        'settings' => $fieldDefinition->getSettings(),
        'key' => $keys[$fieldName] ?? '',
      ];
    }

    return $fields;
  }

}
