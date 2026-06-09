<?php

namespace Drupal\ai_translate;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\TranslateText\TranslateTextInput;

/**
 * Defines text translator service.
 */
class TextTranslator implements TextTranslatorInterface {

  use LoggerChannelTrait;

  /**
   * AI module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $aiConfig;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
    protected ConfigFactoryInterface $configFactory,
    protected AiProviderPluginManager $aiProviderManager,
    protected TwigEnvironment $twig,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    $this->aiConfig = $this->configFactory->get('ai.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function translateContent(
    string $input_text,
    LanguageInterface $langTo,
    ?LanguageInterface $langFrom = NULL,
    array $context = [],
  ) : string {
    try {
      /** @var \Drupal\ai\OperationType\TranslateText\TranslateTextInterface $provider */
      $providerConfig = $this->aiProviderManager->getDefaultProviderForOperationType('translate_text');
      $provider = $this->aiProviderManager->createInstance($providerConfig['provider_id'], $providerConfig);
      $translation = $provider->translateText(
        new TranslateTextInput($input_text, $langFrom?->getId(), $langTo->getId()),
        $providerConfig['model_id']
      );

      $cleaned = trim(trim($translation->getNormalized(), '```'), ' ');
      return trim($cleaned, '"');
    }
    catch (\Throwable $e) {
      $this->getLogger('ai_translate')->warning($e->getMessage());
      throw new TranslationException($e->getMessage());
    }
  }

}
