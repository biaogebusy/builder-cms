<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Plugin\AiApiExplorer;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai\Service\FunctionCalling\FunctionGroupPluginManager;
use Drupal\ai\Service\FunctionCalling\PropertyFormBuilder;
use Drupal\ai_api_explorer\AiApiExplorerPluginBase;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;
use Drupal\ai_api_explorer\ExplorerHelper;
use Drupal\user\Entity\Role;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the ai_api_explorer.
 *
 * @AiApiExplorer(
 *   id = "tools_explorer",
 *   label = @Translation("Tools Explorer"),
 *   description = @Translation("Contains a form where you can experiment by seeing parameters and triggering tools.")
 * )
 */
#[AiApiExplorer(
  id: 'tools_explorer',
  title: new TranslatableMarkup('Tools Explorer'),
  description: new TranslatableMarkup('Contains a form where you can experiment by seeing parameters and triggering tools.'),
)]
final class ToolsExplorer extends AiApiExplorerPluginBase {

  use DependencySerializationTrait;

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
   * @param \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager $functionCallPluginManager
   *   The AI Function Calls.
   * @param \Drupal\ai\Service\FunctionCalling\FunctionGroupPluginManager $functionGroupPluginManager
   *   The AI Function Groups.
   * @param \Drupal\ai\Service\FunctionCalling\PropertyFormBuilder $propertyFormBuilder
   *   The property form builder.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RequestStack $requestStack,
    AiProviderFormHelper $aiProviderHelper,
    ExplorerHelper $explorerHelper,
    AiProviderPluginManager $providerManager,
    protected FunctionCallPluginManager $functionCallPluginManager,
    protected FunctionGroupPluginManager $functionGroupPluginManager,
    protected PropertyFormBuilder $propertyFormBuilder,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
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
      $container->get('plugin.manager.ai.function_calls'),
      $container->get('plugin.manager.ai.function_groups'),
      $container->get('ai.function_call_form_helper'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
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
    // Get the query string.
    $query = $this->requestStack->getCurrentRequest()->query->all();

    $form = $this->getFormTemplate($form, 'ai-function-response');

    // Get all the tools.
    $options = [];
    foreach ($this->functionCallPluginManager->getDefinitions() as $plugin_id => $definition) {
      $group = $definition['group'];
      if ($group && $this->functionGroupPluginManager->hasDefinition($group)) {
        $group_details = $this->functionGroupPluginManager->getDefinition($group);
        $options[(string) $group_details['group_name']][$plugin_id] = $definition['name'] . ' (' . $definition['provider'] . ')';
      }
      else {
        $options['Other'][$plugin_id] = $definition['name'] . ' (' . $definition['provider'] . ')';
      }
    }

    $form['left']['tool'] = [
      '#type' => 'select',
      '#title' => $this->t('Tool'),
      '#options' => $options,
      '#required' => TRUE,
      '#empty_option' => $this->t('Select a tool'),
      '#description' => $this->t('Please choose a tool to test against.'),
      '#default_value' => $form_state->getValue('tool') ?? $query['tool'] ?? '',
      '#ajax' => [
        'callback' => [$this, 'getFunctionPropertiesCallback'],
        'wrapper' => 'ai-properties-response',
        'event' => 'change',
      ],
    ];

    $form['left']['properties'] = [
      '#type' => 'details',
      '#title' => $this->t('Properties'),
      '#open' => TRUE,
      '#description' => $this->t('Please choose a tool.'),
      '#prefix' => '<div id="ai-properties-response">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    // If a tool is preset and nothing exists in the form state, create subform.
    if (!empty($query['tool']) && empty($form_state->getValue('tool'))) {
      $form_state->setValue('tool', $query['tool']);
    }

    if ($form_state->getValue('tool')) {
      $this->getFunctionProperties($form, $form_state);
    }

    // Run as another role.
    $user_roles = [];
    foreach (Role::loadMultiple() as $role_key => $role) {
      $user_roles[$role_key] = $role->label();
    }

    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run Function'),
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'ai-function-response',
      ],
    ];

    return $form;
  }

  /**
   * Creates an ajax callback for the function properties.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function getFunctionPropertiesCallback(array &$form, FormStateInterface $form_state): array {
    $form_state->setRebuild(TRUE);
    return $form['left']['properties'];
  }

  /**
   * Get the function properties.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function getFunctionProperties(&$form, FormStateInterface $form_state): array {
    // Get the value of the tool.
    $tool = $form_state->getValue('tool');
    if (empty($tool)) {
      $form['left']['properties']['#description'] = $this->t('Please choose a tool.');
    }
    else {
      $form['left']['properties']['#description'] = '';
      // Load an instance of the function.
      try {
        $sub_form = $this->propertyFormBuilder->createFormElements($tool);
        $form['left']['properties'] += $sub_form;

        // Expose the usage limit.
        $form['left']['properties']['limits'] = [
          '#type' => 'details',
          '#open' => FALSE,
          '#title' => $this->t('Limits'),
          '#parents' => ['property_limits'],
        ];
        $tool_definition = $this->functionCallPluginManager->getDefinition($tool);
        foreach ($tool_definition['context_definitions'] as $name => $definition) {
          $form['left']['properties']['limits'][$name] = [
            '#type' => 'details',
            '#open' => FALSE,
            '#title' => $definition->getLabel(),
          ];
          $form['left']['properties']['limits'][$name]['action'] = [
            '#type' => 'select',
            '#title' => $this->t('Restrictions for property %name', [
              '%name' => $definition->getLabel(),
            ]),
            '#options' => [
              '' => $this->t('Allow all'),
              'only_allow' => $this->t('Only allow certain values'),
              'force_value' => $this->t('Force value'),
            ],
            '#description' => $this->t('Restrict the allowed values or enforce a value.'),
          ];
          $form['left']['properties']['limits'][$name]['values'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Values'),
            '#description' => $this->t('The values that are allowed or the value that should be set. If you pick to only allow certain values, you can set the allowed values new line separated if there are more then one. If you pick to force a value, you can set the value that should be set.'),
            '#rows' => 2,
            '#states' => [
              'visible' => [
                ':input[name="property_limits[' . $name . '][action]"]' => [
                  ['value' => 'only_allow'],
                  'or',
                  ['value' => 'force_value'],
                ],
              ],
            ],
          ];
        }
      }
      catch (\Exception $e) {
        $form['left']['properties']['#description'] = $this->t('Failed to load tool properties: @error', ['@error' => $e->getMessage()]);
      }
    }
    return $form['left']['properties'];
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    try {
      $tool = $form_state->getValue('tool');
      if (empty($tool)) {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('No Tool Selected'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('Please select a valid tool to execute.'),
            '#attributes' => [
              'class' => ['ai-text-response', 'ai-error-message'],
            ],
          ],
        ];
        $form_state->setRebuild();
        return $form['right'];
      }

      /** @var \Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface|\Drupal\ai\Base\FunctionCallBase $function_call */
      $function_call = $this->functionCallPluginManager->createInstance($tool);
      // Run through and fill all the properties.
      foreach ($function_call->getContextDefinitions() as $name => $property) {
        // Apply any limits.
        $limit = $form_state->getValue(['property_limits', $name]) ?? [];

        $values = explode("\n", $limit['values']);
        switch ($limit['action']) {
          // Set constant value (forced value).
          case 'force_value':
            $property->addConstraint('FixedValue', $values[0]);
            $property->setDefaultValue($values[0]);
            $property->setRequired(FALSE);
            break;

          case 'only_allow':
            $property->addConstraint('Choice', $values);
            break;
        }

        $property_name = str_replace(':', '__colon__', $name);
        $value = $form_state->getValue(['properties', $property_name]) ?? '';
        if ($value) {
          $function_call->setContextValue($name, $value);
        }
      }
      $violations = $function_call->validateContexts();
      if ($violations->count()) {
        $form['right']['response'] = [
          '#theme' => 'item_list',
          '#list_type' => 'ul',
          '#title' => $this->t('Property validation errors'),
          '#items' => array_map(
            fn (ConstraintViolationInterface $violation) => new FormattableMarkup('@property: @violation', [
              '@property' => $violation->getRoot()->getDataDefinition()->getLabel(),
              '@violation' => $violation->getMessage(),
            ]),
            (array) $violations->getIterator(),
          ),
        ];
      }
      else {
        $function_call->execute();
        $form['right']['response'] = [
          '#type' => 'markup',
          '#markup' => '<pre>' . $function_call->getReadableOutput() . '</pre>',
        ];
      }
    }
    catch (\TypeError $e) {
      $form['right']['response']['#context']['ai_response'] = [
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Configuration Error'),
        ],
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('The tool could not be executed. Please ensure the tool is properly configured.'),
          '#attributes' => [
            'class' => ['ai-text-response', 'ai-error-message'],
          ],
        ],
      ];
    }
    catch (\Exception $e) {
      $form['right']['response']['#context']['ai_response'] = [
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
    }

    $form_state->setRebuild();
    return $form['right'];
  }

}
