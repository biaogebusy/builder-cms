<?php

namespace Drupal\ai_translate\Plugin\AiProvider;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Enum\AiProviderCapability;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\TranslateText\TranslateTextInput;
use Drupal\ai\OperationType\TranslateText\TranslateTextInterface;
use Drupal\ai\OperationType\TranslateText\TranslateTextOutput;
use Drupal\ai\Plugin\ProviderProxy;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'chat_translation' provider.
 *
 * The purpose is to implement 'translate_text' operation
 * using more generic 'chat' operation supported by many LLM providers.
 */
#[AiProvider(
  id: 'chat_translation',
  label: new TranslatableMarkup('Chat proxy to LLM'),
)]
class ChatTranslationProvider extends AiProviderClientBase implements
  ContainerFactoryPluginInterface,
  TranslateTextInterface {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $manager;

  /**
   * Configuration of default chat provider.
   *
   * @var array
   */
  protected array $chatConfiguration;

  /**
   * Lazy-loaded provider that actually performs text translation.
   *
   * @var \Drupal\ai\Plugin\ProviderProxy
   */
  protected ProviderProxy $realTranslator;

  /**
   * The Twig engine.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected TwigEnvironment $twig;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->manager = $container->get('ai.provider');
    $instance->twig = $container->get('twig');
    $instance->configFactory = $container->get('config.factory');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    if (empty($this->configFactory->get('ai_translate.settings')->get('prompt'))) {
      return FALSE;
    }
    if (!isset($this->chatConfiguration)) {
      $defaultProviders = $this->config->get('default_providers');
      if (empty($defaultProviders)) {
        $this->chatConfiguration = [];
      }
      $this->chatConfiguration = $defaultProviders['chat'] ?? [];
    }
    return !empty($this->chatConfiguration)
      && in_array($operation_type, $this->getSupportedOperationTypes());
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [
      'translate_text',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedCapabilities(): array {
    return [
      AiProviderCapability::StreamChatOutput,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('ai.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    $defaultProviders = $this->getConfig()->get('default_providers');
    if (empty($defaultProviders['chat'])) {
      $this->messenger()->addWarning($this->t('@plugin requires a default chat provider to be set.',
        ['@plugin' => $this->getPluginId()]));
      return [];
    }
    $chatProvider = $this->manager->createInstance($defaultProviders['chat']['provider_id']);
    $models = $chatProvider->getConfiguredModels('chat');
    return $models;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    // This provider does not have model settings.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {}

  /**
   * {@inheritdoc}
   */
  public function translateText(TranslateTextInput $input, string $model_id, array $options = []): TranslateTextOutput {
    $text = $input->getText();

    // We can guess source, but not target language.
    /** @var \Drupal\language\Entity\ConfigurableLanguage $targetLanguage */
    $targetLanguage = $this->entityTypeManager->getStorage('configurable_language')->load($input->getTargetLanguage());
    if (!$targetLanguage) {
      // @todo TranslateText-specific exception, documented in
      // TranslateTextInterface::translateText() docblock.
      $this->loggerFactory->get('ai_translate')->warning(
        $this->t('Unable to guess target language, code @langcode',
          ['@langcode' => $input->getTargetLanguage()]));
      return new TranslateTextOutput('', '', '');
    }

    $aiConfig = $this->configFactory->get('ai_translate.settings')->get('language_settings') ?? [];
    $prompt = NULL;
    // Get target language-specific prompt, if it exists.
    if ($aiConfig[$targetLanguage->getId()]['prompt']) {
      $promptId = $aiConfig[$targetLanguage->getId()]['prompt'];
      if ($languageSpecificPrompt = $this->configFactory->get('ai.ai_prompt.' . $promptId)->get('prompt')) {
        $prompt = $languageSpecificPrompt;
      }
    }

    // If no language-specific prompt config exists, fall back to the default.
    if (!$prompt) {
      $promptId = $this->configFactory->get('ai_translate.settings')->get('prompt');
      $prompt = $this->configFactory->get('ai.ai_prompt.' . $promptId)->get('prompt');
    }

    // Define replacement variables.
    $twigContext = [];
    $replacements = [
      '{destLang}' => $targetLanguage->getId(),
      '{destLangName}' => $targetLanguage->getName(),
      '{inputText}' => $text,
    ];
    try {
      /** @var \Drupal\language\Entity\ConfigurableLanguage $sourceLanguage */
      $sourceLanguage = $this->entityTypeManager->getStorage('configurable_language')->load($input->getSourceLanguage());
      if ($sourceLanguage) {
        $twigContext['sourceLang'] = $sourceLanguage->getId();
        $replacements['{sourceLang}'] = $sourceLanguage->getId();
        $twigContext['sourceLangName'] = $sourceLanguage->getName();
        $replacements['{sourceLangName}'] = $sourceLanguage->getName();
      }
    }
    // Ignore failure to load source language.
    catch (\AssertionError) {
    }
    // Only use Twig for conditional logic. We only need source lang Twig
    // variables for this use case. Other variables are replaced via AI prompts
    // replacement logic.
    $promptText = (string) $this->twig->renderInline($prompt, $twigContext);
    $promptText = strtr($promptText, $replacements);
    try {
      $messages = new ChatInput([
        new chatMessage('user', $promptText),
      ]);
      $messages->setSystemPrompt('You are a helpful translator.');

      $this->loadTranslator($messages);
      /** @var /Drupal\ai\OperationType\Chat\ChatOutput $message */
      $message = $this->realTranslator->chat($messages, $this->chatConfiguration['model_id'], ['ai_translate']);
    }
    catch (GuzzleException $exception) {
      // Error handling for the API call.
      $this->loggerFactory->get('ai_translate')
        ->warning($exception->getMessage());
      return new TranslateTextOutput('', '', '');
    }

    return new TranslateTextOutput($message->getNormalized()->getText(),
      $message->getRawOutput(), []);
  }

  /**
   * Load real translator and its configuration.
   *
   * @return \Drupal\ai\Plugin\ProviderProxy|null
   *   Real provider or NULL on failure.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function loadTranslator(ChatInput $messages) :? ProviderProxy {
    if (isset($this->realTranslator)) {
      return $this->realTranslator;
    }
    $chatConfig = $this->manager->getDefaultProviderForOperationType('chat');

    // Allow other modules to take over.
    $this->moduleHandler->alter('ai_translate_translation', $messages,
      $chatConfig['provider_id'], $chatConfig['model_id']);

    $this->realTranslator = $this->manager->createInstance($chatConfig['provider_id'],
      $chatConfig);
    $this->chatConfiguration = $chatConfig;
    return $this->realTranslator;
  }

}
