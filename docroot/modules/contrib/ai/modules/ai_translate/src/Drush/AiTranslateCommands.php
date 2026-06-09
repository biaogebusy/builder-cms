<?php

namespace Drupal\ai_translate\Drush;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai_translate\TextExtractorInterface;
use Drupal\ai_translate\TextTranslatorInterface;
use Drupal\ai_translate\TranslationException;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AI translate drush commands.
 */
class AiTranslateCommands extends DrushCommands {

  use LoggerChannelTrait;
  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Text extractor.
   *
   * @var \Drupal\ai_translate\TextExtractorInterface
   */
  protected TextExtractorInterface $textExtractor;

  /**
   * Text translation service.
   *
   * @var \Drupal\ai_translate\TextTranslatorInterface
   */
  protected TextTranslatorInterface $textTranslator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = new static();
    $instance->languageManager = $container->get('language_manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->textExtractor = $container->get('ai_translate.text_extractor');
    $instance->textTranslator = $container->get('ai_translate.text_translator');
    return $instance;
  }

  /**
   * Create AI-powered translation of an entity.
   */
  #[Command(
    name: 'ai:translate-entity'
  )]
  #[Argument(name: 'entityType', description: 'Entity type (i.e. node)')]
  #[Argument(name: 'entityIds', description: 'Comma-separated entity IDs (i.e 16,18,20,21)')]
  #[Argument(name: 'langFrom', description: 'Source language code (i.e. fr)')]
  #[Argument(name: 'langTo', description: 'Target language code (i.e. en)')]
  public function translateEntities(
    string $entityType,
    string $entityIds,
    string $langFrom,
    string $langTo,
  ) {
    static $langNames;
    if (empty($langNames)) {
      $langNames = $this->languageManager->getNativeLanguages();
    }
    $ids = array_filter(explode(',', $entityIds));
    $entityStorage = $this->entityTypeManager->getStorage($entityType);
    foreach ($entityStorage->loadMultiple($ids) as $entity) {
      if ($entity->language()->getId() !== $langFrom
        && $entity->hasTranslation($langFrom)) {
        $entity = $entity->getTranslation($langFrom);
      }
      if ($entity->hasTranslation($langTo)) {
        $this->messenger()->addMessage(
          $this->t('Translation already exists.'));
        continue;
      }
      $textMetadata = $this->textExtractor->extractTextMetadata($entity);
      foreach ($textMetadata as &$singleField) {
        // Get translations for each extracted field property.
        foreach ($singleField['_columns'] as $column) {
          try {
            $singleField['translated'][$column] = '';
            if (!empty($singleField[$column])) {
              $singleField['translated'][$column] = $this->textTranslator->translateContent(
                $singleField[$column], $langNames[$langTo], $langNames[$langFrom] ?? NULL);
            }
          }
          catch (TranslationException) {
            // Error already logged by text_translate service.
            $this->messenger()->addError('Error translating content.');
            continue;
          }
        }

        // Decodes HTML entities in translation.
        // Because of sanitation in StringFormatter/Markup, this should be safe.
        foreach ($singleField['translated'] as &$translated_text_item) {
          $translated_text_item = html_entity_decode($translated_text_item);
        }
      }
      $translation = $entity->addTranslation($langTo, $entity->toArray());
      $this->textExtractor->insertTextMetadata($translation,
        $textMetadata);
      try {
        $entityStorage->save($translation);
        $this->messenger()
          ->addStatus($this->t('Content translated successfully.'));
      }
      catch (\Throwable $exception) {
        $this->getLogger('ai_translate')->warning($exception->getMessage());
        $this->messenger()
          ->addError($this->t('There was some issue with content translation.'));
      }
    }
  }

  /**
   * Create AI-powered translation of a text.
   */
  #[Command(
    name: 'ai:translate-text'
  )]
  #[Argument(name: 'text', description: 'Text to translate')]
  #[Argument(name: 'langTo', description: 'Target language code (i.e. en)')]
  #[Argument(name: 'langFrom', description: 'Source language code (i.e. fr)')]
  public function translate(
    string $text,
    string $langFrom,
    string $langTo,
  ) {
    static $langNames;
    if (empty($langNames)) {
      $langNames = $this->languageManager->getNativeLanguages();
    }
    try {
      return $this->textTranslator->translateContent($text,
        $langNames[$langTo], $langNames[$langFrom] ?? NULL);
    }
    catch (TranslationException) {
      // Error already logged by text_translate service.
      $this->messenger()->addError('Error translating content.');
      return;
    }
  }

}
