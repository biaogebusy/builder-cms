<?php

namespace Drupal\ai_translate\Form;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\ai\AiProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AI Translate module.
 */
class AiTranslateSettingsForm extends ConfigFormBase {

  const MINIMAL_PROMPT_LENGTH = 50;

  use StringTranslationTrait;

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_translate.settings';

  /**
   * Twig engine.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected TwigEnvironment $twig;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Language Manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Provider Manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $providerManager;

  /**
   * Route builder.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected RouteBuilderInterface $routeBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->twig = $container->get('twig');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->languageManager = $container->get('language_manager');
    $instance->providerManager = $container->get('ai.provider');
    $instance->routeBuilder = $container->get('router.builder');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_translate_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      static::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Load config.
    $config = $this->config(static::CONFIG_NAME);

    $chat_models = $this->providerManager->getSimpleProviderModelOptions('chat');
    array_shift($chat_models);
    array_splice($chat_models, 0, 1);
    $form['#tree'] = TRUE;

    // Allow site builders to opt-out of using this module to override the
    // default translation tab.
    $form['use_ai_translate'] = [
      '#title' => $this->t('Use AI Translate as the default to translate content'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('use_ai_translate') ?? TRUE,
      '#description' => $this->t('When using this module on its own, keep this box checked. This allows AI Translate to take over the "Translate" tab when editing any entity. When this module is use as a translation framework for other translation mechanisms such as AI TMGMT; however, the default Drupal translation may be desired for the "Translate" tab.'),
    ];

    // Add translation status setting.
    $form['translation_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Translation status'),
      '#description' => $this->t('Choose how to handle the published status of newly created translations.'),
      '#options' => [
        'keep_original' => $this->t('Keep the status of original entity'),
        'create_draft' => $this->t('Create translation in draft status'),
      ],
      '#config_target' => static::CONFIG_NAME . ':translation_status',
      '#required' => TRUE,
    ];
    // Add translation status setting.
    $form['redirect_after_create'] = [
      '#type' => 'radios',
      '#title' => $this->t('Action after creating a new translation'),
      '#description' => $this->t('Where to redirect after creating a new translation.'),
      '#options' => [
        'list' => $this->t('Return to the translation list.'),
        'edit' => $this->t('Edit the new translation.'),
      ],
      '#config_target' => static::CONFIG_NAME . ':redirect_after_create',
      '#required' => TRUE,
    ];

    $default_prompt = $config->get('prompt');

    $languages = $this->languageManager->getLanguages();
    $language_settings = $config->get('language_settings') ?? [];
    $form['prompt'] = [
      '#title' => $this->t('Default translation prompt'),
      '#type' => 'ai_prompt',
      '#prompt_types' => ['ai_translate'],
      '#required' => TRUE,
      '#config_target' => static::CONFIG_NAME . ':prompt',
    ];

    foreach ($languages as $langcode => $language) {
      $form['language_settings'][$langcode] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Translate to @lang', ['@lang' => $language->getName()]),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      ];
      $form['language_settings'][$langcode]['model'] = [
        '#type' => 'select',
        '#options' => $chat_models,
        "#empty_option" => $this->t('-- Default from AI module (chat) --'),
        '#disabled' => count($chat_models) == 0,
        '#default_value' => $language_settings[$langcode]['model'] ?? '',
        '#title' => $this->t('AI model used for translating to @lang', ['@lang' => $language->getName()]),
      ];
      $form['language_settings'][$langcode]['prompt'] = [
        '#title' => $this->t('Translation prompt for translating to @lang', ['@lang' => $language->getName()]),
        '#type' => 'ai_prompt',
        '#parents' => ['language_settings', $langcode, 'prompt'],
        '#prompt_types' => ['ai_translate'],
        '#required' => FALSE,
        '#default_value' => $language_settings[$langcode]['prompt'] ?? $default_prompt,
      ];
    }

    $form['reference_defaults'] = [
      '#type' => 'details',
      '#tree' => FALSE,
      '#title' => $this->t('Entity reference translation'),
      '#description' => $this->moduleHandler->moduleExists('help')
        ? Link::createFromRoute($this->t('Read more'), 'help.help_topic',
          ['id' => 'ai_translate.references'])
        : $this->t('Enable <em>@module</em> module to read more', ['@module' => 'help']),
    ];
    $form['reference_defaults']['reference_defaults'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('These entity types will be translated by default when referencing entity is translated'),
      '#options' => self::getReferencingEntityTypes(TRUE),
      '#description' => $this->t('This setting can be overriden in entity reference field settings.'),
      '#default_value' => $config->get('reference_defaults'),
    ];
    $form['reference_defaults']['entity_reference_depth'] = [
      '#type' => 'select',
      '#options' => [
        1 => '1',
        2 => '2',
        5 => '5',
        10 => '10',
        0 => $this->t('Unlimited'),
      ],
      '#default_value' => $config->get('entity_reference_depth'),
      '#title' => $this->t('Maximum Reference Depth'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Validate the length of the selected default prompt.
    $defaultPromptId = $form_state->getValue('prompt');
    $promptText = $this->configFactory->get('ai.ai_prompt.' . $defaultPromptId)?->get('prompt') ?? '';
    try {
      $renderedPrompt = (string) $this->twig->renderInline($promptText, [
        '{sourceLangName}' => 'Test 1',
      ]);
      $renderedPrompt = strtr($renderedPrompt, [
        '{destLangName}' => 'Test 2',
        '{inputText}' => 'Text to translate',
      ]);
      if (strlen($renderedPrompt) < self::MINIMAL_PROMPT_LENGTH) {
        $form_state->setErrorByName('prompt',
          $this->t('Prompt cannot be shorter than @num characters',
            ['@num' => self::MINIMAL_PROMPT_LENGTH]));
      }
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('prompt', $e->getMessage());
    }

    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      try {
        // Language-specific prompts are optional.
        $langPromptId = $form_state->getValue(['language_settings', $langcode, 'prompt']);
        if ($langPromptId && $langPromptId !== $defaultPromptId) {
          $promptText = $this->configFactory->get('ai.ai_prompt.' . $langPromptId)?->get('prompt') ?? '';
          $renderedPrompt = (string) $this->twig->renderInline($promptText, [
            '{sourceLangName}' => 'Test 1',
          ]);
          $renderedPrompt = strtr($renderedPrompt, [
            '{destLangName}' => 'Test 2',
            '{inputText}' => 'Text to translate',
          ]);
          if (strlen($renderedPrompt) < self::MINIMAL_PROMPT_LENGTH) {
            $form_state->setError($form['language_settings'][$langcode]['prompt'],
              $this->t('Prompt cannot be shorter than @num characters',
                ['@num' => self::MINIMAL_PROMPT_LENGTH]));
          }
        }
      }
      catch (\Exception $e) {
        $form_state->setErrorByName('prompt', $e->getMessage());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config(static::CONFIG_NAME);

    // If changing use of AI translate, route subscriber needs a refresh.
    // We want the editable version here as we want what is actually taking
    // place in the cache including overrides, not just what is stored in
    // configuration.
    $original_use_ai_translate = $config->get('use_ai_translate');

    // Save configuration settings.
    $config->set('use_ai_translate', $form_state->getValue('use_ai_translate'));
    $config->set('translation_status', $form_state->getValue('translation_status'));
    $config->set('entity_reference_depth', $form_state->getValue('entity_reference_depth'));
    $config->set('reference_defaults', array_keys(array_filter($form_state->getValue('reference_defaults'))));
    $config->set('language_settings', $form_state->getValue('language_settings'));
    $config->save();

    // Now rebuild the routes after config save since the route subscriber
    // checks the configuration.
    if ($original_use_ai_translate !== $form_state->getValue('use_ai_translate')) {
      $this->routeBuilder->rebuild();
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Get list of valid entity types for the reference_default setting.
   *
   * @param bool $option_list
   *   Whether to return as list of entity type IDs, or an option list.
   *
   * @return array
   *   Valid entity types that can be used for the reference_default setting.
   */
  public static function getReferencingEntityTypes(bool $option_list = FALSE): array {
    $options = [];
    foreach (\Drupal::entityTypeManager()->getDefinitions() as $entityTypeId => $entityType) {
      if (!($entityType instanceof ContentEntityTypeInterface)) {
        continue;
      }
      if ($option_list) {
        $options[$entityTypeId] = $entityType->getLabel();
      }
      else {
        $options[] = $entityTypeId;
      }
    }
    return $options;
  }

}
