<?php

namespace Drupal\ai_translate\Controller;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\ai_translate\TextExtractorInterface;
use Drupal\ai_translate\TextTranslatorInterface;
use Drupal\ai_translate\TranslationException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines an AI Translate Controller.
 */
class AiTranslateController extends ControllerBase {

  use DependencySerializationTrait;

  /**
   * Text extractor service.
   *
   * @var \Drupal\ai_translate\TextExtractorInterface
   */
  protected TextExtractorInterface $textExtractor;

  /**
   * Text translator service.
   *
   * @var \Drupal\ai_translate\TextTranslatorInterface
   */
  protected TextTranslatorInterface $aiTranslator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->languageManager = $container->get('language_manager');
    $instance->textExtractor = $container->get('ai_translate.text_extractor');
    $instance->aiTranslator = $container->get('ai_translate.text_translator');
    return $instance;
  }

  /**
   * Add requested entity translation to the original content.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $entity_id
   *   Entity ID.
   * @param string $lang_from
   *   Source language code.
   * @param string $lang_to
   *   Target language code.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   the function will return a RedirectResponse to the translation
   *   overview page by showing a success or error message.
   */
  public function translate(string $entity_type, string $entity_id, string $lang_from, string $lang_to) {
    static $langNames;
    if (empty($langNames)) {
      $langNames = $this->languageManager->getNativeLanguages();
    }
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);

    // From UI, translation is always request from default entity language,
    // but nothing stops users from using different $lang_from.
    if ($entity->language()->getId() !== $lang_from
      && $entity->hasTranslation($lang_from)) {
      $entity = $entity->getTranslation($lang_from);
    }

    $redirectUrl = ('edit' === $this->config('ai_translate.settings')
      ->get('redirect_after_create'))
      ? $entity->toUrl('edit-form', ['language' => $langNames[$lang_to]])
      : $entity->toUrl('drupal:content-translation-overview');
    $response = new RedirectResponse($redirectUrl->setAbsolute()->toString());

    // @todo support updating existing translations.
    if ($entity->hasTranslation($lang_to)) {
      $this->messenger()->addMessage('Translation already exists.');
      $response->send();
      return $response;
    }

    $textMetadata = $this->textExtractor->extractTextMetadata($entity);

    // Creates a batch builder to translate text metadata.
    $batchBuilder = (new BatchBuilder())
      ->setTitle($this->t('Translating entity content with AI'))
      ->setInitMessage($this->t('Batch is starting'))
      ->setErrorMessage($this->t('Batch has encountered an error'));

    foreach ($textMetadata as $singleMeta) {
      $batchBuilder->addOperation([$this, 'translateSingleField'], [
        $singleMeta,
        $langNames[$lang_from],
        $langNames[$lang_to],
      ]);
    }
    $batchBuilder->addOperation([$this, 'insertTranslation'], [$entity, $lang_to]);
    batch_set($batchBuilder->toArray());
    return batch_process($redirectUrl);
  }

  /**
   * Finished operation.
   */
  public static function finish($success, $results, $operations, $duration) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addMessage(t('All terms have been processed.'));
    }
  }

  /**
   * Batch callback - translate a single (text) field.
   *
   * @param array $singleField
   *   Chunk of (text) field metadata to translate.
   * @param \Drupal\Core\Language\LanguageInterface $langFrom
   *   The source language.
   * @param \Drupal\Core\Language\LanguageInterface $langTo
   *   The target language.
   * @param array $context
   *   The batch context.
   */
  public function translateSingleField(
    array $singleField,
    LanguageInterface $langFrom,
    LanguageInterface $langTo,
    array &$context,
  ) {
    // Get translations for each extracted field property.
    $translated_text = [];
    foreach ($singleField['_columns'] as $column) {
      try {
        $translated_text[$column] = '';
        if (!empty($singleField[$column])) {
          $translated_text[$column] = $this->aiTranslator->translateContent(
            $singleField[$column], $langTo, $langFrom);
        }
      }
      catch (TranslationException) {
        $context['results']['failures'][] = $singleField[$column];
        return;
      }
    }

    // Decodes HTML entities in translation.
    // Because of sanitation in StringFormatter/Markup, this should be safe.
    foreach ($translated_text as &$translated_text_item) {
      $translated_text_item = html_entity_decode($translated_text_item);
    }

    $singleField['translated'] = $translated_text;
    $context['results']['processedTranslations'][] = $singleField;
  }

  /**
   * Batch callback - insert processed texts back into the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity to translate.
   * @param string $lang_to
   *   Language code of translation.
   * @param array $context
   *   Text metadata containing both source values and translation.
   */
  public function insertTranslation(
    ContentEntityInterface $entity,
    string $lang_to,
    array &$context,
  ) {
    $translation = $entity->addTranslation($lang_to, $entity->toArray());

    // Handle published status based on configuration setting.
    if ($entity instanceof EntityPublishedInterface) {
      $config = $this->config('ai_translate.settings');
      $translation_status = $config->get('translation_status') ?? 'keep_original';
      if ('create_draft' === $translation_status) {
        if ($entity->getEntityType()->isRevisionable()) {
          $translation->setRevisionTranslationAffected(NULL);
        }
        // Is there a better way to detect moderation state?
        if ($entity->hasField('moderation_state')) {
          $translation->set('moderation_state', 'draft');
        }
        // Content moderation module sets draft states to unpublish.
        $translation->setUnpublished();
      }
    }

    $this->textExtractor->insertTextMetadata($translation,
      $context['results']['processedTranslations'] ?? []);
    try {
      $translation->save();
      $this->messenger()->addStatus($this->t('Content translated successfully.'));
    }
    catch (\Throwable $exception) {
      $this->getLogger('ai_translate')->warning($exception->getMessage());
      $this->messenger()->addError($this->t('There was some issue with content translation.'));
    }
  }

}
