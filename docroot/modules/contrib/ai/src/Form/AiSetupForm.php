<?php

declare(strict_types=1);

namespace Drupal\ai\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\ai\AiProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a AI Core form.
 */
final class AiSetupForm extends FormBase {

  use StringTranslationTrait;

  /**
   * Constructs the form.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $extensionList
   *   Extension list service.
   * @param \Drupal\ai\AiProviderPluginManager $pluginManager
   *   Plugin Manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   Form Builder service.
   * @param \Drupal\Core\File\FileSystem $fileSystem
   *   File system service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module Handler service.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $moduleInstaller
   *   Module installer service.
   */
  public function __construct(
    protected ModuleExtensionList $extensionList,
    protected AiProviderPluginManager $pluginManager,
    protected RendererInterface $renderer,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FormBuilderInterface $formBuilder,
    protected FileSystem $fileSystem,
    protected ModuleHandlerInterface $moduleHandler,
    protected ModuleInstallerInterface $moduleInstaller,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('extension.list.module'),
      $container->get('ai.provider'),
      $container->get('renderer'),
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('file_system'),
      $container->get('module_handler'),
      $container->get('module_installer'),
    );
  }

  /**
   * Stores the current stage during submission.
   *
   * @var int|null
   *   The current stage.
   */
  protected int|null $stage = NULL;

  /**
   * The selected provider during submission.
   *
   * @var string|null
   *   The current provider.
   */
  protected string|null $provider = NULL;

  /**
   * And array of the different stages of the multi-step.
   *
   * @var string[]
   *   The stages.
   */
  protected array $stages = [
    'download_providers',
    'choose_providers',
    'configure_providers',
  ];

  /**
   * The supported AI Providers.
   *
   * @var string[]
   *   An array of [module_name => [provider => plugin_id, 'form' => 'form_id'].
   */
  protected array $providerModules = [
    'ai_provider_aws_bedrock' => [
      'provider' => 'bedrock',
      'form' => 'Drupal\ai_provider_aws_bedrock\Form\BedrockConfigForm',
    ],
    'ai_provider_anthropic' => [
      'provider' => 'anthropic',
      'form' => 'Drupal\ai_provider_anthropic\Form\AnthropicConfigForm',
    ],
    'ai_provider_azure' => [
      'provider' => 'azure',
      'form' => 'Drupal\ai_provider_azure\Form\AzureConfigForm',
    ],
    'ai_provider_deepl' => [
      'provider' => 'deepl',
      'form' => 'Drupal\ai_provider_deepl\Form\DeepLConfigForm',
    ],
    'ai_provider_groq' => [
      'provider' => 'groq',
      'form' => 'Drupal\ai_provider_groq\Form\GroqConfigForm',
    ],
    'ai_provider_huggingface' => [
      'provider' => 'huggingface',
      'form' => 'Drupal\ai_provider_huggingface\Form\HuggingfaceConfigForm',
    ],
    'ai_provider_lmstudio' => [
      'provider' => 'lmstudio',
      'form' => 'Drupal\ai_provider_lmstudio\Form\LmStudioConfigForm',
    ],
    'ai_provider_mistral' => [
      'provider' => 'mistral',
      'form' => 'Drupal\ai_provider_mistral\Form\MistralConfigForm',
    ],
    'ai_provider_ollama' => [
      'provider' => 'ollama',
      'form' => 'Drupal\ai_provider_ollama\Form\OllamaConfigForm',
    ],
    'ai_provider_openai' => [
      'provider' => 'openai',
      'form' => 'Drupal\ai_provider_openai\Form\OpenAiConfigForm',
    ],
    'auphonic' => [
      'provider' => 'auphonic',
      'form' => 'Drupal\auphonic\Form\AuphonicConfigForm',
    ],
    'deepgram' => [
      'provider' => 'deepgram',
      'form' => 'Drupal\deepgram\Form\DeepgramConfigForm',
    ],
    'elevenlabs' => [
      'provider' => 'elevenlabs',
      'form' => 'Drupal\elevenlabs\Form\ElevenLabsSettingsForm',
    ],
    'fireworksai' => [
      'provider' => 'fireworks',
      'form' => 'Drupal\fireworksai\Form\FireworksaiConfigForm',
    ],
    'gemini_provider' => [
      'provider' => 'gemini',
      'form' => 'Drupal\gemini_provider\Form\GeminiConfigForm',
    ],
  ];

  /**
   * An array of the available AI Provider modules.
   *
   * @var array|\Drupal\Core\Extension\Extension[]
   *   Empty on set up. ['module_name' => Extension].
   */
  protected array $modules = [];

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_ai_setup';
  }

  /**
   * Helper to get a list of AI Provider modules in the codebase.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   An array of [module_name => Extension]
   */
  protected function getModules(): array {
    if (empty($this->modules)) {
      foreach ($this->extensionList->reset()->getList() as $module => $data) {
        if (array_key_exists($module, $this->providerModules)) {
          $this->modules[$module] = $data;
        }
      }
    }

    return $this->modules;
  }

  /**
   * Helper to get the selected provider.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string|null
   *   The selected provider. NULL if not set.
   */
  protected function getProvider(FormStateInterface $form_state): ?string {
    if (!$provider = $form_state->getValue('provider')) {
      $provider = $this->provider;
    }

    return $provider;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->stage = $this->getStage();
    $this->getProgress($form, $this->stage);

    if ($this->stage < 0 || $this->stage > 2) {
      $form['complete'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Provider configured'),
      ];
      $form['message'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('A configured provider has been found. Use the links to set up another, or you may start using your AI tools now.'),
      ];
    }
    else {
      $form['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        // phpcs:ignore
        '#value' => $this->t($this->getStageName($this->stage)),
      ];

      switch ($this->stage) {

        case 0:
          $this->moduleDownloadForm($form, $form_state);
          break;

        case 1:
          $this->moduleEnableForm($form, $form_state);
          break;

        case 2:
          $this->providerConfigureForm($form, $form_state);
          break;

      }
    }

    // Clear the cache if new providers are added.
    $form['#cache']['contexts'][] = 'ai_providers';

    return $form;
  }

  /**
   * Adds the Module Download form.
   *
   * @param array $form
   *   The parent form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  private function moduleDownloadForm(array &$form, FormStateInterface $form_state): void {
    $attributes = [
      'destination' => '/admin/config/ai/setup',
    ];

    if ($this->moduleHandler->moduleExists('project_browser')) {
      $url = Url::fromRoute('project_browser.browse', [], $attributes);
      $message = $this->t('<p>Once you have selected the AI Provider for your chosen LLM, you can search for it and download it in the <a href="@url" title="Visit the Module Browser page on your site for assistance with modules">the Module Browsers page</a for your site.></p>', [
        '@url' => $url->toString(),
      ]);
    }
    else {
      $url = Url::fromRoute('system.modules_list', [], $attributes);
      $message = $this->t('<p>Once you have selected the AI Provider for your chosen LLM, you can visit <a href="@url" title="Visit the Extend page on your site for assistance with modules">the Extend page</a> for assistance with installing the new modules.</p>', [
        '@url' => $url->toString(),
      ]);
    }

    $form['intro'] = [
      '#markup' => $this->t('<p>The first step to integrating your site with an LLM is to link to the two together using an AI Provider module. You can find a list of available modules at <a href="https://www.drupal.org/project/ai" title="Visit the AI Core module homepage for more information on AI Providers">https://www.drupal.org/project/ai</a>.</p>'),
    ];

    $form['message'] = [
      '#markup' => $message,
    ];

    $form['outro'] = [
      '#markup' => $this->t('<p>Once you have done that, return here to continue setting up your providers.</p>'),
    ];
  }

  /**
   * Adds the Module Enable form.
   *
   * @param array $form
   *   The parent form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  private function moduleEnableForm(array &$form, FormStateInterface $form_state): void {
    $form['header'] = [
      '#markup' => $this->t('<p>In order to use your LLM, your chosen AI Provider module must be enabled. Just select the module(s) you wish to use below and submit the form.</p><p><strong>Please note:</strong> deselecting a module will NOT uninstall it. If you wish to remove AI Providers from your site, please visit <a href="@url" title="Visit the module uninstall page">the module uninstall page.</a></p>', [
        '@url' => Url::fromRoute('system.modules_uninstall', [], ['destination' => '/admin/config/ai/setup'])->toString(),
      ]),
    ];

    $form['table'] = [
      '#type' => 'tableselect',
      '#header' => [
        'module' => $this->t('Provider module'),
      ],
      '#options' => [],
      '#empty' => $this->t('No modules found'),
      '#default_value' => [],
    ];

    foreach ($this->getModules() as $module => $data) {
      $form['table']['#options'][$module] = [
        // phpcs:ignore
        'module' => $this->t($data->info['name']),
      ];

      if ($this->moduleHandler->moduleExists($module)) {
        $form['table']['#default_value'][$module] = $module;
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Enable Modules'),
      ],
    ];
  }

  /**
   * Adds the Provider Configuration form.
   *
   * @param array $form
   *   The parent form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  private function providerConfigureForm(array &$form, FormStateInterface $form_state): void {
    $form['container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'ai-provider-wrapper',
      ],
    ];

    $provider = $this->getProvider($form_state);

    $form['container']['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose your Provider'),
      '#description' => $this->t('Choose the provider you wish to configure.'),
      '#options' => $this->providerOptions(),
      '#required' => TRUE,
      '#empty_option' => $this->t('Please select a provider'),
      '#default_value' => $provider,
      '#ajax' => [
        'callback' => '::addProviderForm',
        'wrapper' => 'ai-provider-wrapper',
      ],
      '#weight' => -50,
    ];

    if ($provider) {
      $provider_form_id = $this->providerModules[$provider]['form'];
      $provider_form = $this->formBuilder->getForm($provider_form_id);
      $keys = [];
      $has_key = FALSE;

      foreach (Element::getVisibleChildren($provider_form) as $key) {
        if ($provider_form[$key]['#type'] == 'key_select') {
          $keys = $provider_form[$key]['#options'];
          $has_key = TRUE;
        }
        elseif ($provider_form[$key]['#type'] == 'actions' || $provider_form[$key]['#type'] == 'submit') {
          continue;
        }

        $form['container'][$key] = $provider_form[$key];
      }

      if ($has_key) {
        // Hide the key selector if there are no keys to select.
        $form['container']['api_key']['#access'] = count($keys) !== 1;

        $form['container']['add_key'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Add new key'),
          '#weight' => -25,
          '#default_value' => count($keys) == 1,
          '#description' => count($keys) == 1 ? $this->t('You <strong>MUST</strong> add an API Key to link your site with your chosen LLM') : $this->t('Select this to add a new key if your chosen key is not listed below.'),
          '#attributes' => [
            'id' => 'add-key',
          ],
        ];

        $form['container']['add_key_form'] = [
          '#type' => 'details',
          '#title' => $this->t('Key details'),
          '#open' => count($keys) == 1,
          '#weight' => -20,
          '#states' => [
            'visible' => [
              ':input[name="add_key"]' => ['checked' => TRUE],
            ],
          ],
        ];

        $form['container']['add_key_form']['key_label'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Key Label'),
          '#description' => $this->t('Give your key a name so you can identify it later.'),
          '#default_value' => $form_state->getValue('key_label'),
        ];

        $form['container']['add_key_form']['key_storage'] = [
          '#type' => 'select',
          '#title' => $this->t('Key storage'),
          '#description' => $this->t('Select where your key will be stored: if unsure leave at the default value.'),
          '#default_value' => $form_state->getValue('key_storage') ?? 'config',
          '#options' => [
            'config' => $this->t('Configuration'),
            'file' => $this->t('File'),
            'env' => $this->t('Environment'),
          ],
          '#attributes' => [
            'id' => 'key-storage',
          ],
        ];

        $form['container']['add_key_form']['key_location'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Key location'),
          '#description' => $this->t('The location of the file in which the key will be stored. The path may be absolute (e.g., /etc/keys/foobar.key), relative to the Drupal directory (e.g., ../keys/foobar.key), or defined using a stream wrapper (e.g., private://keys/foobar.key).'),
          '#default_value' => $form_state->getValue('key_location'),
          '#states' => [
            'visible' => [
              ':input[name="key_storage"]' => ['value' => 'file'],
            ],
          ],
        ];

        $form['container']['add_key_form']['key_variable'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Key environment variable'),
          '#description' => $this->t('Name of the environment variable.'),
          '#default_value' => $form_state->getValue('key_variable'),
          '#states' => [
            'visible' => [
              ':input[name="key_storage"]' => ['value' => 'env'],
            ],
          ],
        ];

        $form['container']['add_key_form']['key_strip'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Strip trailing line breaks'),
          '#default_value' => $form_state->getValue('key_strip'),
          '#description' => $this->t('Check this to remove any trailing line breaks from the variable. Leave unchecked if there is a chance that a line break could be a valid character in the key.'),
          '#states' => [
            'invisible' => [
              ':input[name="key_storage"]' => ['value' => 'config'],
            ],
          ],
        ];

        $form['container']['add_key_form']['key_base64'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Base64-encoded'),
          '#default_value' => $form_state->getValue('key_base64'),
          '#description' => $this->t('Check this to remove any trailing line breaks from the variable. Leave unchecked if there is a chance that a line break could be a valid character in the key.'),
          '#states' => [
            'visible' => [
              ':input[name="key_storage"]' => ['value' => 'env'],
            ],
          ],
        ];

        $form['container']['add_key_form']['key_value'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Key value'),
          '#description' => $this->t('Enter the API Key provided by your LLM provider so you can access their service.'),
          '#default_value' => $form_state->getValue('key_value'),
          '#maxlength' => 4096,
          '#states' => [
            'visible' => [
              ':input[name="key_storage"]' => ['value' => 'config'],
            ],
          ],
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->isSubmitted()) {
      if ($this->stage == 2) {
        $this->validateProvider($form, $form_state);
      }
    }
  }

  /**
   * Validates the provider form submission.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Core\Form\EnforcedResponseException
   * @throws \Drupal\Core\Form\FormAjaxException
   */
  public function validateProvider(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('add_key')) {
      if (!$form_state->getValue('key_label')) {
        $form_state->setError($form['container']['add_key_form']['key_label'], 'You must add a label for your key.');
      }
      else {
        switch ($form_state->getValue('key_storage')) {
          case 'config':
            if (!$form_state->getValue('key_value')) {
              $form_state->setError($form['container']['add_key_form']['key_value'], 'You must set a key value.');
            }
            break;

          case 'env':
            $env = $form_state->getValue('key_variable');

            if (!$env) {
              $form_state->setError($form['container']['add_key_form']['key_variable'], 'You must set a key environment variable.');
            }
            elseif (!getenv($env)) {
              $form_state->setError($form['container']['add_key_form']['key_variable'], 'Your environment variable does not exist or is empty. If you are unsure how to resolve this, use the Configuration option instead.');
            }
            break;

          case 'file':
            $path = $form_state->getValue('key_location');

            if (!$path) {
              $form_state->setError($form['container']['add_key_form']['key_location'], 'You must set the path to the file containing your key.');
            }
            else {
              $real_path = $this->fileSystem->realpath($path);
              $contents = file_get_contents($path);

              if (!$contents || !$real_path) {
                $form_state->setError($form['container']['add_key_form']['key_location'], 'Your file does not exist or is empty. If you are unsure how to resolve this, use the Configuration option instead.');
              }
            }
            break;

        }
      }
    }
    else {

      // Get the provider form's ID.
      $provider = $form_state->getValue('provider');
      $form_id = $this->providerModules[$provider]['form'];

      // Generate the form.
      $provider_state = new FormState();
      $provider_form = $this->formBuilder->buildForm($form_id, $provider_state);

      // Then set the values in our state, as building the form empties them.
      $provider_state->setUserInput($form_state->getUserInput());
      $provider_state->setValues($form_state->getUserInput());

      // Now let's validate the form.
      $formObject = $provider_state->getFormObject();
      $formObject->validateForm($provider_form, $provider_state);

      if ($errors = $provider_state->getErrors()) {

        // If we have errors, transfer them to our form.
        foreach ($errors as $element => $error) {
          $form_state->setError($form['container'][$element], $error);
        }
      }
    }
  }

  /**
   * AJAX Submit for the provider form.
   *
   * @param array $form
   *   The rebuilt form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The correct section of the rebuilt form.
   */
  public function addProviderForm(array &$form, FormStateInterface $form_state): array {
    return $form['container'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $url = NULL;

    if ($this->stage == 1) {
      $url = $this->moduleEnableSubmit($form, $form_state);
    }
    elseif ($this->stage == 2) {

      // Set the provider for later use.
      $this->provider = $this->getProvider($form_state);

      $url = $this->providerSubmit($form, $form_state);
    }
    else {

      // It shouldn't be possible to get here, but if we do we'll push the user
      // on to the next stage.
      $stage = $this->getStage();

      if (array_key_exists($stage, $this->stages)) {
        $stage++;
        $url = Url::fromRoute('ai.ai_setup', [], ['query' => ['stage' => $this->stages[$stage]]]);
      }
    }

    if ($url) {
      $form_state->setRedirectUrl($url);
    }
  }

  /**
   * Submit function to enable provider modules.
   *
   * @param array $form
   *   The module enable form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Url
   *   A URL to redirect the form to, or NULL if no redirect required.
   *
   * @throws \Drupal\Core\Extension\ExtensionNameLengthException
   * @throws \Drupal\Core\Extension\ExtensionNameReservedException
   * @throws \Drupal\Core\Extension\MissingDependencyException
   */
  private function moduleEnableSubmit(array &$form, FormStateInterface $form_state): Url {
    if ($values = $form_state->getValue('table')) {
      foreach ($values as $module) {
        if ($module) {
          if (!$this->moduleHandler->moduleExists($module)) {
            if (!$this->moduleInstaller->install([$module])) {
              $this->messenger()->addError('There was a problem installing your modules. Please try again or contact an administrator.');
            }
          }
        }
      }
    }

    return Url::fromRoute('ai.ai_setup', [], ['query' => ['stage' => $this->stages[2]]]);
  }

  /**
   * Submit function to add keys or configure providers.
   *
   * @param array $form
   *   The provider form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Url|null
   *   A URL to redirect the form to, or NULL if no redirect required.
   *
   * @throws \Drupal\Core\Form\EnforcedResponseException
   * @throws \Drupal\Core\Form\FormAjaxException
   */
  private function providerSubmit(array &$form, FormStateInterface $form_state): ?Url {
    $url = NULL;

    if ($form_state->getValue('add_key')) {

      // Don't let the form state lose all the form values.
      $form_state->setRebuild();

      $values = [
        'label' => $form_state->getValue('key_label'),
        'id' => strtolower(str_replace(' ', '_', $form_state->getValue('key_label'))),
        'key_type' => 'authentication',
        'key_type_settings' => [],
        'key_provider' => $form_state->getValue('key_storage'),
        'key_provider_settings' => [],
        'key_input' => ($form_state->getValue('key_storage') == 'config') ? 'text_field' : 'none',
        'description' => 'Automatically created by the AI Core module.',
      ];

      foreach ([
        'key_value' => 'key_value',
        'env_variable' => 'key_variable',
        'base64_encoded' => 'key_base64',
        'strip_line_breaks' => 'key_strip',
        'file_location' => 'key_location',
      ] as $key_value => $form_value) {
        if ($value = $form_state->getValue($form_value)) {
          $values['key_provider_settings'][$key_value] = $value;
        }
      }

      try {
        $error = FALSE;
        $key = $this->entityTypeManager->getStorage('key')->create($values);
        $key->save();
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t('There was an error saving your key: please check your values or contact an administrator.'));
        $error = TRUE;
      }

      if (!$error) {

        // Clear the values to prevent errors trying to submit the same key
        // multiple times.
        $form_state->setUserInput(['provider' => $this->getProvider($form_state)]);
        $form_state->setValues(['provider' => $this->getProvider($form_state)]);
      }
    }
    else {

      // Get the provider form's ID.
      $provider = $form_state->getValue('provider');
      $form_id = $this->providerModules[$provider]['form'];

      // Generate the form.
      $provider_state = new FormState();
      $this->formBuilder->buildForm($form_id, $provider_state);

      // Then set the values in our state, as building the form empties them.
      $provider_state->setUserInput($form_state->getUserInput());
      $provider_state->setValues($form_state->getUserInput());

      // Now let's submit the form.
      $formObject = $provider_state->getFormObject();

      try {
        $error = FALSE;
        $this->formBuilder->submitForm($formObject, $provider_state);
      }
      catch (\Exception $e) {
        $this->messenger()->addError('There was a problem saving your settings: please double check your selections or contact an administrator.');
        $form_state->setRebuild();
        $error = TRUE;
      }

      if (!$error) {
        $url = Url::fromRoute('ai.ai_setup', [], ['query' => ['stage' => 'complete']]);
      }
    }

    return $url;
  }

  /**
   * Helper to build the progress links.
   *
   * @param array $form
   *   The current form.
   * @param int $stage
   *   The current stage key.
   *
   * @throws \Exception
   */
  public function getProgress(array &$form, int $stage): void {
    $form['#attached']['library'][] = 'ai/ai_setup_form';

    $form['progress'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => [],
      '#attributes' => ['class' => ['ai-setup-progress-items']],
      '#wrapper_attributes' => ['class' => ['container', 'ai-setup-progress']],
      '#weight' => -100,
    ];

    foreach ($this->stages as $key => $machine) {
      $url = Url::fromRoute('ai.ai_setup', [], ['query' => ['stage' => $machine]]);
      $link = Link::fromTextAndUrl($this->getStageName($key), $url);
      $link = $link->toRenderable();

      // Set some classes to make it clear where we are so far in the process.
      if ($key == $stage) {
        $class = 'is-active';
      }
      elseif ($key < $stage) {
        $class = 'complete';
      }
      else {
        $class = 'pending';
      }

      $link['#attributes'] = [
        'class' => [$class],
      ];

      $form['progress']['#items'][] = $this->renderer->render($link);
    }
  }

  /**
   * Helper to get a list of providers as an options array.
   *
   * @return array
   *   The providers ['module' => 'label'].
   */
  private function providerOptions(): array {
    if ($list = $this->getModules()) {
      $options = [];

      foreach ($list as $module => $data) {
        // phpcs:ignore
        $options[$module] = $this->t($data->info['name']);
      }

      return $options;
    }

    $this->messenger()->addError('You have no provider modules available: please complete the earlier stages of this form before progressing.');

    return [];
  }

  /**
   * Helper to create a readable name from the machine name.
   *
   * @param int $stage
   *   The stage to get the name for.
   *
   * @return string
   *   The human-readable name.
   */
  private function getStageName(int $stage): string {
    $machine = $this->stages[$stage];

    return ucwords(str_replace('_', ' ', $machine));
  }

  /**
   * Helper to get the current stage, either from the URI or by calculation.
   *
   * @return int
   *   The current stage key.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  private function getStage(): int {

    // Get the stage from the request POST or GET.
    // @todo Change to leverage https://www.drupal.org/project/drupal/issues/3555532
    // once it is resolved.
    $request = $this->getRequest();
    $stage = $request->query->get('stage');
    if (!$stage) {
      $stage = $request->request->get('stage');
    }

    if (!$stage) {
      $stage = $this->calculateStart();
    }
    else {
      if ($key = array_search($stage, $this->stages)) {
        $stage = $key;
      }
      elseif ($stage == 'complete') {
        $stage = -1;
      }
      else {
        $stage = 0;
      }
    }

    return (int) $stage;
  }

  /**
   * Helper to calculate how set up the AI Module is.
   *
   * @return int
   *   The current stage key based on calculations.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  private function calculateStart(): int {
    $stage = 0;
    $enabled = $configured = FALSE;

    if ($list = $this->getModules()) {

      // If we have some AI Provider modules in the codebase, we can skip the
      // first step.
      $stage++;

      foreach ($list as $module => $data) {
        if ($data->status) {
          $enabled = TRUE;

          if ($definition = $this->pluginManager->getDefinition($this->providerModules[$module]['provider'])) {

            /** @var \Drupal\ai\AiProviderInterface $plugin */
            if ($plugin = $this->pluginManager->createInstance($this->providerModules[$module]['provider'], $definition)) {
              if ($plugin->isUsable()) {
                $configured = TRUE;
              }
            }
          }
        }
      }
    }

    if ($enabled) {

      // If we have at least one AI Provider module enabled, we can skip the
      // second stage.
      $stage++;

      if ($configured) {

        // If we have at least one AI Provider configured, we have completed the
        // process.
        $stage++;
      }
    }

    return $stage;
  }

}
