<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Plugin\AiApiExplorer;

use Drupal\ai\OperationType\GenericType\DocumentFile;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Dto\StructuredOutputSchema;
use Drupal\ai\Guardrail\AiGuardrailHelper;
use Drupal\ai\Guardrail\AiGuardrailSetInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\OperationType\Chat\Tools\ToolsInput;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai\Service\FunctionCalling\FunctionGroupPluginManager;
use Drupal\ai_api_explorer\AiApiExplorerPluginBase;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;
use Drupal\ai_api_explorer\ExplorerHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\ai\Response\AiStreamedResponse;

/**
 * Plugin implementation of the ai_api_explorer.
 */
#[AiApiExplorer(
  id: 'chat_generator',
  title: new TranslatableMarkup('Chat Generation Explorer'),
  description: new TranslatableMarkup('Contains a form where you can experiment and test the AI chat generator with prompts.'),
)]
final class ChatGenerator extends AiApiExplorerPluginBase {

  /**
   * Constructs the base plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\ai\Service\AiProviderFormHelper $aiProviderHelper
   *   The AI Provider Helper.
   * @param \Drupal\ai_api_explorer\ExplorerHelper $explorerHelper
   *   The Explorer helper.
   * @param \Drupal\ai\AiProviderPluginManager $providerManager
   *   The Provider Manager.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The Renderer service.
   * @param \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager $functionCallPluginManager
   *   The AI Function Calls.
   * @param \Drupal\ai\Service\FunctionCalling\FunctionGroupPluginManager $functionGroupPluginManager
   *   The AI Function Groups.
   * @param \Drupal\ai\Guardrail\AiGuardrailHelper $aiGuardrailHelper
   *   The AI Guardrail Helper.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RequestStack $requestStack,
    AiProviderFormHelper $aiProviderHelper,
    ExplorerHelper $explorerHelper,
    AiProviderPluginManager $providerManager,
    protected Renderer $renderer,
    protected FunctionCallPluginManager $functionCallPluginManager,
    protected FunctionGroupPluginManager $functionGroupPluginManager,
    protected AiGuardrailHelper $aiGuardrailHelper,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $requestStack, $aiProviderHelper, $explorerHelper, $providerManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('ai.form_helper'),
      $container->get('ai_api_explorer.helper'),
      $container->get('ai.provider'),
      $container->get('renderer'),
      $container->get('plugin.manager.ai.function_calls'),
      $container->get('plugin.manager.ai.function_groups'),
      $container->get('ai.guardrail_helper'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function isActive(): bool {
    return $this->providerManager->hasProvidersForOperationType('chat');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = $this->getFormTemplate($form, 'ai-text-response', 'three-column');
    $form['#attached']['library'][] = 'ai_api_explorer/stream';
    $form['#attached']['library'][] = 'ai_api_explorer/multiselect';

    $form['left']['prompts'] = [
      '#type' => 'details',
      '#title' => $this->t('Chat Messages'),
      '#open' => TRUE,
      '#description' => $this->t('<strong>Please note: This is not a chat, it is an explorer of the chat endpoint to build chat logic!</strong> <br />Enter your chat messages here, each message has to have a role and a message. The role may not be used by all providers or models.'),
    ];

    $form['left']['prompts']['system_prompt'] = [
      '#type' => 'details',
      '#title' => $this->t('System Prompt'),
      '#open' => FALSE,
    ];

    $form['left']['prompts']['system_prompt']['system_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System Message'),
      '#attributes' => [
        'placeholder' => $this->t('You are an helpful assistant.'),
      ],
      '#required' => FALSE,
    ];

    $form['left']['prompts']['role_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Role'),
      '#attributes' => [
        'placeholder' => $this->t('user, system, assistant, etc.'),
      ],
      '#default_value' => 'user',
      '#required' => TRUE,
    ];
    $form['left']['prompts']['message_1'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#attributes' => [
        'placeholder' => $this->t('Write you message here.'),
      ],
      '#required' => TRUE,
      '#default_value' => '',
    ];
    $form['left']['prompts']['image_1'] = [
      '#type' => 'file',
      // Only jpg, png files are allowed, since that covers most models.
      '#title' => $this->t('File'),
      '#description' => $this->t('Attach a file to the call. Note that not all models support images or other files and will throw an error.'),
    ];

    $form['left']['streamed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Streamed'),
      '#description' => $this->t('If the provider supports streaming, the response will be streamed. <strong>Currently the image chat will not work with streaming in this explorer (however in the API it works).</strong>'),
    ];

    $form['left']['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced'),
      '#open' => FALSE,
    ];

    $form['left']['advanced']['json_schema_detail'] = [
      '#type' => 'details',
      '#title' => $this->t('JSON Schema/Structured Output'),
      '#open' => FALSE,
    ];

    $form['left']['advanced']['json_schema_detail']['json_schema'] = [
      '#type' => 'ai_json_schema',
      '#title' => $this->t('JSON Schema/Structured Output'),
      '#description' => $this->t('If the provider supports structured JSON, you can enter the JSON schema here.'),
      '#attributes' => [
        'placeholder' => $this->t('{"type": "object", "properties": {"role": {"type": "string"}, "text": {"type": "string"}}}'),
      ],
    ];

    $form['left']['advanced']['function_call_detail'] = [
      '#type' => 'details',
      '#title' => $this->t('Function Calling'),
      '#open' => FALSE,
    ];

    $function_call_options = [];
    foreach ($this->functionCallPluginManager->getDefinitions() as $plugin_id => $definition) {
      $group = $definition['group'];
      if ($group && $this->functionGroupPluginManager->hasDefinition($group)) {
        $group_details = $this->functionGroupPluginManager->getDefinition($group);
        $function_call_options[(string) $group_details['group_name']][$plugin_id] = $definition['name'] . ' (' . $definition['provider'] . ')';
      }
      else {
        $function_call_options['Other'][$plugin_id] = $definition['name'] . ' (' . $definition['provider'] . ')';
      }

    }
    $form['left']['advanced']['function_call_detail']['function_calls'] = [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#title' => $this->t('Function Calling'),
      '#description' => $this->t('The function to add to the call.'),
      '#required' => FALSE,
      '#options' => $function_call_options,
    ];

    $form['left']['advanced']['function_call_detail']['execute'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Execute Function Call'),
      '#description' => $this->t('If you want to execute the function call and show the output.'),
    ];

    $form['left']['advanced']['guardrails_detail'] = [
      '#type' => 'details',
      '#title' => $this->t('Guardrails'),
      '#open' => FALSE,
    ];

    $guardrail_set_options = array_map(function (AiGuardrailSetInterface $guardrail_set) {
      return $guardrail_set->label();
    }, $this->aiGuardrailHelper->getRepository()->getAllGuardrailSets());
    $form['left']['advanced']['guardrails_detail']['guardrail_set'] = [
      '#type' => 'select',
      '#title' => $this->t('Guardrail Set'),
      '#description' => $this->t('The guardrails set to apply to the call.'),
      '#required' => FALSE,
      '#options' => ['' => $this->t('- Select -')] + $guardrail_set_options,
    ];

    $form['left']['submit_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['ai-submit-wrapper'],
        'style' => 'display: flex; align-items: center; gap: 5px;',
      ],
    ];

    $form['left']['submit_wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Ask The AI'),
      '#attributes' => [
        'data-response' => 'ai-text-response',
        'class' => ['ai-submit-button'],
      ],
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'ai-text-response',
      ],
    ];

    $form['left']['submit_wrapper']['loading'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => [
        'id' => 'ai-loading-message-chat',
        'class' => ['ai-loading'],
        'style' => 'display: none;',
      ],
      '#value' => $this->t('Processing...'),
    ];
    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form['right'], $form_state, 'chat', 'chat', AiProviderFormHelper::FORM_CONFIGURATION_FULL, 1003);

    $form['right']['chat_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    // This runs on streamed.
    try {
      $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'chat', 'chat');
      $values = $form_state->getValues();
      $prompt_message = $values['message_1'];

      // Get the messages.
      $messages = [];
      // Get potential files.
      $files = $this->getRequest()->files->all();
      if (!empty($prompt_message)) {
        foreach ($values as $key => $value) {
          if (str_starts_with($key, 'role_')) {
            $index = substr($key, 5);
            $role = $value;
            $message = $values['message_' . $index];
            // Load the file.
            $attachment = "";
            if (isset($files['files']['image_' . $index])) {
              $file = $files['files']['image_' . $index];
              $raw_file = file_get_contents($file->getPathname());
              if (str_starts_with($file->getClientMimeType(), 'image')) {
                $attachment = new ImageFile($raw_file, $file->getClientMimeType(), $file->getClientOriginalName());
              }
              elseif ($file->getClientMimeType() === 'application/pdf') {
                $attachment = new DocumentFile($raw_file, $file->getClientMimeType(), $file->getClientOriginalName());
              }
            }
            if ($role && $message) {
              $attachments = [];
              if ($attachment) {
                $attachments[] = $attachment;
              }
              $messages[] = new ChatMessage($role, $message, $attachments);
            }
          }
        }
      }

      $functions = [];
      $function_instances = [];
      foreach ($values['function_calls'] as $function_call_name) {
        $function_call = $this->functionCallPluginManager->createInstance($function_call_name);
        $function_instances[$function_call->getFunctionName()] = $function_call;
        $functions[] = $function_call->normalize();
      }

      $input = new ChatInput($messages);

      if (count($functions)) {
        $input->setChatTools(new ToolsInput($functions));
      }

      $input = $this->aiGuardrailHelper->applyGuardrailSetToChatInput($values['guardrail_set'], $input);

      // Check for system message.
      if ($form_state->getValue('system_message')) {
        $input->setSystemPrompt($form_state->getValue('system_message'));
      }

      $message = NULL;
      $response = NULL;

      try {

        if ($form_state->getValue('json_schema')) {
          $schema = Json::decode($form_state->getValue('json_schema'));
          if (!is_array($schema) || (!isset($schema['schema']) && !isset($schema['properties']))) {
            throw new \InvalidArgumentException("The JSON schema is not valid JSON or does not decode to an array. It has to include the schema/properties key.");
          }
          $schema_part = !empty($schema['schema']) ? $schema['schema'] : $schema;
          $name = !empty($schema['name']) ? $schema['name'] : 'json_schema';
          $description = !empty($schema['description']) ? $schema['description'] : '';
          $strict = !empty($schema['strict']) ? (bool) $schema['strict'] : FALSE;
          $structured = new StructuredOutputSchema(
            json_schema: $schema_part,
            name: $name,
            description: $description,
            strict: $strict,
          );
          $input->setChatStructuredJsonSchema($structured);
        }

        // If we should stream.
        if ($form_state->getValue('streamed')) {
          $input->setStreamedOutput(TRUE);
        }
        $response = $provider->chat($input, $form_state->getValue('chat_ai_model'), [
          'chat_generation',
          'ai_api_explorer',
        ])->getNormalized();
      }
      catch (\TypeError $e) {
        $message = $this->t('The AI provider could not be used. Please make sure a model is selected and the provider is properly configured.');
      }
      catch (\Exception $e) {
        $message = $this->explorerHelper->renderException($e);
      }
      $code = '';
      $tools_output = '';
      if ($response) {
        if (method_exists($response, 'getTools') && $response->getTools()) {
          $tools_output = $this->getToolsOutput($function_instances, $response->getTools(), $form_state->getValue('execute'));
        }
        // Generation code for normalization.
        $code = $this->normalizeCodeExample($provider, $form_state, $messages);
      }

      if (is_object($response) && get_class($response) == ChatMessage::class) {
        $output = $response->getText();
        if ($form_state->getValue('json_schema')) {
          // Decode && encode nicely.
          $json = json_encode(Json::decode($output), JSON_PRETTY_PRINT);
          if (!empty($json)) {
            $output = '<pre>' . $json . '</pre>';
          }
        }
        $form['middle']['response']['#context']['ai_response']['role'] = [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => 'Role: ' . $response->getRole(),
        ];
        $form['middle']['response']['#context']['ai_response']['text'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $output,
        ];
        if ($tools_output) {
          $form['middle']['response']['#context']['ai_response']['tools_wrapper'] = [
            '#type' => 'details',
            '#title' => $this->t('Tools Output'),
            '#open' => TRUE,
          ];
          $form['middle']['response']['#context']['ai_response']['tools_wrapper']['tools'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $tools_output,
          ];
        }
        $form['middle']['response']['#context']['ai_response']['code'] = $code;
        $form_state->setRebuild();
        return $form['middle'];
      }
      elseif ($response instanceof StreamedChatMessageIteratorInterface) {
        $http_response = new AiStreamedResponse();
        $http_response->setCallback(function () use ($response, $code) {
          foreach ($response as $key => $chat_message) {
            if ($chat_message->getRole() && !$key) {
              echo '<h4>Role: ' . $chat_message->getRole() . "</h4><p>";
            }
            echo $chat_message->getText();
            flush();
          }
          echo $this->renderer->render($code);
          flush();
        });
        $form_state->setResponse($http_response);
      }
      else {
        $form['middle']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $message ? $this->t('Error') : $this->t('Response will appear here.'),
          ],
        ];

        if ($message) {
          $form['middle']['response']['#context']['ai_response']['message'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $message,
            '#attributes' => [
              'class' => ['ai-text-response', 'ai-error-message'],
            ],
          ];
        }

        $form_state->setRebuild();
        return $form['middle'];
      }
    }
    catch (\TypeError $e) {
      $form['middle']['response']['#context']['ai_response'] = [
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Configuration Error'),
        ],
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('The AI provider could not be used. Please make sure a model is selected and the provider is properly configured.'),
          '#attributes' => [
            'class' => ['ai-text-response', 'ai-error-message'],
          ],
        ],
      ];
      $form_state->setRebuild();
      return $form['middle'];
    }
    catch (\Exception $e) {
      $form['middle']['response']['#context']['ai_response'] = [
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Error'),
        ],
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->explorerHelper->renderException($e),
          '#attributes' => [
            'class' => ['ai-text-response', 'ai-error-message'],
          ],
        ],
      ];
      $form_state->setRebuild();
      return $form['middle'];
    }

    return $form['middle'] ?? [];
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // If its streamed, we trigger the function.
    if ($form_state->getValue('streamed')) {
      $this->getResponse($form, $form_state);
    }
  }

  /**
   * Gets the tools output.
   *
   * @param \Drupal\ai\Service\FunctionCalling\FunctionCallInterface[] $functions
   *   The functions.
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput[] $outputs
   *   The outputs.
   * @param int $execute
   *   If the functions should be executed.
   *
   * @return string
   *   The tools output.
   */
  public function getToolsOutput(array $functions, array $outputs, int $execute): string {
    $output = '';
    $i = 1;
    foreach ($outputs as $tool) {
      $output .= '<h4>#' . $i . ' tool usage:</h4>';
      $output .= $this->t('<strong>Tool name</strong>') . ' ' . $tool->getName() . '<br>';
      $output .= $this->t('<strong>Arguments from LLM:</strong>') . '<br>';
      foreach ($tool->getArguments() as $argument) {
        $output .= '- <em>' . $argument->getName() . '</em>: ' . Json::encode($argument->getValue()) . '<br>';
      }
      $function = $this->functionCallPluginManager->convertToolResponseToObject($tool);

      // Validate the context.
      $violations = $function->validateContexts();
      if ($violations->count()) {
        $output .= $this->t('<strong>Validation errors</strong>:') . '<br>';
        foreach ($violations as $violation) {
          $output .= '- ' . new FormattableMarkup('@property: @violation', [
            '@property' => $violation->getRoot()->getDataDefinition()->getLabel(),
            '@violation' => $violation->getMessage(),
          ]) . '<br/>';
        }
      }
      elseif ($function instanceof ExecutableFunctionCallInterface && $execute) {
        $function->execute();
        $output .= $this->t('<strong>Executed value</strong>:') . ' ' . $function->getReadableOutput() . '<br>';
      }
      $i++;
    }
    return $output;
  }

  /**
   * Gets the normalized code example.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $messages
   *   The messages.
   *
   * @return array
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, array $messages): array {
    $code = $this->getCodeExampleTemplate();
    $code['code']['#value'] .= '// Use this when you want to be able to swap the provider. <br>';
    $show_config = count($provider->getConfiguration());

    if ($show_config) {
      $code['code']['#value'] .= $this->addProviderCodeExample($provider);
    }

    $code['code']['#value'] .= '$input = new \Drupal\ai\OperationType\Chat\ChatInput([<br>';

    foreach ($messages as $message) {
      if (count($message->getImages())) {
        $code['code']['#value'] .= '&nbsp;&nbsp;// Assume a File entity being used with variable $file.<br>';
        $code['code']['#value'] .= '&nbsp;&nbsp;$image = new \Drupal\ai\OperationType\GenericType\ImageFile();<br>';
        $code['code']['#value'] .= '&nbsp;&nbsp;$image->setFileFromFile($file);<br>';
        $code['code']['#value'] .= '&nbsp;&nbsp;new \Drupal\ai\OperationType\Chat\ChatMessage("' . $message->getRole() . '", "' . $message->getText() . '", $image),<br>';
      }
      else {
        $code['code']['#value'] .= '&nbsp;&nbsp;new \Drupal\ai\OperationType\Chat\ChatMessage("' . $message->getRole() . '", "' . $message->getText() . '"),<br>';
      }
    }
    $code['code']['#value'] .= ']);<br><br>';
    if (!empty($form_state->getValue('function_calls'))) {
      $code['code']['#value'] .= '$functions_array = [];<br>';
      $code['code']['#value'] .= '// Example call for tools plugin for function calling.<br>';
      $code['code']['#value'] .= '$tools = ["' . implode('", "', $form_state->getValue('function_calls')) . '"];<br>';
      $code['code']['#value'] .= 'foreach ($tools as $tool_id) {<br>';
      $code['code']['#value'] .= '&nbsp;&nbsp;$tool = \Drupal::provider("plugin.manager.ai.function_calls")->createInstance($tool_id);<br>';
      $code['code']['#value'] .= '&nbsp;&nbsp;$functions_array[] = $tool->normalize();<br>';
      $code['code']['#value'] .= '&nbsp;&nbsp;$functions[$tool->getName()] = $tool;<br>';
      $code['code']['#value'] .= '}<br>';
      $code['code']['#value'] .= '$input->setChatTools(new \Drupal\ai\OperationType\Chat\Tools\ToolsInput($functions));<br><br>';
    }

    if (!empty($form_state->getValue('guardrail_set'))) {
      $code['code']['#value'] .= '$ai_guardrail_helper = \Drupal::service(\'ai.guardrail_helper\');<br>';
      $code['code']['#value'] .= '$input = $ai_guardrail_helper->applyGuardrailSetToChatInput(\'' . $form_state->getValue('guardrail_set') . '\', $input);<br><br>';
    }

    $code['code']['#value'] .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('chat_ai_provider') . '\');<br>';
    if ($show_config) {
      $code['code']['#value'] .= "\$ai_provider->setConfiguration(\$config);<br>";
    }
    if ($form_state->getValue('json_schema')) {
      $code['code']['#value'] .= '$input->setChatStructuredJsonSchema(' . $form_state->getValue('json_schema') . ');<br>';
    }
    if ($form_state->getValue('system_message')) {
      $code['code']['#value'] .= '$input->setSystemPrompt("' . Json::decode($form_state->getValue('system_message')) . '");<br>';
    }
    if ($form_state->getValue('streamed')) {
      $code['code']['#value'] .= "// If you want to stream the response normalized you have to make sure<br>";
      $code['code']['#value'] .= "\$input->setStreamedOutput(TRUE);<br>";
    }
    $code['code']['#value'] .= "// Normalized \$response will be a ChatMessage object.<br>";
    $code['code']['#value'] .= "\$response = \$ai_provider->chat(\$input, '" . $form_state->getValue('chat_ai_model') . '\', ["your_module_name"])->getNormalized();<br>';

    if (!empty($form_state->getValue('function_calls'))) {
      $code['code']['#value'] .= '// Example call for tools output for function calling.<br>';
      $code['code']['#value'] .= 'if ($response->getTools()) {<br>';
      $code['code']['#value'] .= '&nbsp;&nbsp;foreach($response->getTools() as $tool) {<br>';
      $code['code']['#value'] .= '&nbsp;&nbsp;&nbsp;&nbsp;// Get the actual object.<br>';
      $code['code']['#value'] .= '&nbsp;&nbsp;&nbsp;&nbsp;$function = $functions[$tool->getName()];<br>';
      $code['code']['#value'] .= '&nbsp;&nbsp;&nbsp;&nbsp;// Seed it.<br>';
      $code['code']['#value'] .= '&nbsp;&nbsp;&nbsp;&nbsp;$function->populateValues($tool);<br>';
      $code['code']['#value'] .= '&nbsp;&nbsp;&nbsp;&nbsp;// Execute it if possible.<br>';
      $code['code']['#value'] .= '&nbsp;&nbsp;&nbsp;&nbsp;if ($function instanceof \Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface) {<br>';
      $code['code']['#value'] .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$function->execute();<br>';
      $code['code']['#value'] .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;// Get the readable output.<br>';
      $code['code']['#value'] .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$function->getReadableOutput();<br>';
      $code['code']['#value'] .= '&nbsp;&nbsp;&nbsp;&nbsp;}<br>';
      $code['code']['#value'] .= '&nbsp;&nbsp;}<br>';
      $code['code']['#value'] .= '}<br>';

    }
    // If there is a streaming response.
    if ($form_state->getValue('streamed')) {
      $code['code']['#value'] .= "<br><br>// If you want to stream the response normalized you have to make sure<br>";
      $code['code']['#value'] .= "// the provider supports it and have a fallback if not. This shows how. <br><br>";
      $code['code']['#value'] .= "// It is a stream response.<br>";
      $code['code']['#value'] .= "if (\$response instanceof \Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface) {<br>";
      $code['code']['#value'] .= "&nbsp;&nbsp;// This is a stream response.<br>";
      $code['code']['#value'] .= "&nbsp;&nbsp;// You can loop through the response and output it as it comes in.<br>";
      $code['code']['#value'] .= "/* @var \Drupal\ai\OperationType\Chat\StreamedChatMessage \$chat_message */<br>";
      $code['code']['#value'] .= "&nbsp;&nbsp;foreach (\$response as \$chat_message) {<br>";
      $code['code']['#value'] .= "&nbsp;&nbsp;&nbsp;&nbsp;echo \$chat_message->getText();<br>";
      $code['code']['#value'] .= "&nbsp;&nbsp;}<br>";
      $code['code']['#value'] .= "} else {<br>";
      $code['code']['#value'] .= "&nbsp;&nbsp;// This is a normal response.<br>";
      $code['code']['#value'] .= "&nbsp;&nbsp;echo \$response->getText();<br>";
      $code['code']['#value'] .= "}<br>";
    }

    return [
      'code' => $code,
      'raw_code' => $this->rawCodeExample($provider, $form_state),
    ];
  }

  /**
   * Gets the raw code example.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The raw code example.
   */
  public function rawCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state): array {
    $code = $this->getCodeExampleTemplate();
    $code['#title'] = $this->t('Raw Code Example');

    $code['code']['#value'] .= '// Another way if you know you always will use ' . $provider->getPluginDefinition()['label'] . ' and want its way of doing stuff. Not recommended. <br>';
    $code['code']['#value'] .= $this->addProviderCodeExample($provider);
    $code['code']['#value'] .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('chat_ai_provider') . '\');<br>';
    $code['code']['#value'] .= "\$ai_provider->setConfiguration(\$config);<br>";
    if (!empty($form_state->getValue('function_calls'))) {
      $code['code']['#value'] .= '// Some custom code for function calling per model.<br>';
    }
    $code['code']['#value'] .= "// Normalized \$response will be what ever the provider gives back.<br>";
    $code['code']['#value'] .= "\$response = \$ai_provider->chat(\$expectedInputFromProviderClient, '" . $form_state->getValue('chat_ai_model') . '\', ["your_module_name"])->getRawOutput();';

    return $code;
  }

  /**
   * Ajax callback accounting for the different form structure.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The correct section of the form.
   *
   * @see \Drupal\ai\Service\AiProviderFormHelper::loadModelsAjaxCallback
   */
  public static function loadModelsAjaxCallback(array &$form, FormStateInterface $form_state): mixed {
    $prefix = $form_state->getTriggeringElement()['#ajax']['data-prefix'];
    $form_state->setRebuild();
    return $form['right'][$prefix . 'ajax_prefix'];
  }

}
