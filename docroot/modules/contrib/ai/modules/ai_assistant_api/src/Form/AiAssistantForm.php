<?php

declare(strict_types=1);

namespace Drupal\ai_assistant_api\Form;

use Drupal\ai\Utility\Textarea;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Site\Settings;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Utility\CastUtility;
use Drupal\ai_assistant_api\AiAssistantActionPluginManager;
use Drupal\ai_assistant_api\Entity\AiAssistant;

/**
 * AI Assistant form.
 */
final class AiAssistantForm extends EntityForm {

  /**
   * The ai assistant action plugin manager.
   *
   * @var \Drupal\ai_assistant_api\AiAssistantActionPluginManager
   */
  protected $actionPluginManager;

  /**
   * The path extension resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected $extensionPathResolver;

  /**
   * The AI form helper.
   *
   * @var \Drupal\ai\Service\AiProviderFormHelper
   */
  protected $formHelper;

  /**
   * The AI Provider.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProvider;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new AiAssistantForm object.
   */
  public function __construct(
    AiAssistantActionPluginManager $action_plugin_manager,
    ExtensionPathResolver $extension_path_resolver,
    AiProviderFormHelper $form_helper,
    AiProviderPluginManager $ai_provider,
    ModuleHandlerInterface $module_handler,
  ) {
    $this->actionPluginManager = $action_plugin_manager;
    $this->extensionPathResolver = $extension_path_resolver;
    $this->formHelper = $form_helper;
    $this->aiProvider = $ai_provider;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($container) {
    return new static(
      $container->get('ai_assistant_api.action_plugin.manager'),
      $container->get('extension.path.resolver'),
      $container->get('ai.form_helper'),
      $container->get('ai.provider'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $entity */
    $entity = $this->entity;
    $agents_enabled = $this->moduleHandler->moduleExists('ai_agents') &&  $this->entityTypeManager->hasDefinition('ai_agent');
    if (($entity->isNew() && !$agents_enabled) || $entity->get('ai_agent') && !$agents_enabled) {
      // We just inform that new assistants will use agents and that you have
      // to upgrade the AI Agents module.
      return [];
    }
    return parent::actions($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $entity */
    $entity = $this->entity;
    $form_state->set('agents_enabled', $this->moduleHandler->moduleExists('ai_agents') &&  $this->entityTypeManager->hasDefinition('ai_agent'));
    $agents_enabled = $form_state->get('agents_enabled');

    if (($entity->isNew() && !$agents_enabled) || $entity->get('ai_agent') && !$agents_enabled) {
      // We just inform that new assistants will use agents and that you have
      // to upgrade the AI Agents module.
      $form['warning'] = [
        '#type' => 'markup',
        '#markup' => $this->t('All assistants going forward will be agents. To be able to add a new assistant you need to update to the AI Agents module 1.1.0+.'),
      ];
      return $form;
    }

    $form = parent::form($form, $form_state);

    // Possible agent object.
    $agent_entity = NULL;
    $agents = FALSE;

    $old_entity = count($entity->get('actions_enabled'));

    if ($old_entity) {
      // Show message that this will be deprecated.
      $this->messenger()->addWarning($this->t('This assistant is using the old AI Assistant API for 1.0.0. Please create a new one and migrate the settings to the new one. The old one will be removed in 2.0.0.'));
    }

    $agent_options = [];

    // Hard dependency for now.
    if ($agents_enabled) {
      $agents = $this->entityTypeManager->getStorage('ai_agent')->loadMultiple();
      foreach ($agents as $agent) {
        $agent_options[$agent->id()] = $agent->label();
      }
      $form['ai_agent'] = [
        '#type' => 'hidden',
        '#value' => $entity->get('ai_agent') ?? '',
        '#access' => TRUE,
      ];

      /** @var \Drupal\ai_agents\Entity\AiAgent $agent_entity */
      $agent_entity = $entity->get('ai_agent') ? $this->entityTypeManager->getStorage('ai_agent')->load($entity->get('ai_agent')) : NULL;
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#description' => $this->t('This is the title of the AI Assistant'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => $this->t('Article finder assistant'),
      ],
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => [AiAssistant::class, 'load'],
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Administrative Description'),
      '#default_value' => $agent_entity ? $agent_entity->get('description') : $entity->get('description'),
      '#description' => $this->t('Add a short 1-2 sentence description of what this AI Assistant does. This is not used in the prompt at all and is primarily for site admins. If for any reason the assistant is called by an AI this description may be used to help another AI agent understand this Assistant.'),
      '#attributes' => [
        'rows' => 2,
        'placeholder' => $this->t('An assistant that can find old articles and also publish and unpublish them.'),
      ],
      '#required' => TRUE,
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    $form['instructions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Instructions'),
      '#default_value' => $agent_entity ? $agent_entity->get('system_prompt') : $entity->get('instructions') ?? '',
      '#attributes' => [
        'rows' => 15,
        'placeholder' => $this->t('If the user asks questions about unpublished articles, make sure to add status unpublished somewhere in the lookup.'),
      ],
      '#required' => TRUE,
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    if ($old_entity) {
      foreach ($this->actionPluginManager->getDefinitions() as $definition) {
        $form['action_plugin_' . $definition['id']] = [
          '#type' => 'details',
          '#title' => $definition['label'],
          '#open' => TRUE,
          '#description' => $this->t('Configure the %label settings for this AI assistant.', [
            '%label' => $definition['label'],
          ]),
        ];

        $form['action_plugin_' . $definition['id']]['enabled'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable %label', ['%label' => $definition['label']]),
          '#default_value' => isset($entity->get('actions_enabled')[$definition['id']]),
        ];

        $form['action_plugin_' . $definition['id']]['plugin_id'] = [
          '#type' => 'hidden',
          '#value' => $definition['id'],
        ];

        $form['action_plugin_' . $definition['id']]['configuration'] = [
          '#type' => 'details',
          '#title' => $this->t('%label settings', [
            '%label' => $definition['label'],
          ]),
          '#open' => TRUE,
          'states' => [
            'visible' => [
              ':input[name="' . $definition['id'] . '_enabled"]' => ['checked' => TRUE],
            ],
          ],
          '#description' => $this->t('Configure the %label settings for this AI assistant.', [
            '%label' => $definition['label'],
          ]),
        ];

        $instance = $this->actionPluginManager->createInstance($definition['id'], $entity->get('actions_enabled')[$definition['id']] ?? []);
        $subform = $form['action_plugin_' . $definition['id']]['configuration'] ?? [];
        $subform_state = SubformState::createForSubform($subform, $form, $form_state);

        $form['action_plugin_' . $definition['id']]['configuration'] = $instance->buildConfigurationForm([], $subform_state);
        $form['action_plugin_' . $definition['id']]['#tree'] = TRUE;
      }
    }
    else {
      // Hard code agents and rag action for now.
      if ($form_state->get('agents_enabled')) {
        $form['agents_enabled'] = [
          '#type' => 'details',
          '#title' => $this->t('Agents Enabled'),
          '#description' => $this->t('The agents that this assistant should have access to.'),
          '#open' => TRUE,
        ];

        $tools = [];
        if ($agent_entity) {
          foreach ($agent_entity->get('tools') as $tool => $enabled) {
            if ($enabled && substr($tool, 0, 21) === 'ai_agents::ai_agent::') {
              $tools[] = substr($tool, 21);
            }
          }
        }

        // Remove own agent if it exists.
        if (isset($agent_options[$entity->id()])) {
          unset($agent_options[$entity->id()]);
        }

        $form['agents_enabled']['agents_agent'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Agents to use'),
          '#options' => $agent_options,
          '#default_value' => $tools,
          '#description' => $this->t('Select which agents to use for this plugin.'),
        ];
      }

      // Only show if AI search is enabled.
      if ($this->moduleHandler->moduleExists('ai_search')) {
        $search_enabled = $agent_entity ? $agent_entity->get('tools')['ai_search:rag_search'] ?? FALSE : FALSE;

        $form['rag_action'] = [
          '#type' => 'details',
          '#title' => $this->t('RAG Action'),
          '#description' => $this->t('The RAG action that this assistant should have access to.'),
          '#open' => TRUE,
        ];

        $form['rag_action']['enable_rag'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable RAG'),
          '#default_value' => $search_enabled,
        ];

        $form['rag_action']['rag_database_wrapper'] = [
          '#type' => 'details',
          '#title' => $this->t('RAG Action settings'),
          '#open' => TRUE,
          '#states' => [
            'visible' => [
              ':input[name="enable_rag"]' => ['checked' => TRUE],
            ],
          ],
        ];

        // Get the RAG databases.
        $rag_databases = $this->entityTypeManager->getStorage('search_api_index')->loadMultiple();
        $rag_options = [];
        foreach ($rag_databases as $rag_database) {
          $rag_options[$rag_database->id()] = $rag_database->label();
        }

        $form['rag_action']['rag_database_wrapper']['rag_database'] = [
          '#type' => 'select',
          '#title' => $this->t('RAG Database'),
          '#options' => $rag_options,
          '#empty_option' => $this->t('-- Select --'),
          '#default_value' => $agent_entity ? $agent_entity->get('tool_usage_limits')['ai_search:rag_search']['index']['values'][0] ?? '' : '',
          '#description' => $this->t('Select which RAG database to use for this plugin.'),
        ];

        $form['rag_action']['rag_database_wrapper']['rag_threshold'] = [
          '#type' => 'number',
          '#title' => $this->t('RAG threshold'),
          '#min' => 0,
          '#max' => 1,
          '#step' => 0.01,
          '#default_value' => $agent_entity ? $agent_entity->get('tool_usage_limits')['ai_search:rag_search']['min_score']['values'][0] ?? 0.5 : 0.5,
          '#description' => $this->t('The threshold for the RAG database.'),
        ];

        $form['rag_action']['rag_database_wrapper']['rag_max_results'] = [
          '#type' => 'number',
          '#title' => $this->t('Max results'),
          '#default_value' => $agent_entity ? $agent_entity->get('tool_usage_limits')['ai_search:rag_search']['amount']['values'][0] ?? 5 : 5,
          '#description' => $this->t('The maximum number of results to return from the RAG database.'),
        ];
      }
    }

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $entity->status(),
    ];

    $form['advanced']['allow_history'] = [
      '#type' => 'select',
      '#title' => $this->t('Allow History'),
      '#default_value' => $entity->get('allow_history') ?? 'session',
      '#description' => $this->t('If enabled, the AI Assistant will try store the questions and answers in history during a session. This makes it possible to ask follow-up questions to the Assistant. Note that this raises the price and size of AI calls, and might not be needed for all assistants. Sessions means that it will be stored in the session until the page is reloaded. (coming) Database means that it will be stored in the database with an ID and can be continued later in multiple threads. History includes all the user messages and the assistant replies. It does not include the system prompt (this changes), the messages made by the agents themselves. It does not include all the context provided alongside a user prompt.'),
      '#options' => [
        'none' => $this->t('None'),
        'session' => $this->t('Session'),
        'session_one_thread' => $this->t('Session (Same thread on reload)'),
      ],
    ];

    $form['advanced']['history_context_length'] = [
      '#type' => 'number',
      '#title' => $this->t('History context length'),
      '#default_value' => $entity->get('history_context_length') ?? 2,
      '#description' => $this->t('The number of user and system messages pair to send from last set of messages, excluding the last message from the user.'),
      '#states' => [
        'invisible' => [
          ':input[name="allow_history"]' => ['value' => 'none'],
        ],
      ],
      '#min' => 0,
    ];

    // Set form state if empty.
    if ($form_state->getValue('llm_ai_provider') == NULL) {
      $form_state->setValue('llm_ai_provider', $entity->get('llm_provider'));
    }
    if ($form_state->getValue('llm_ai_model') == NULL) {
      $form_state->setValue('llm_ai_model', $entity->get('llm_model'));
    }

    $this->formHelper->generateAiProvidersForm($form, $form_state, 'chat', 'llm', AiProviderFormHelper::FORM_CONFIGURATION_FULL, 0, '', $this->t('AI Provider'), $this->t('The provider of the AI models used by this assistant. You will only be able to select the advanced models that are capable of providing the responses the Assistant needs.'), TRUE);
    $form['llm_ajax_prefix']['#open'] = FALSE;
    // Set default values.
    $llm_configs = $entity->get('llm_configuration');
    if ($llm_configs && count($llm_configs)) {
      foreach ($llm_configs as $key => $value) {
        $form['llm_ajax_prefix']['llm_ajax_prefix_configuration_' . $key]['#default_value'] = $value;
      }
    }
    $pre_action_prompt = file_get_contents($this->extensionPathResolver->getPath('module', 'ai_assistant_api') . '/resources/pre_action_prompt.txt');
    $system_prompt = file_get_contents($this->extensionPathResolver->getPath('module', 'ai_assistant_api') . '/resources/system_prompt.txt');

    $options = [];
    foreach ($this->entityTypeManager->getStorage('user_role')->loadMultiple() as $role) {
      $options[$role->id()] = $role->label();
    }

    $form['advanced']['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Permission Roles'),
      '#default_value' => $entity->get('roles') ?? [],
      '#description' => $this->t('The roles that are allowed to run this AI Assistant. If no roles are selected, all roles are allowed. User 1 is always allowed.'),
      '#options' => $options,
      '#multiple' => TRUE,
    ];

    $form['advanced']['error_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Generic error message'),
      '#description' => $this->t('This is the answer if we run into any error on the way. You may use the token [error_message] to get the error message from the backend in your message, but it might be a security concern to show this to none escalated users.'),
      '#default_value' => $entity->get('error_message') ?? $this->t('I am sorry, something went terribly wrong. Please try to ask me again.'),
      '#attributes' => [
        'placeholder' => $this->t('I am sorry, something went terribly wrong. Please try to ask me again.'),
        'rows' => 2,
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

    $form['advanced']['specific_error_messages'] = [
      '#type' => 'details',
      '#title' => $this->t('Specific error messages'),
      '#description' => $this->t("These custom error messages will be used where applicable. If there isn't a specific error message for an error, the generic error above will be used."),
      '#open' => FALSE,
      '#tree' => TRUE,
    ];

    foreach ($this->getExceptions() as $name => $exception) {
      $form['advanced']['specific_error_messages'][$name] = [
        '#type' => 'textarea',
        '#title' => $this->t('@type error message', ['@type' => $exception['label']]),
        '#description' => $this->t('The response if we run into an error of this type. You may use the token [error_message] to get the error message from the backend in your message, but it might be a security concern to show this to none escalated users.'),
        '#default_value' => $entity->get('specific_error_messages')[$name] ?? NULL,
        '#attributes' => [
          'placeholder' => $exception['placeholder'] ?? NULL,
          'rows' => 2,
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
    }

    if ($old_entity) {
      $form['advanced']['pre_action_prompt'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Pre Action Prompt'),
        '#default_value' => $entity->get('pre_action_prompt') ?? $pre_action_prompt,
        '#description' => $this->t(
        "This field provides instructions to the LLM prior to running an action.<br><br><strong>The following placesholders can be used:</strong><br>
        <em>[learning_example]</em> - The learning examples for the list of actions the Assistant can take.<br>
        <em>[usage_instruction]</em> - The list of usage instructions given back from the action plugins.<br>
        <em>[list_of_actions]</em> - The list of actions that the Assistant can take.<br>"),
        '#disabled' => !Settings::get('ai_assistant_advanced_mode_enabled', FALSE),
        '#attributes' => [
          'rows' => 30,
        ],
        '#states' => [
          'visible' => [
            ':input[name="ai_agent"]' => ['value' => ''],
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
      $form['advanced']['system_prompt'] = [
        '#type' => 'textarea',
        '#title' => $this->t('System Prompt'),
        '#default_value' => $entity->get('system_prompt') ?? $system_prompt,
        '#description' => $this->t("This field can be enabled by adding <strong>\$settings['ai_assistant_advanced_mode_enabled'] = TRUE;</strong> in settings.php. The pre prompts gets a list of actions that it can take, including RAG databases and either gives back actions that the Assistant can take or an outputted answer. You may use [list_of_actions] to list the actions that the Assistant can take. You can only change this via manual config change. DO NOT CHANGE THIS UNLESS YOU KNOW WHAT YOU ARE DOING. <br><br><strong>The following placesholders can be used:</strong><br>
        <em>[instructions]</em> - The instructions for the assistant.<br>
        <em>[pre_action_prompt]</em> - The value of the preprompt field above.<br>
        <em>[is_logged_in]</em> - A message if the person is logged in or not.<br>
        <em>[user_name]</em> - The username of the user.<br>
        <em>[user_roles]</em> - The roles of the user.<br>
        <em>[user_id]</em> - The user id of the user.<br>
        <em>[user_language]</em> - The language of the user.<br>
        <em>[user_timezone]</em> - The timezone of the user.<br>
        <em>[page_title]</em> - The title of the page.<br>
        <em>[page_path]</em> - The path of the page.<br>
        <em>[page_language]</em> - The language of the page.<br>
        <em>[site_name]</em> - The name of the site.<br>
        "),
        '#required' => TRUE,
        '#disabled' => !Settings::get('ai_assistant_advanced_mode_enabled', FALSE),
        '#attributes' => [
          'rows' => 30,
        ],
        '#states' => [
          'visible' => [
            ':input[name="ai_agent"]' => ['value' => ''],
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
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $this->formHelper->validateAiProvidersConfig($form, $form_state, 'chat', 'llm');

    // Even if the model is required, it can be empty.
    // Trigger an error if the provider is not set to default and the model is
    // empty.
    if ($form_state->getValue('llm_ai_provider') !== '__default__' && empty($form_state->getValue('llm_ai_model'))) {
      $form_state->setErrorByName('llm_ai_model', $this->t('You need to select a model for the AI provider.'));
    }

    // If the rag is enabled, we need to check if the database is selected.
    if ($form_state->getValue('enable_rag') && !$form_state->getValue('rag_database')) {
      $form_state->setErrorByName('rag_database', $this->t('You need to select a RAG database.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $entity */
    $entity = $this->entity;

    // Plugins settings.
    $action_plugins = [];
    foreach ($form_state->getValues() as $key => $val) {
      if (strpos($key, 'action_plugin_') === 0) {
        if ($val['enabled']) {
          $action_plugins[$val['plugin_id']] = $val['configuration'] ?? [];
        }
      }
    }
    $entity->set('actions_enabled', $action_plugins);
    $old_entity = count($entity->get('actions_enabled'));

    // Handle agent-related logic only if the ai_agents module is enabled.
    if ($form_state->get('agents_enabled') && !$old_entity) {
      // Get the tools.
      $tools = [];
      foreach ($form_state->getValue('agents_agent') as $key => $val) {
        if ($val) {
          $tools['ai_agents::ai_agent::' . $key] = TRUE;
        }
      }
      $tool_usage_limits = [];
      // Also check if we need to add the RAG search.
      if ($form_state->getValue('enable_rag')) {
        $tools['ai_search:rag_search'] = TRUE;
        $tool_usage_limits['ai_search:rag_search'] = [
          'index' => [
            'values' => [$form_state->getValue('rag_database')],
            'action' => 'force_value',
            'hide_property' => 1,
          ],
          'amount' => [
            'values' => [$form_state->getValue('rag_max_results')],
            'action' => 'force_value',
            'hide_property' => 1,
          ],
          'min_score' => [
            'values' => [$form_state->getValue('rag_threshold')],
            'action' => 'force_value',
            'hide_property' => 1,
          ],
        ];
      }
      // Check if this is a new assistant.
      if ($entity->isNew()) {
        // If this is a new assistant, we create an ai agent and connect it
        // to the assistant.
        $agent = $this->entityTypeManager->getStorage('ai_agent')->create([
          'id' => $form_state->getValue('id'),
          'label' => $form_state->getValue('label'),
          'description' => $form_state->getValue('description'),
          'system_prompt' => $form_state->getValue('instructions'),
          'tools' => $tools,
          'orchestration_agent' => TRUE,
          'triage_agent' => FALSE,
          'tool_usage_limits' => $tool_usage_limits,
        ]);
        $agent->save();
        $entity->set('ai_agent', $agent->id());
      }
      else {
        // Load the agent and set the tools.
        /** @var \Drupal\ai_agent\Entity\AiAgent $agent */
        $agent = $this->entityTypeManager->getStorage('ai_agent')->load($form_state->getValue('ai_agent'));
        if ($agent) {
          // Get existing tools and preserve non-managed tools.
          $existing_tools = $agent->get('tools') ?: [];

          // Remove only RAG and subagent tools from existing tools.
          foreach ($existing_tools as $tool_key => $enabled) {
            if ($tool_key === 'ai_search:rag_search' || str_starts_with($tool_key, 'ai_agents::ai_agent::')) {
              unset($existing_tools[$tool_key]);
            }
          }

          // Merge preserved tools with new managed tools.
          $tools = array_merge($existing_tools, $tools);
          $agent->set('tools', $tools);
          $agent->set('description', $form_state->getValue('description'));
          $agent->set('system_prompt', $form_state->getValue('instructions'));

          // Load and merge tool usage limits, preserving non-managed limits.
          $old_tool_usage_limits = $agent->get('tool_usage_limits') ?: [];

          // Remove limits for removed subagents and disabled RAG.
          foreach ($old_tool_usage_limits as $limit_key => $limit_value) {
            if ($limit_key === 'ai_search:rag_search' && !$form_state->getValue('enable_rag')) {
              unset($old_tool_usage_limits[$limit_key]);
            }
            if (str_starts_with($limit_key, 'ai_agents::ai_agent::') && !isset($tools[$limit_key])) {
              unset($old_tool_usage_limits[$limit_key]);
            }
          }

          $tool_usage_limits = array_merge($old_tool_usage_limits, $tool_usage_limits);
          $agent->set('tool_usage_limits', $tool_usage_limits);

          $agent->save();
        }
      }
      $entity->set('pre_action_prompt', "");
      $entity->set('system_prompt', "");
    }

    // LLM provider.
    $entity->set('llm_provider', $form_state->getValue('llm_ai_provider'));
    // If its default, we don't set the last.
    if ($form_state->getValue('llm_ai_provider') !== '__default__') {
      $entity->set('llm_model', $form_state->getValue('llm_ai_model'));
      $llm_config = [];
      $provider = $this->aiProvider->createInstance($form_state->getValue('llm_ai_provider'));
      $schema = $provider->getAvailableConfiguration('chat', $form_state->getValue('llm_ai_model'));
      foreach ($form_state->getValues() as $key => $val) {
        if (strpos($key, 'llm_') === 0 && $key !== 'llm_ai_provider' && $key !== 'llm_ai_model') {

          $real_key = str_replace('llm_ajax_prefix_configuration_', '', $key);
          $type = $schema[$real_key]['type'] ?? 'string';
          $llm_config[$real_key] = CastUtility::typeCast($type, $val);
        }
      }
      $entity->set('llm_configuration', $llm_config);
    }
    else {
      $entity->set('llm_configuration', []);
      $entity->set('llm_model', '');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match($result) {
        \SAVED_NEW => $this->t('Created new example %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated example %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

  /**
   * Defines the exception types that will have custom error messages.
   *
   * @return <string, <\Drupal\Core\StringTranslation\TranslatableMarkup>[]>[]
   *   The array of exception with custom error messages. Key is the class name.
   *   Value is the human-readable label.
   */
  protected function getExceptions(): array {
    $exceptions = [];

    $exceptions['AiBadRequestException'] = [
      'label' => $this->t('Bad Request'),
      'placeholder' => $this->t('I am sorry, there was an issue with your request. If the problem persists, please contact an administrator.'),
    ];
    $exceptions['AiRateLimitException'] = [
      'label' => $this->t('Rate Limit'),
      'placeholder' => $this->t('I am sorry, the request has been rejected due to rate limits. Often AI provider accounts can be upgraded to increase the rate limit. You may also find trying later works.'),
    ];
    $exceptions['AiQuotaException'] = [
      'label' => $this->t('Quota'),
      'placeholder' => $this->t('I am sorry, something went wrong. This could be due to the attached account requiring more credits.'),
    ];
    $exceptions['AiSetupFailureException'] = [
      'label' => $this->t('Setup Failure'),
      'placeholder' => $this->t('I am sorry, there is an issue with the AI integration on your site. Please contact an administrator to resolve.'),
    ];
    $exceptions['AiRequestErrorException'] = [
      'label' => $this->t('Request Error'),
      'placeholder' => $this->t('I am sorry, there was an error communicating with the AI. If the problem persists, please contact an administrator.'),
    ];

    return $exceptions;
  }

}
