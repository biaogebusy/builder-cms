<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features\Diff;

use Caxy\HtmlDiff\HtmlDiff;
use Drupal\ckeditor5_premium_features\Utility\Html;

/**
 * Ckeditor5 extension of an external library for detecting string changes.
 */
class Ckeditor5HtmlDiff extends HtmlDiff {

  /**
   * Returns a string with content parts, added to the compared document.
   */
  public function getAddedContent(): string {
    $addedParts = [];
    $operations = $this->operations();
    foreach ($operations as $operation) {
      switch ($operation->action) {
        case 'insert':
        case 'replace':
          $newWordsImploded = implode('', array_slice(
            $this->newWords,
            $operation->startInNew,
            $operation->endInNew - $operation->startInNew
          ));
          $addedParts[] = $newWordsImploded;
          break;
      }
    }

    $allNewContentParts = implode(PHP_EOL, $addedParts);

    return $this->fixHtmlWithPotentiallyImproperHtml($allNewContentParts);
  }

  /**
   * Returns an array of all changes made to the document..
   */
  public function getChanges(): array {
    $changes = [];
    $operations = $this->operations();
    foreach ($operations as $operation) {
      switch ($operation->action) {
        case 'equal':
          break;
        default:
          $addedChunks = array_slice(
            $this->newWords,
            $operation->startInNew,
            $operation->endInNew - $operation->startInNew
          );
          $added = implode('', $addedChunks);

          $removedChunks = array_slice(
            $this->oldWords,
            $operation->startInOld,
            $operation->endInOld - $operation->startInOld
          );
          $removed = implode('', $removedChunks);

          $changes[] = [
            'action' => $operation->action,
            'added' => $added,
            'removed' => $removed,
          ];
          break;
      }
    }

    return $changes;
  }

  /**
   * Returns string representing document with marked detected changes.
   */
  public function getContext() :string {
    return $this->content;
  }

  /**
   * Process passed HTML to try to fix bad HTML.
   *
   * @param string $htmlString
   *   String to be processed using \DOMDocument.
   */
  protected function fixHtmlWithPotentiallyImproperHtml(string $htmlString): string {
    $document = Html::load($htmlString);

    $xpath = new \DOMXPath($document);
    $bodyElement = $xpath->query('//body')->item(0);

    $htmlRes = '';

    foreach ($bodyElement->childNodes as $node) {
      $htmlRes .= $document->saveHTML($node);
    }

    return $htmlRes;
  }

  /**
   * Override base method. Better regex for getting attribute.
   *
   * @param string $text
   *   Text.
   * @param string $attribute
   *   Attribute to find.
   *
   * @return null|string
   *   Value of attribute or null.
   */
  protected function getAttributeFromTag($text, $attribute): ?string {
    $matches = [];
    if (preg_match(sprintf('/<[^>]*?%s=(["\'])?((?:.(?!\1|>))*.?)\1?/', $attribute), $text, $matches)) {
      return htmlspecialchars_decode($matches[2]);
    }

    return NULL;
  }

}
