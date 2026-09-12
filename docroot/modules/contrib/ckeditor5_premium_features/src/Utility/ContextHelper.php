<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Utility;

/**
 * Class offering helper methods for collecting notification context.
 */
class ContextHelper implements ContextHelperInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\ckeditor5_premium_features\Utility\HtmlHelper $htmlHelper
   *   Collaboration HTML helper.
   */
  public function __construct(
    protected HtmlHelper $htmlHelper,
    protected LibraryVersionChecker $libraryVersionChecker
  ) {
  }

  /**
   * Returns an array of strings with document detected changes.
   *
   * @param string $context
   *   Document content.
   * @param bool $onlyInserts
   *   Flag for determining type of changes to be selected.
   */
  public function getDocumentChangesContext(string $context, bool $onlyInserts = FALSE): array {
    $query = "//ins" . ($onlyInserts ? '' : '|//del');

    $snippets = [];
    foreach ($this->getMatchingContext($context, $query, FALSE) as $markup) {
      $snippets[] = $markup;
    }

    return $snippets;
  }

  /**
   * Returns elements list with optional class for highlighting matched element.
   *
   * @param string $context
   *   Source HTML content.
   * @param string $query
   *   XPATH query that will be used for selecting matching HTML part.
   * @param bool $highlight
   *   Flag to determine if a found element should receive highlight class.
   *
   * @return array
   *   List of string representing matching element context.
   */
  protected function getMatchingContext(string $context, string $query, bool $highlight = TRUE): array {
    $document = Html::load($context);

    $contextParts = [];

    $xpath = new \DOMXPath($document);
    $matchingElements = $xpath->query($query);
    if (!empty($matchingElements)) {
      /** @var \DOMElement $element */
      foreach ($matchingElements as $element) {
        if ($highlight) {
          $element->setAttribute('class', $element->getAttribute('class') . ' highlight-item');
        }
        // Let's prevent selecting same parent node several times (when several
        // changes were made in the same paragraph tag).
        $parentNode = $this->htmlHelper->selectElementParentNode($element);
        $parentPath = $parentNode->getNodePath();
        $matched = FALSE;
        foreach ($contextParts as $nodePath => $html) {
          if (stripos($nodePath, $parentPath) !== FALSE) {
            // Let's prefer to choose parent node instead of it;s children.
            unset($contextParts[$nodePath]);
          }
          elseif (stripos($parentPath, $nodePath) !== FALSE) {
            // Let's also detect a situation when we select a child of a parent
            // that we already selected.
            $matched = TRUE;
            break;
          }
        }
        if (!$matched) {
          $contextParts[$parentPath] = $element->ownerDocument->saveXML($parentNode);
        }
      }
    }
    return $contextParts;
  }

}
