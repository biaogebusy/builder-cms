<?php

namespace Drupal\ai\Form;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Url;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\AiVdbProviderPluginManager;
use Drupal\ai\Utility\PseudoOperationTypes;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AI module.
 */
class AiSettingsForm extends ConfigFormBase {

  use AjaxHelperTrait;

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai.settings';

  /**
   * Section identifier for installed capabilities.
   */
  const SECTION_INSTALLED = 'installed';

  /**
   * Section identifier for additional capabilities.
   */
  const SECTION_ADDITIONAL = 'additional';

  /**
   * Section identifier for vector capabilities.
   */
  const SECTION_VECTOR = 'vector';

  /**
   * Provider registry data loaded from ai.provider_registry.yml.
   *
   * @var array|null
   */
  protected $providerRegistry = NULL;

  /**
   * Vector database registry loaded from ai.vdb_provider_registry.yml.
   *
   * @var array|null
   */
  protected $vdbProviderRegistry = NULL;

  /**
   * Constructor.
   */
  public function __construct(
    protected AiProviderPluginManager $providerManager,
    protected AiVdbProviderPluginManager $vdbProviderManager,
    protected ExtensionPathResolver $extensionPathResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
      $container->get('ai.vdb_provider'),
      $container->get('extension.path.resolver'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nojs = NULL) {
    $form = [
      'default_providers' => [],
    ];

    // Load the registry file once.
    $this->getProviderRegistry();

    // Attach the AI global library for consistent styling.
    $form['#attached']['library'][] = 'ai/ai_global';
    $form['#attached']['library'][] = 'ai/ai_settings_form';

    $config = $this->config(static::CONFIG_NAME);
    $default_providers = $config->get('default_providers') ?? [];

    // Get all providers.
    /** @var \Drupal\ai\AiProviderInterface[] $providers */
    $providers = [];
    foreach ($this->providerManager->getDefinitions() as $id => $definition) {
      $providers[$id] = $this->providerManager->createInstance($id);
    }

    if (count($providers) === 0) {
      $this->messenger()->addWarning($this->t('Choose at least one AI provider module from those listed on the AI module homepage, add to your project, install and configure it. Then update the AI Settings on this page.'));
    }

    $operation_types = $this->getOperationTypes();

    // Check if we're simulating no JavaScript or if a non-JS button
    // was clicked.
    $is_nojs = ($nojs === 'nojs');
    $triggering_element = $form_state->getTriggeringElement();
    $is_nojs_submit = $triggering_element && !empty($triggering_element['#name']) && strpos($triggering_element['#name'], 'select_provider_') === 0;

    // Categorize capabilities into sections.
    $categorized = $this->categorizeCapabilities($operation_types, $providers);

    // Build the installed capabilities section.
    $form['installed_capabilities'] = $this->buildInstalledCapabilitiesSection(
      $categorized[self::SECTION_INSTALLED],
      $providers,
      $default_providers,
      $form_state,
      $is_nojs,
      $is_nojs_submit
    );

    // Build the additional capabilities section (only if there are any).
    if (!empty($categorized[self::SECTION_ADDITIONAL])) {
      $form['additional_capabilities'] = $this->buildAdditionalCapabilitiesSection(
        $categorized[self::SECTION_ADDITIONAL]
      );
    }

    // Build the vector capabilities section.
    $form['vector_capabilities'] = $this->buildVectorCapabilitiesSection(
      $categorized[self::SECTION_VECTOR],
      $providers,
      $default_providers,
      $form_state,
      $is_nojs,
      $is_nojs_submit,
      $config
    );

    $form['advanced_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#open' => FALSE,
      '#weight' => 25,
    ];

    // Add HTTP timeout configuration.
    $form['advanced_settings']['request_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('HTTP Request Timeout'),
      '#description' => $this->t('Timeout in seconds for HTTP requests to AI providers. Longer timeouts may be needed for complex operations like translations or thinking models. Default is 60 seconds.'),
      '#default_value' => $config->get('request_timeout') ?: 60,
      '#min' => 1,
      '#max' => 3600,
      '#required' => TRUE,
    ];

    // A details field that has allowed hosts for generated content.
    $form['advanced_settings']['allowed_host_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Generated Content External Link Security'),
      '#description' => $this->t('Protect against prompt injection attacks that trick AI into sending sensitive data to third-party sites via hidden links or images. Links to your own site and relative paths are always allowed.'),
      '#open' => FALSE,
      '#weight' => 20,
    ];

    $form['advanced_settings']['allowed_host_wrapper']['allowed_hosts'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Trusted Domains'),
      '#default_value' => implode("\n", $this->normalizeAllowedHosts($config->get('allowed_hosts'))),
      '#description' => $this->t('Enter one domain per line. Links and images to trusted domains are not filtered. Examples: `example.com`, `docs.example.com`. Use `*.example.com` to allow all subdomains. Links and images pointing to unlisted domains will be handled according to the setting below.'),
    ];

    $option = $config->get('allowed_hosts_rewrite_links') ? 'rewrite' : 'delete';
    if (!empty(Settings::get('ai_output')['full_trust_mode'])) {
      $option = 'full_trust';
    }

    $form['advanced_settings']['allowed_host_wrapper']['allowed_hosts_rewrite_links'] = [
      '#type' => 'radios',
      '#title' => $this->t('Untrusted AI Generated Links & Images'),
      '#default_value' => $option,
      '#options' => [
        'rewrite' => $this->t('Replace Links & Delete Images'),
        'delete' => $this->t('Delete Images & Links'),
        'full_trust' => $this->t('Full Trust Mode (must be enabled in settings.php)'),
      ],
      '#description' => $this->t('<ul><li><strong>Replace Links & Delete Images</strong> displays the full URL as visible text, so users can see the destination and any URL parameters. Images from untrusted domains are removed.</li>
<li><strong>Delete Images & Links</strong> removes all links and images from untrusted domains.</li>
<li><strong>Full Trust Mode</strong> allows all links and images. Can only be enabled in settings.php.</li></ul>'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Categorizes operation types into sections.
   *
   * @param array $operation_types
   *   The operation types to categorize.
   * @param array $providers
   *   The available providers.
   *
   * @return array
   *   An array with keys 'installed', 'additional', and 'vector'.
   */
  protected function categorizeCapabilities(array $operation_types, array $providers): array {
    $categorized = [
      self::SECTION_INSTALLED => [],
      self::SECTION_ADDITIONAL => [],
      self::SECTION_VECTOR => [],
    ];

    foreach ($operation_types as $operation_type) {
      $operation_id = $operation_type['id'];
      $filters = $operation_type['filter'] ?? [];

      // Embeddings go to vector section.
      if ($operation_id === 'embeddings') {
        $categorized[self::SECTION_VECTOR][] = $operation_type;
        continue;
      }

      // Check if any provider supports this capability.
      $has_provider = FALSE;
      foreach ($providers as $provider) {
        if ($provider->isUsable($operation_type['actual_type'] ?? $operation_id, $filters)) {
          $has_provider = TRUE;
          break;
        }
      }

      if ($has_provider) {
        $categorized[self::SECTION_INSTALLED][] = $operation_type;
      }
      else {
        $categorized[self::SECTION_ADDITIONAL][] = $operation_type;
      }
    }

    // Sort each section alphabetically by label.
    $sortByLabel = fn($a, $b) => strcasecmp((string) $a['label'], (string) $b['label']);
    usort($categorized[self::SECTION_INSTALLED], $sortByLabel);
    usort($categorized[self::SECTION_ADDITIONAL], $sortByLabel);
    usort($categorized[self::SECTION_VECTOR], $sortByLabel);

    return $categorized;
  }

  /**
   * Builds the installed capabilities section.
   *
   * @param array $capabilities
   *   The capabilities for this section.
   * @param array $providers
   *   The available providers.
   * @param array $default_providers
   *   The default provider configuration.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param bool $is_nojs
   *   Whether we're in no-JS mode.
   * @param bool $is_nojs_submit
   *   Whether this is a no-JS submit.
   *
   * @return array
   *   The form section render array.
   */
  protected function buildInstalledCapabilitiesSection(
    array $capabilities,
    array $providers,
    array $default_providers,
    FormStateInterface $form_state,
    bool $is_nojs,
    bool $is_nojs_submit,
  ): array {
    $section = [
      '#type' => 'details',
      '#title' => $this->t('AI Capabilities from Installed Providers'),
      '#open' => TRUE,
      '#weight' => 10,
      '#description' => $this->t('Configure default providers and models for AI capabilities.'),
    ];

    if (empty($capabilities)) {
      $section['empty'] = [
        '#markup' => '<p>' . $this->t('No AI capabilities are available. Install and <a href="@configure_url">configure</a> an AI Provider to get started.', [
          '@configure_url' => Url::fromRoute('ai.admin_providers')->toString(),
        ]) . '</p>',
      ];
      return $section;
    }

    $section['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Capability'),
        $this->t('Provider'),
        $this->t('Model'),
        $this->t('Info'),
      ],
      '#empty' => $this->t('No capabilities available.'),
      '#attributes' => ['class' => ['ai-capabilities-table']],
    ];

    foreach ($capabilities as $operation_type) {
      $row = $this->buildCapabilityTableRow(
        $operation_type,
        $providers,
        $default_providers,
        $form_state,
        $is_nojs,
        $is_nojs_submit
      );
      $section['table'][$operation_type['id']] = $row;
    }

    return $section;
  }

  /**
   * Builds a table row for a capability.
   *
   * @param array $operation_type
   *   The operation type definition.
   * @param array $providers
   *   The available providers.
   * @param array $default_providers
   *   The default provider configuration.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param bool $is_nojs
   *   Whether we're in no-JS mode.
   * @param bool $is_nojs_submit
   *   Whether this is a no-JS submit.
   *
   * @return array
   *   The table row render array.
   */
  protected function buildCapabilityTableRow(
    array $operation_type,
    array $providers,
    array $default_providers,
    FormStateInterface $form_state,
    bool $is_nojs,
    bool $is_nojs_submit,
  ): array {
    $operation_id = $operation_type['id'];
    $filters = $operation_type['filter'] ?? [];

    $options = ['' => $this->t('No default')];
    foreach ($providers as $provider) {
      if ($provider->isUsable($operation_type['actual_type'] ?? $operation_id, $filters)) {
        $options[$provider->getPluginId()] = $provider->getPluginDefinition()['label'];
      }
    }

    $selected_provider = $this->getSelectedProvider(
      $operation_id,
      $default_providers,
      $form_state,
      $is_nojs_submit
    );

    $row = [];

    // Build capability label with description as smaller helper text.
    $capability_markup = '<strong>' . $operation_type['label'] . '</strong>';
    if (!empty($operation_type['description'])) {
      $capability_markup .= '<br><span class="ai-description">' . $operation_type['description'] . '</span>';
    }
    $row['capability'] = [
      '#markup' => $capability_markup,
      '#wrapper_attributes' => ['class' => ['ai-capability-cell']],
    ];

    $row['provider'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ai-provider-cell']],
    ];

    $row['provider']['operation__' . $operation_id] = [
      '#type' => 'select',
      '#name' => 'operation__' . $operation_id,
      '#parents' => ['operation__' . $operation_id],
      '#title' => $this->t('Provider for @capability', ['@capability' => $operation_type['label']]),
      '#title_display' => 'invisible',
      '#options' => $options,
      '#default_value' => $selected_provider,
      '#attributes' => ['class' => ['ai-select']],
      '#ajax' => [
        'callback' => '::loadModels',
        'wrapper' => 'model__' . $operation_id,
        'event' => 'change',
      ],
    ];

    $row['provider']['select_provider_' . $operation_id] = [
      '#type' => 'submit',
      '#value' => $this->t('Choose Model'),
      '#name' => 'select_provider_' . $operation_id,
      '#attributes' => ['class' => ['js-hide', 'button--small']],
      '#submit' => ['::selectProviderSubmit'],
      '#access' => count($options) > 1,
    ];

    if ($is_nojs) {
      unset($row['provider']['operation__' . $operation_id]['#ajax']);
      unset($row['provider']['select_provider_' . $operation_id]['#attributes']['class'][0]);
    }

    $model_options = ['' => $this->t('- Select -')];
    $default_model = '';

    if ($selected_provider && !empty($providers[$selected_provider])) {
      // Check if this is an AJAX request - if so, skip messenger calls as
      // they won't display. Error handling for AJAX is done in loadModels().
      $is_ajax_request = $this->isAjax();

      try {
        if ($providers[$selected_provider]->isUsable($operation_type['actual_type'] ?? $operation_id, $filters)) {
          $model_options = $providers[$selected_provider]->getConfiguredModels($operation_type['actual_type'] ?? $operation_id, $filters);
          $default_model = $form_state->getValue('model__' . $operation_id) ??
                           $default_providers[$operation_id]['model_id'] ?? '';
        }
        elseif (!$is_ajax_request) {
          $this->messenger()->addWarning($this->t('The default %operation provider (%provider_id) is not currently usable. Please review your configuration.', [
            '%operation' => $operation_type['label'],
            '%provider_id' => $selected_provider,
          ]));
        }
      }
      catch (\Exception $e) {
        if (!$is_ajax_request) {
          $this->messenger()->addError($e->getMessage());
          if ($e->getCode() == 401 || (method_exists($e, 'getStatusCode') && $e->getStatusCode() == 401)) {
            $api_key = $providers[$selected_provider]->getConfig()->get('api_key');
            if (!empty($api_key)) {
              $this->messenger()->addError($this->t('You can update or add the API Key <a href="@url" target="_blank">here</a>', ['@url' => Url::fromRoute('entity.key.edit_form', ['key' => $api_key])->toString()]));
            }
            else {
              try {
                $provider_id = $providers[$selected_provider]->getPluginId();
                $this->messenger()->addError($this->t('You can update your provider settings <a href="@url" target="_blank">here</a>.', ['@url' => Url::fromRoute('ai_provider_' . $provider_id . '.settings_form')->toString()]));
              }
              catch (\Exception $route_exception) {
                // Fall back to the general providers page if the
                // provider-specific route does not exist.
                $this->messenger()->addError($this->t('You can update your provider settings <a href="@url" target="_blank">here</a>.', ['@url' => Url::fromRoute('ai.admin_providers')->toString()]));
              }
            }
          }
        }
      }
    }

    $row['model'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'model__' . $operation_id,
        'class' => ['ai-model-cell', 'js-form-wrapper', 'form-wrapper'],
      ],
    ];

    $row['model']['model__' . $operation_id] = [
      '#type' => 'select',
      '#name' => 'model__' . $operation_id,
      '#parents' => ['model__' . $operation_id],
      '#title' => $this->t('Model for @capability', ['@capability' => $operation_type['label']]),
      '#title_display' => 'invisible',
      '#options' => $model_options,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $default_model,
      '#disabled' => empty($selected_provider),
      '#attributes' => ['class' => ['ai-select']],
      '#ajax' => [
        'callback' => '::updateInfoLink',
        'wrapper' => 'info__' . $operation_id,
        'event' => 'change',
      ],
    ];

    // Messages container for AJAX error display.
    $row['model']['messages__' . $operation_id] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'messages__' . $operation_id,
        'class' => ['ai-messages-cell'],
        'data-drupal-messages' => '',
      ],
    ];

    $row['info'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'info__' . $operation_id,
        'class' => ['ai-info-cell'],
      ],
      'link' => $this->buildInfoLink($selected_provider, $default_model),
    ];

    return $row;
  }

  /**
   * Builds the additional capabilities section.
   *
   * @param array $capabilities
   *   The capabilities for this section.
   *
   * @return array
   *   The form section render array.
   */
  protected function buildAdditionalCapabilitiesSection(array $capabilities): array {
    $section = [
      '#type' => 'details',
      '#title' => $this->t('Additional AI Capabilities'),
      '#open' => FALSE,
      '#weight' => 15,
      '#description' => $this->t('These capabilities require additional provider modules to be installed.'),
    ];

    $section['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Capability'),
        $this->t('Available Providers'),
      ],
      '#empty' => $this->t('All capabilities have providers installed.'),
      '#attributes' => ['class' => ['ai-additional-capabilities-table']],
    ];

    foreach ($capabilities as $operation_type) {
      $operation_id = $operation_type['id'];

      // Build capability label with description as smaller helper text.
      $capability_markup = '<strong>' . $operation_type['label'] . '</strong>';
      if (!empty($operation_type['description'])) {
        $capability_markup .= '<br><span class="ai-description">' . $operation_type['description'] . '</span>';
      }

      $providers_markup = $this->buildAvailableProvidersMarkup($operation_id);

      $section['table'][$operation_id] = [
        'capability' => [
          '#markup' => $capability_markup,
          '#wrapper_attributes' => ['class' => ['ai-capability-cell']],
        ],
        'providers' => [
          '#markup' => $providers_markup,
          '#wrapper_attributes' => ['class' => ['ai-providers-cell']],
        ],
      ];
    }

    return $section;
  }

  /**
   * Builds the vector capabilities section.
   *
   * @param array $capabilities
   *   The capabilities for this section.
   * @param array $providers
   *   The available providers.
   * @param array $default_providers
   *   The default provider configuration.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param bool $is_nojs
   *   Whether we're in no-JS mode.
   * @param bool $is_nojs_submit
   *   Whether this is a no-JS submit.
   * @param mixed $config
   *   The configuration object.
   *
   * @return array
   *   The form section render array.
   */
  protected function buildVectorCapabilitiesSection(
    array $capabilities,
    array $providers,
    array $default_providers,
    FormStateInterface $form_state,
    bool $is_nojs,
    bool $is_nojs_submit,
    $config,
  ): array {
    $section = [
      '#type' => 'details',
      '#title' => $this->t('Vector Data Capabilities'),
      '#open' => TRUE,
      '#weight' => 20,
      '#description' => $this->t("Embedding providers allow text & media to be converted to a vector format, which is stored in a vector database (Eg Pinecone, Milvus). This enables AI tools to more easily understand your website's content, which is useful for features such as semantic search, chatbots, or AI content reviews."),
    ];

    // Embedding Providers subsection.
    $section['embeddings_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Embedding Providers'),
      '#attributes' => ['class' => ['ai-heading-h4']],
    ];

    // Check if any providers support embeddings.
    $has_embedding_provider = FALSE;
    foreach ($providers as $provider) {
      if ($provider->isUsable('embeddings')) {
        $has_embedding_provider = TRUE;
        break;
      }
    }

    if (!$has_embedding_provider) {
      $section['embeddings_install_prompt'] = [
        '#markup' => Markup::create('<p>' . $this->buildEmbeddingProviderLinks() . '</p>'),
      ];
    }
    else {
      if (!empty($capabilities)) {
        $section['embeddings_table'] = [
          '#type' => 'table',
          '#header' => [
            $this->t('AI Model Capability'),
            $this->t('Provider'),
            $this->t('Default Model'),
            $this->t('Info'),
          ],
          '#attributes' => ['class' => ['ai-embeddings-table']],
        ];

        foreach ($capabilities as $operation_type) {
          $row = $this->buildCapabilityTableRow(
            $operation_type,
            $providers,
            $default_providers,
            $form_state,
            $is_nojs,
            $is_nojs_submit
          );
          $section['embeddings_table'][$operation_type['id']] = $row;
        }
      }
    }

    // Vector Database (VDB) Providers subsection.
    $section['vdb_heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Vector Database (VDB) Providers'),
      '#attributes' => ['class' => ['ai-heading-h4']],
    ];

    $vdb_providers = $this->vdbProviderManager->getProviders();

    // Load the VDB provider registry.
    $this->getProviderRegistry('vdbProviderRegistry');

    if (empty($vdb_providers)) {
      $section['vdb_install_prompt'] = [
        '#markup' => Markup::create('<p>' . $this->buildVdbProviderLinks() . '</p>'),
      ];
    }
    else {
      $vdb_options = ['' => $this->t('- Select -')] + $vdb_providers;

      $section['vdb_table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('AI Model Capability'),
          $this->t('VDB Provider'),
          $this->t('Database'),
          $this->t('Info'),
        ],
        '#attributes' => ['class' => ['ai-vdb-table']],
      ];

      $selected_vdb = $config->get('default_vdb_provider') ?? '';

      $section['vdb_table']['vdb'] = [
        'capability' => [
          '#markup' => '<strong>' . $this->t('Vector Database') . '</strong>',
        ],
        'provider' => [
          '#type' => 'container',
        ],
        'database' => [
          '#markup' => $this->getVdbDatabaseName($selected_vdb),
        ],
        'info' => $this->buildVdbInfoLink($selected_vdb),
      ];

      $section['vdb_table']['vdb']['provider']['default_vdb_provider'] = [
        '#type' => 'select',
        '#title' => $this->t('Vector Database Provider'),
        '#title_display' => 'invisible',
        '#options' => $vdb_options,
        '#default_value' => $selected_vdb,
      ];
    }

    return $section;
  }

  /**
   * Gets the database name for a VDB provider.
   *
   * @param string|null $vdb_provider
   *   The VDB provider ID.
   *
   * @return string
   *   The database name or empty string.
   */
  protected function getVdbDatabaseName(?string $vdb_provider): string {
    if (empty($vdb_provider)) {
      return '';
    }

    return isset($this->vdbProviderRegistry[$vdb_provider]) ?
      $this->vdbProviderRegistry[$vdb_provider]['label'] : '';
  }

  /**
   * Builds an info link for a VDB provider.
   *
   * @param string|null $vdb_provider
   *   The VDB provider ID.
   *
   * @return array
   *   A render array for the info link.
   */
  protected function buildVdbInfoLink(?string $vdb_provider): array {
    if (empty($vdb_provider)) {
      return ['#markup' => ''];
    }

    $info_url = $this->vdbProviderRegistry[$vdb_provider]['info_url'] ?? '';

    if (empty($info_url)) {
      return ['#markup' => ''];
    }

    $label = $this->vdbProviderRegistry[$vdb_provider]['label'] ?? $vdb_provider;

    return [
      '#type' => 'link',
      '#title' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['class' => ['visually-hidden']],
        '#value' => $label,
      ],
      '#url' => Url::fromUri($info_url),
      '#attributes' => [
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
        'title' => $label,
        'class' => ['ai-icon-button', 'ai-icon--model'],
      ],
    ];
  }

  /**
   * Builds links to available VDB provider modules.
   *
   * @return string
   *   HTML string with links to VDB provider modules.
   */
  protected function buildVdbProviderLinks(): string {
    $links = [];
    foreach ($this->vdbProviderRegistry as $info) {
      $links[] = Link::fromTextAndUrl(
        $info['label'],
        Url::fromUri($info['project_url'], [
          'attributes' => [
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
          ],
        ])
      )->toString();
    }

    return (string) $this->t('No Vector Database is installed or <a href="@configure_url">configured</a>. To get started, install and configure one of the following providers: @links', [
      '@configure_url' => Url::fromRoute('ai.admin_vdb_providers')->toString(),
      '@links' => Markup::create(implode(', ', $links)),
    ]);
  }

  /**
   * Builds links to available embedding provider modules.
   *
   * @return string
   *   HTML string with links to embedding provider modules.
   */
  protected function buildEmbeddingProviderLinks(): string {
    $links = [];
    foreach ($this->providerRegistry as $info) {
      // Check if the capabilities array has embeddings.
      if (!in_array('embeddings', $info['capabilities'] ?? [], TRUE)) {
        continue;
      }
      $links[] = Link::fromTextAndUrl(
        $info['label'],
        Url::fromUri($info['project_url'], [
          'attributes' => [
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
          ],
        ])
      )->toString();
    }

    return (string) $this->t('No Embedding service is installed. To get started, install a provider: @links', [
      '@links' => Markup::create(implode(', ', $links)),
    ]);
  }

  /**
   * Gets providers that support a specific capability from the registry.
   *
   * @param string $capability_id
   *   The capability ID to look up.
   *
   * @return array
   *   An array of provider metadata keyed by provider ID.
   */
  protected function getProvidersForCapability(string $capability_id): array {
    $providers = [];

    foreach ($this->providerRegistry as $provider_id => $metadata) {
      $capabilities = $metadata['capabilities'] ?? [];
      if (in_array($capability_id, $capabilities, TRUE)) {
        $providers[$provider_id] = $metadata;
      }
    }

    return $providers;
  }

  /**
   * Builds markup showing available providers for a capability.
   *
   * @param string $capability_id
   *   The capability ID.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The rendered markup with provider links.
   */
  protected function buildAvailableProvidersMarkup(string $capability_id): Markup|string {
    $providers = $this->getProvidersForCapability($capability_id);

    if (empty($providers)) {
      return (string) $this->t('Check the AI module homepage for available providers.');
    }

    // Sort providers alphabetically by label.
    uasort($providers, fn($a, $b) => strcasecmp($a['label'] ?? '', $b['label'] ?? ''));

    $links = [];
    foreach ($providers as $provider_id => $metadata) {
      $project_url = $metadata['project_url'] ?? NULL;
      $label = $metadata['label'] ?? $provider_id;

      if ($project_url) {
        $links[] = Link::fromTextAndUrl(
          $label,
          Url::fromUri($project_url, [
            'attributes' => [
              'target' => '_blank',
              'rel' => 'noopener noreferrer',
            ],
          ])
        )->toString();
      }
      else {
        $links[] = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
      }
    }

    return Markup::create(implode(', ', $links));
  }

  /**
   * Gets the selected provider for an operation.
   *
   * @param string $operation_id
   *   The operation ID.
   * @param array $default_providers
   *   The default provider configuration.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param bool $is_nojs_submit
   *   Whether this is a no-JS submit.
   *
   * @return string
   *   The selected provider ID.
   */
  protected function getSelectedProvider(
    string $operation_id,
    array $default_providers,
    FormStateInterface $form_state,
    bool $is_nojs_submit,
  ): string {
    $operation_key = 'operation__' . $operation_id;

    if ($is_nojs_submit && $form_state->getUserInput()[$operation_key]) {
      return $form_state->getUserInput()[$operation_key];
    }

    if ($form_state->hasValue($operation_key)) {
      return $form_state->getValue($operation_key);
    }

    return $default_providers[$operation_id]['provider_id'] ?? '';
  }

  /**
   * Finds which section a capability belongs to in the form.
   *
   * @param array $form
   *   The form array.
   * @param string $operation_type
   *   The operation type ID.
   *
   * @return string|null
   *   The section key or NULL if not found.
   */
  protected function findCapabilitySection(array $form, string $operation_type): ?string {
    if (isset($form['installed_capabilities']['table'][$operation_type])) {
      return 'installed_capabilities';
    }
    if (isset($form['vector_capabilities']['embeddings_table'][$operation_type])) {
      return 'vector_capabilities';
    }
    return NULL;
  }

  /**
   * Submit handler for provider selection buttons (non-JS).
   */
  public function selectProviderSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate that allowed hosts are properly formatted.
    $allowed_hosts_input = $form_state->getValue('allowed_hosts');
    $allowed_hosts = array_filter(array_map('trim', explode("\n", $allowed_hosts_input)));
    foreach ($allowed_hosts as $host) {
      // Basic validation for host format.
      if (!preg_match('/^(\*\.)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/', $host)) {
        $form_state->setErrorByName('allowed_hosts', $this->t('The host %host is not a valid host format.', ['%host' => $host]));
      }
    }

    // Skip validation if we're just selecting a provider (non-JS flow).
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element && !empty($triggering_element['#name']) &&
        (strpos($triggering_element['#name'], 'select_provider_') === 0)) {
      return;
    }

    $values = $form_state->getValues();
    foreach ($this->getOperationTypes() as $operation_type) {
      // We only want to ensure a model is selected for each operation that
      // has a default.
      if (empty($values['operation__' . $operation_type['id']])) {
        continue;
      }

      if (empty($values['model__' . $operation_type['id']])) {
        // The user has the option to select a model but has not, show a
        // validation error.
        $message = $this->t('You have selected a provider for @operation but have not selected a model.', [
          '@operation' => $operation_type['label'],
        ]);
        $form_state->setErrorByName('model__' . $operation_type['id'], $message);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Skip saving if we're just selecting a provider (non-JS flow).
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element && !empty($triggering_element['#name']) &&
        (strpos($triggering_element['#name'], 'select_provider_') === 0)) {
      $form_state->setRebuild();
      return;
    }

    // Set the default providers array.
    $valid_operation_types = array_fill_keys(
      array_map(static fn(array $operation_type): string => $operation_type['id'], $this->getOperationTypes()),
      TRUE
    );
    $default_providers = [];
    foreach ($form_state->getValues() as $key => $value) {
      if (strpos($key, 'operation__') === 0) {
        $operation_type = substr($key, 11);
        if (!isset($valid_operation_types[$operation_type]) || $value === '' || $value === NULL) {
          continue;
        }
        $model_id = $form_state->getValue('model__' . $operation_type);
        if ($model_id === '' || $model_id === NULL) {
          continue;
        }
        $default_providers[$operation_type] = [
          'provider_id' => (string) $value,
          'model_id' => (string) $model_id,
        ];
      }
    }

    // Retrieve the configuration.
    $this->config(static::CONFIG_NAME)
      ->set('default_providers', $default_providers)
      ->set('default_vdb_provider', $form_state->getValue('vdb_table')['vdb']['provider']['default_vdb_provider'] ?? '')
      ->set('request_timeout', (int) $form_state->getValue('request_timeout'))
      ->set('allowed_hosts', array_filter(array_map('trim', explode("\n", $form_state->getValue('allowed_hosts')))))
      ->set('allowed_hosts_rewrite_links', $form_state->getValue('allowed_hosts_rewrite_links') == 'rewrite' ? TRUE : FALSE)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Normalizes allowed hosts config values to a clean list of host strings.
   *
   * @param mixed $allowed_hosts
   *   The raw allowed hosts config value.
   *
   * @return string[]
   *   A cleaned host list.
   */
  protected function normalizeAllowedHosts(mixed $allowed_hosts): array {
    if (is_string($allowed_hosts)) {
      $allowed_hosts = [$allowed_hosts];
    }
    if (!is_array($allowed_hosts)) {
      return [];
    }

    $normalized_hosts = array_map(
      static fn($host): string => trim((string) $host),
      $allowed_hosts
    );

    return array_values(array_filter(
      $normalized_hosts,
      static fn(string $host): bool => $host !== ''
    ));
  }

  /**
   * Ajax callback to load models.
   */
  public function loadModels(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $operation_type = substr($trigger['#name'], 11);

    // Get the selected provider from user input.
    $user_input = $form_state->getUserInput();

    // The form elements are nested in the table structure.
    // Try nested path first (table form), then flat path (fallback).
    $table_input = $user_input['table'][$operation_type] ?? [];
    $embeddings_table_input = $user_input['embeddings_table'][$operation_type] ?? [];

    // Look for provider in nested structure.
    $provider_id = $table_input['provider']['operation__' . $operation_type]
      ?? $embeddings_table_input['provider']['operation__' . $operation_type]
      ?? $user_input['operation__' . $operation_type]
      ?? '';

    // Look for model in nested structure.
    $current_model = $table_input['model']['model__' . $operation_type]
      ?? $embeddings_table_input['model']['model__' . $operation_type]
      ?? $user_input['model__' . $operation_type]
      ?? '';

    // Find which section this capability is in.
    $section = $this->findCapabilitySection($form, $operation_type);
    if (!$section) {
      return ['#attached' => []];
    }

    // Get reference to model element - can't use ?? with references.
    $model_element = NULL;
    if (isset($form[$section]['table'][$operation_type]['model'])) {
      $model_element = &$form[$section]['table'][$operation_type]['model'];
    }
    elseif (isset($form[$section]['embeddings_table'][$operation_type]['model'])) {
      $model_element = &$form[$section]['embeddings_table'][$operation_type]['model'];
    }

    if (!$model_element) {
      return ['#attached' => []];
    }

    // Ensure #attached exists to prevent AJAX errors.
    if (!isset($model_element['#attached'])) {
      $model_element['#attached'] = [];
    }

    // If no provider is selected, return empty model container and info.
    if (empty($provider_id)) {
      $form_state->setValue('model__' . $operation_type, '');

      $model_element = [
        '#type' => 'container',
        '#attached' => [],
        '#attributes' => [
          'id' => 'model__' . $operation_type,
          'class' => ['ai-model-cell', 'js-form-wrapper', 'form-wrapper'],
        ],
      ];
      $model_element['model__' . $operation_type] = [
        '#type' => 'select',
        '#name' => 'model__' . $operation_type,
        '#parents' => ['model__' . $operation_type],
        '#title' => $this->t('Model'),
        '#title_display' => 'invisible',
        '#options' => ['' => $this->t('- Select -')],
        '#default_value' => '',
        '#disabled' => TRUE,
        '#attributes' => ['disabled' => 'disabled'],
      ];
      $info_element = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'info__' . $operation_type,
          'class' => ['ai-info-cell'],
        ],
        'link' => ['#markup' => ''],
      ];
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand('#model__' . $operation_type, $model_element));
      $response->addCommand(new ReplaceCommand('#info__' . $operation_type, $info_element));
      return $response;
    }

    // Get the provider instance.
    $provider = $this->providerManager->createInstance($provider_id);

    // Get the operation type definition and filters.
    $operation_type_definition = NULL;
    $filters = [];
    foreach ($this->getOperationTypes() as $type) {
      if ($type['id'] === $operation_type) {
        $operation_type_definition = $type;
        $filters = $type['filter'] ?? [];
        break;
      }
    }

    if (!$operation_type_definition) {
      return $model_element;
    }

    // Get the models for this provider and operation type.
    $models = [];
    $error_message = NULL;
    try {
      if ($provider->isUsable($operation_type_definition['actual_type'] ?? $operation_type, $filters)) {
        $models = $provider->getConfiguredModels($operation_type_definition['actual_type'] ?? $operation_type, $filters);
      }
    }
    catch (\Exception $e) {
      $error_message = $e->getMessage();
      // Check if this is an authentication error to provide helpful guidance.
      if ($e->getCode() == 401 || (method_exists($e, 'getStatusCode') && $e->getStatusCode() == 401)) {
        $api_key = $provider->getConfig()->get('api_key');
        // If the API key is set then provide a link to the key edit form.
        if (!empty($api_key)) {
          $error_message .= ' ' . $this->t('You can update or add the API Key <a href="@url" target="_blank">here</a>.', ['@url' => Url::fromRoute('entity.key.edit_form', ['key' => $api_key])->toString()]);
        }
        else {
          // If the API key is not set then provide a link to the provider
          // settings form.
          try {
            $provider_id = $provider->getPluginId();
            $error_message .= ' ' . $this->t('You can update your provider settings <a href="@url" target="_blank">here</a>.', ['@url' => Url::fromRoute('ai_provider_' . $provider_id . '.settings_form')->toString()]);
          }
          catch (\Exception $route_exception) {
            // Fall back to the general providers page if we can't determine
            // the provider route for the config page.
            $error_message .= ' ' . $this->t('You can update your provider settings <a href="@url" target="_blank">here</a>.', ['@url' => Url::fromRoute('ai.admin_providers')->toString()]);
          }
        }
      }
    }

    // If we have a current model value, check if it's still
    // valid for the new provider.
    if ($current_model && !isset($models[$current_model])) {
      // If the current model is not valid for the new provider, clear it.
      $current_model = '';
      unset($user_input['model__' . $operation_type]);
      $form_state->setUserInput($user_input);
    }

    $options_with_empty = ['' => $this->t('- Select -')] + $models;
    $form_state->setValue('model__' . $operation_type, $current_model);

    // Update the model element options in the form.
    $model_element['model__' . $operation_type]['#options'] = $options_with_empty;
    $model_element['model__' . $operation_type]['#default_value'] = $current_model;
    $model_element['model__' . $operation_type]['#disabled'] = FALSE;
    unset($model_element['model__' . $operation_type]['#attributes']['disabled']);

    // Ensure the messages container exists for displaying AJAX errors.
    $model_element['messages__' . $operation_type] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'messages__' . $operation_type,
        'class' => ['ai-messages-cell'],
        'data-drupal-messages' => '',
      ],
    ];

    // Build info element with updated provider/model links.
    $info_element = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'info__' . $operation_type,
        'class' => ['ai-info-cell'],
      ],
      'link' => $this->buildInfoLink($provider_id, $current_model),
    ];

    $response = new AjaxResponse();
    // Use the rebuilt form element which has proper AJAX settings processed.
    $response->addCommand(new ReplaceCommand('#model__' . $operation_type, $model_element));
    $response->addCommand(new ReplaceCommand('#info__' . $operation_type, $info_element));

    // If an error occurred, add it as a message in the row's message container.
    if ($error_message) {
      $response->addCommand(new MessageCommand($error_message, '#messages__' . $operation_type, ['type' => 'error'], TRUE));
    }

    return $response;
  }

  /**
   * Ajax callback to update info link when model changes.
   */
  public function updateInfoLink(array &$form, FormStateInterface $form_state): array {
    $trigger = $form_state->getTriggeringElement();
    $operation_type = str_replace('model__', '', $trigger['#name']);

    $provider_id = $form_state->getValue('operation__' . $operation_type);
    $model_id = $form_state->getValue('model__' . $operation_type);

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'info__' . $operation_type,
        'class' => ['ai-info-cell'],
      ],
      'link' => $this->buildInfoLink($provider_id, $model_id),
    ];
  }

  /**
   * Builds info links for a provider and optionally a model.
   *
   * @param string|null $provider_id
   *   The provider ID.
   * @param string|null $model_id
   *   The model ID.
   *
   * @return array
   *   A render array containing the info links.
   */
  protected function buildInfoLink(?string $provider_id, ?string $model_id): array {
    if (empty($provider_id)) {
      return ['#markup' => ''];
    }

    $provider_data = $this->providerRegistry[$provider_id] ?? [];
    $items = [];

    // Get provider info URL.
    $provider_url = $provider_data['info_url'] ?? NULL;
    if (!empty($provider_url)) {
      $provider_label = $provider_data['label'] ?? $provider_id;
      $provider_title = $this->t('@provider Provider Information', ['@provider' => $provider_label]);
      $items['provider'] = [
        '#type' => 'link',
        '#title' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['class' => ['visually-hidden']],
          '#value' => $provider_title,
        ],
        '#url' => Url::fromUri($provider_url),
        '#attributes' => [
          'target' => '_blank',
          'rel' => 'noopener noreferrer',
          'data-ai-tooltip' => $provider_title,
          'class' => ['ai-icon-button', 'ai-icon--provider'],
        ],
      ];
    }

    // Get model info URL if available.
    $model_url = $provider_data['models'][$model_id]['info_url'] ?? NULL;
    if (!empty($model_id) && !empty($model_url)) {
      $model_title = $this->t('@model Model Information', ['@model' => $model_id]);
      $items['model'] = [
        '#type' => 'link',
        '#title' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['class' => ['visually-hidden']],
          '#value' => $model_title,
        ],
        '#url' => Url::fromUri($model_url),
        '#attributes' => [
          'target' => '_blank',
          'rel' => 'noopener noreferrer',
          'data-ai-tooltip' => $model_title,
          'class' => ['ai-icon-button', 'ai-icon--model'],
        ],
      ];
    }

    if (empty($items)) {
      return ['#markup' => ''];
    }

    return $items;
  }

  /**
   * Gets provider registry data from YAML file.
   *
   * @return array
   *   The provider registry data.
   */
  protected function getProviderRegistry($type = 'providerRegistry'): array {
    // Check so that the type is allowed.
    if (!in_array($type, ['providerRegistry', 'vdbProviderRegistry'], TRUE)) {
      throw new \InvalidArgumentException('Invalid registry type requested.');
    }
    if ($this->{$type} === NULL) {
      $file = ($type === 'providerRegistry') ? 'ai.provider_registry.yml' : 'ai.vdb_provider_registry.yml';
      $modulePath = $this->extensionPathResolver->getPath('module', 'ai');
      $registryPath = $modulePath . '/resources/' . $file;
      if (file_exists($registryPath)) {
        $contents = file_get_contents($registryPath);
        if ($contents !== FALSE) {
          try {
            $parsed = Yaml::decode($contents) ?: [];
          }
          catch (InvalidDataTypeException $e) {
            $parsed = [];
          }
          $this->{$type} = $parsed['providers'] ?? [];
        }
      }
      $this->{$type} = $this->{$type} ?? [];
    }

    return $this->{$type};
  }

  /**
   * Gets the operation types used in AI settings.
   *
   * @return array
   *   The operation types, including pseudo operation types.
   */
  public function getOperationTypes(): array {
    return array_merge($this->providerManager->getOperationTypes(), PseudoOperationTypes::getDefaultPseudoOperationTypes());
  }

}
