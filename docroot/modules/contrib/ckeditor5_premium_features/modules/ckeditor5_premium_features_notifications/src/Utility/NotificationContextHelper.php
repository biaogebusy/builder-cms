<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\Utility;

use Drupal\ckeditor5_premium_features\CKeditorFieldKeyHelper;
use Drupal\ckeditor5_premium_features\Utility\ContextHelper;
use Drupal\ckeditor5_premium_features\Utility\Html;
use Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\CommentInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionInterface;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcCommentNotificationEntity;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcNotificationEntityInterface;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcSuggestionNotificationEntity;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Class offering helper methods for collecting notification context.
 */
class NotificationContextHelper extends ContextHelper {

  const COMMENTS_LIMIT_IN_THREAD = 5;

  /**
   * Collects a context for a collaboration entity using a document entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $document
   *   Document entity.
   * @param string $key
   *   Unique key ID to collect document value from.
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface $entity
   *   Collaboration entity.
   * @param string $newContent
   *   A new content of document.
   *
   * @return array
   *   Array with the context.
   */
  public function getFullContext(FieldableEntityInterface $document,
                                 string $key,
                                 CollaborationEntityInterface|RtcNotificationEntityInterface $entity,
                                 ?string $newContent): array {
    if ($newContent) {
      return $this->getFullContextFromDocument($newContent, $entity) ?? [];
    }

    $context = self::getDocumentFieldContent($document, $key);

    return is_string($context) ? $this->getFullContextFromDocument($context, $entity) : [];
  }

  /**
   * Collects a context for a collaboration entity.
   *
   * @param string $context
   *   A string with a document content.
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface $entity
   *   Collaboration entity.
   */
  public function getFullContextFromDocument(string $context, CollaborationEntityInterface|RtcNotificationEntityInterface $entity): array {
    $thread = $this->renderEntityThread($entity);

    $isFormattingSuggestion = FALSE;
    $snippets = [];
    if ($entity instanceof CommentInterface || $entity instanceof RtcCommentNotificationEntity) {
      $snippets = $this->getHighlightedComment($context, $entity);
    }
    if ($entity instanceof SuggestionInterface || $entity instanceof RtcSuggestionNotificationEntity) {
      $snippets = $this->getHighlightedSuggestion($context, $entity, $isFormattingSuggestion);
    }

    if (empty($snippets)) {
      return [];
    }

    $fullContext = [
      '#theme' => 'notification_message_single',
      '#context' => $snippets,
      '#thread' => $thread,
      '#formattingChange' => $isFormattingSuggestion,
    ];

    $this->setCommentsLimitInThread($fullContext, $thread);
    return $fullContext;
  }

  /**
   * Collects a context for a mention found in a document.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $document
   *   Document entity.
   * @param string $key
   *   Unique key ID to collect document value from.
   * @param string $mentionMarker
   *   Mention marker that should be found in a document.
   * @param string|null $originalContent
   *   Alternative document content that should be used if present.
   */
  public function getDocumentMentionContext(FieldableEntityInterface $document, string $key, string $mentionMarker, ?string $originalContent = NULL): array {
    $context = !empty($originalContent) ? $originalContent : self::getDocumentFieldContent($document, $key);

    $snippets = $this->getHighlightedDocumentMention($context, $mentionMarker);

    return [
      '#theme' => 'notification_message_single',
      '#context' => $snippets,
    ];
  }

  /**
   * Search for a field matching the key parameter and returns its value.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $document
   *   Document entity with fields.
   * @param string $key
   *   Unique field key.
   */
  public static function getDocumentFieldContent(FieldableEntityInterface $document, string $key): ?string {
    if (!$key) {
      return NULL;
    }

    $fields = $document->getFields();

    foreach ($fields as $fieldName => $field) {
      $values = $document->get($fieldName)->getValue();
      foreach ($values as $delta => $val) {
        $id = CKeditorFieldKeyHelper::getElementUniqueId('edit-' . $fieldName . '-' . $delta);
        if ($key == $id) {
          return $val['value'];
        }
      }
    }

    return NULL;
  }

  /**
   * Prepares a render array with highlighted suggestion markup.
   *
   * @param string $context
   *   Document content.
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\CommentInterface $comment
   *   Comment to be highlighted.
   */
  public function getHighlightedComment(string $context, CommentInterface|RtcCommentNotificationEntity $comment): array {
    $threadID = $comment->getThreadId();

    $matchingSelectRule = "contains(@name,'$threadID')";

    $context = $this->htmlHelper->prepareParagraphsSplitSuggestions($context);

    $document = Html::load($context);

    $xpath = new \DOMXPath($document);
    $this->htmlHelper->convertCommentAttributes($document, $xpath);

    $this->htmlHelper->createSuggestionsMarkers($document, $matchingSelectRule);

    $this->htmlHelper->removeNotRequiredCollaborationElements($document, 'comment', $matchingSelectRule);

    $this->htmlHelper->convertCollaborationTagsWrappings($document, 'comment', $matchingSelectRule);

    $fixedMarkup = $this->htmlHelper->getInnerHtml($document);

    $fixedMarkup = preg_replace('#<comment-start[^<>]*></comment-start>#si', '<comment>', $fixedMarkup);
    $fixedMarkup = preg_replace('#<comment-end[^<>]*></comment-end>#si', '</comment>', $fixedMarkup);
    $fixedMarkup = preg_replace('#<comment>\s*</comment>#si', '', $fixedMarkup);

    $this->replaceSuggestionMarkers($fixedMarkup);

    $query = "//comment";

    $result = [];

    foreach ($this->getMatchingContext($fixedMarkup, $query, FALSE) as $markup) {
      $result[] = [
        '#markup' => $markup,
        '#allowed_tags' => self::getNotificationAllowedTags(),
      ];
    }

    return $result;
  }

  /**
   * Prepares a render array with highlighted suggestion markup.
   *
   * @param string $context
   *   Document content.
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionInterface $suggestion
   *   Suggestion to be highlighted.
   * @param bool $formattingSuggestionDetected
   *   Returns boolean determining if the script detected formatting suggestion.
   */
  public function getHighlightedSuggestion(string $context, SuggestionInterface|RtcSuggestionNotificationEntity $suggestion, bool &$formattingSuggestionDetected = FALSE): array {
    $suggestionChain = $suggestion->getChain();

    $queryOrParts = [];
    foreach ($suggestionChain as $chainSuggestion) {
      if ($suggestion instanceof RtcSuggestionNotificationEntity) {
        $chainSuggestionId = $chainSuggestion['id'];
      }
      else {
        $chainSuggestionId = $chainSuggestion->id();
      }

      $queryOrParts[] = "contains(@name,'$chainSuggestionId')";
      $queryOrParts[] = "contains(@data-suggestion-start-before,'$chainSuggestionId')";
    }

    $matchingSelectRule = implode(' or ', $queryOrParts);

    $context = $this->htmlHelper->prepareParagraphsSplitSuggestions($context);

    $document = Html::load($context);

    $xpath = new \DOMXPath($document);
    $this->htmlHelper->convertSuggestionsAttributes($document, $xpath);

    $this->htmlHelper->createSuggestionsMarkers($document, $matchingSelectRule);

    $this->htmlHelper->removeNotRequiredCollaborationElements($document, 'suggestion', $matchingSelectRule);

    $this->htmlHelper->removeNotRequiredCollaborationElementsWithSuggestionAttributes($document, $matchingSelectRule);

    foreach ($queryOrParts as $chainPart) {
      $this->htmlHelper->convertCollaborationTagsWrappings($document, 'suggestion', $chainPart);
    }

    $fixedMarkup = $this->htmlHelper->getInnerHtml($document);

    $fixedMarkup = preg_replace('#<suggestion-start[^<>]*insertion[^<>]*></suggestion-start>#si', '<ins>', $fixedMarkup);
    $fixedMarkup = preg_replace('#<suggestion-end[^<>]*insertion[^<>]*></suggestion-end>#si', '</ins>', $fixedMarkup);

    $fixedMarkup = preg_replace('#<suggestion-start[^<>]*deletion[^<>]*></suggestion-start>#si', '<del>', $fixedMarkup);
    $fixedMarkup = preg_replace('#<suggestion-end[^<>]*deletion[^<>]*></suggestion-end>#si', '</del>', $fixedMarkup);

    if ($this->libraryVersionChecker->isLibraryVersionHigherOrEqual('40.1.0')) {
      $fixedMarkup = preg_replace('#<suggestion-start[^<>]*attribute:[^<>]*></suggestion-start>#si', '<format>', $fixedMarkup, -1, $formattingSuggestionCount);
      $fixedMarkup = preg_replace('#<suggestion-end[^<>]*attribute:[^<>]*></suggestion-end>#si', '</format>', $fixedMarkup);
    }
    else {
      $fixedMarkup = preg_replace('#<suggestion-start[^<>]*formatInline[^<>]*></suggestion-start>#si', '<format>', $fixedMarkup, -1, $formattingSuggestionCount);
      $fixedMarkup = preg_replace('#<suggestion-end[^<>]*formatInline[^<>]*></suggestion-end>#si', '</format>', $fixedMarkup);
    }
    $fixedMarkup = preg_replace('#<suggestion-start[^<>]*attribute:[^<>]*></suggestion-start>#si', '<format>', $fixedMarkup, -1, $formattingSuggestionCount);
    $fixedMarkup = preg_replace('#<suggestion-end[^<>]*attribute:[^<>]*></suggestion-end>#si', '</format>', $fixedMarkup);
    $formattingSuggestionDetected |= $formattingSuggestionCount > 0;

    $fixedMarkup = preg_replace('#<suggestion-start[^<>]*formatBlock[^<>]*></suggestion-start>#si', '<formatblock>', $fixedMarkup, -1, $formattingSuggestionCount);
    $fixedMarkup = preg_replace('#<suggestion-end[^<>]*formatBlock[^<>]*></suggestion-end>#si', '</formatblock>', $fixedMarkup);
    $formattingSuggestionDetected |= $formattingSuggestionCount > 0;

    $fixedMarkup = preg_replace('#<ins>\s*</ins>#si', '', $fixedMarkup);
    $fixedMarkup = preg_replace('#<del>\s*</del>#si', '', $fixedMarkup);
    $fixedMarkup = preg_replace('#<format>\s*</format>#si', '', $fixedMarkup);
    $fixedMarkup = preg_replace('#<formatblock>\s*</formatblock>#si', '', $fixedMarkup);

    $this->replaceSuggestionMarkers($fixedMarkup);

    $fixedMarkup = $this->htmlHelper->detectLineBreaks($fixedMarkup);

    $query = '//ins|//del|//format|//formatblock';
    $result = [];

    foreach ($this->getMatchingContext($fixedMarkup, $query, FALSE) as $markup) {
      $result[] = [
        '#markup' => $markup,
        '#allowed_tags' => self::getNotificationAllowedTags(),
      ];
    }

    return $result;

  }

  /**
   * Replace suggestion markers.
   *
   * @param string $fixedMarkup
   *   Markup.
   */
  private function replaceSuggestionMarkers(string &$fixedMarkup): void {
    $fixedMarkup = preg_replace('#<suggestion-marker-start-insertion></suggestion-marker-start-insertion>#si', '<span class="marker-insertion">', $fixedMarkup);
    $fixedMarkup = preg_replace('#<suggestion-marker-start-deletion></suggestion-marker-start-deletion>#si', '<span class="marker-deletion">', $fixedMarkup);
    $fixedMarkup = preg_replace('#<suggestion-marker-start-format></suggestion-marker-start-format>#si', '<span class="marker-format">', $fixedMarkup);
    $fixedMarkup = preg_replace('#<suggestion-marker-end></suggestion-marker-end>#si', '</span>', $fixedMarkup);
  }

  /**
   * Prepares a render array with highlighted the mention markup.
   *
   * @param string $context
   *   Document content.
   * @param string $mentionMarker
   *   Mention marker to be highlighted.
   */
  public function getHighlightedDocumentMention(string $context, string $mentionMarker): array {
    $query = "//span[contains(@data-mention,'$mentionMarker')]";

    $snippets = [];
    foreach ($this->getMatchingContext($context, $query) as $markup) {
      $snippets[] = [
        '#markup' => $markup,
      ];
    }

    return $snippets;
  }

  /**
   * Prepares a render array with highlighted document detected changes.
   *
   * @param string $context
   *   Document content.
   * @param bool $onlyInserts
   *   Flag for determining type of changes to be selected.
   */
  public function getHighlightedDocumentChanges(string $context, bool $onlyInserts = FALSE): array {
    $query = "//ins" . ($onlyInserts ? '' : '|//del');

    $snippets = [];
    foreach ($this->getMatchingContext($context, $query, FALSE) as $markup) {
      $snippets[] = [
        '#markup' => $markup,
      ];
    }

    return [
      '#theme' => 'notification_message_single',
      '#context' => $snippets,
    ];
  }

  /**
   * Returns a render array with the collaboration entity thread.
   *
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface $entity
   *   Collaboration entity that is a part of a thread.
   *
   * @return array
   *   List of render arrays representing collaboration thread.
   */
  public function renderEntityThread(CollaborationEntityInterface|RtcNotificationEntityInterface $entity): array {
    $result = [];
    foreach ($entity->getThread() as $threadItem) {
      $result[] = [
        '#theme' => 'notification_thread_comment',
        '#comment' => $threadItem,
      ];
    }
    return $result;
  }

  /**
   * Returns a list of additional collaboration tags.
   *
   * @return array
   *   List of allowed tags for notification contexts.
   */
  public static function getNotificationAllowedTags(): array {
    return array_merge(Xss::getAdminTagList(), [
      'suggestion-start',
      'suggestion-end',
      'comment-start',
      'comment-end',
      'comment',
      'del',
      'ins',
      'format',
      'formatblock',
    ]);
  }

  /**
   * Set a display limit for comments in the thread.
   *
   * If there are more than 6 comments,
   * the first one and last 5 will be displayed in the notification.
   *
   * @param array $fullContext
   *   Full context.
   * @param array $thread
   *   Thread.
   */
  protected function setCommentsLimitInThread(array &$fullContext, array $thread): void {
    if (count($thread) > self::COMMENTS_LIMIT_IN_THREAD) {
      $firstComment[] = current($thread);
      $threadCounter = count($thread) - self::COMMENTS_LIMIT_IN_THREAD;
      $thread = array_slice($thread, -(self::COMMENTS_LIMIT_IN_THREAD - 1));
      $fullContext['#thread'] = $thread;
      $fullContext['#threadCounter'] = $threadCounter;
      $fullContext['#firstComment'] = $firstComment;
    }
  }

}
