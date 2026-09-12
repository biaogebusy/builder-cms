<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Utility;

/**
 * Provides the utility class for accessing suggestions data in DOM.
 */
class DomSuggestion {

  /**
   * Creates the DOM suggestion element.
   *
   * @param \DOMElement $element
   *   The DOM elemement.
   */
  public function __construct(protected \DOMElement $element) {
  }

  /**
   * Gets the suggestion type.
   *
   * @return string
   *   The suggestion type.
   */
  public function getType(): string|NULL {
    $value = $this->isStartTag() || $this->isEndTag()
      ? $this->getNameAttributeValue() : ($this->getStartAttributeValue() ?? $this->getEndAttributeValue());

    if (!$value) {
      return NULL;
    }

    [$type, $id, $uid] = explode(':', $value);

    return $type;
  }

  /**
   * Gets the name attribute value.
   *
   * @return string|null
   *   The attribute value or NULL if an error occur.
   */
  public function getNameAttributeValue(): string|NULL {
    try {
      return $this->element->getAttribute('name');
    }
    catch (\Error $e) {
      return NULL;
    }
  }

  /**
   * Gets the data attribute value of the suggestion end.
   *
   * @return string|null
   *   The attribute value or NULL if an error occur.
   */
  public function getEndAttributeValue(): string|NULL {
    try {
      $value = $this->element->getAttribute('data-suggestion-end-after');
      if (!$value) {
        $value = $this->element->getAttribute('data-suggestion-end-before');
      }
      return $value;
    }
    catch (\Error $e) {
      return NULL;
    }
  }

  /**
   * Gets the data attribute value of the suggestion start.
   *
   * @return string|null
   *   The attribute value or NULL if an error occur.
   */
  public function getStartAttributeValue(): string|NULL {
    try {
      return $this->element->getAttribute('data-suggestion-start-before');
    }
    catch (\Error $e) {
      return NULL;
    }
  }

  /**
   * Check if this is the suggestion closing tag (end).
   *
   * @return bool
   *   True if end tag, false otherwise.
   */
  public function isEndTag(): bool {
    try {
      return $this->element->nodeName === 'suggestion-end';
    }
    catch (\Error $e) {
      return FALSE;
    }
  }

  /**
   * Check if this is the suggestion opening tag (start).
   *
   * @return bool
   *   True if start tag, false otherwise.
   */
  public function isStartTag(): bool {
    try {
      return $this->element->nodeName === 'suggestion-start';
    }
    catch (\Error $e) {
      return FALSE;
    }
  }

  /**
   * Checks if the given element is of type insertion.
   *
   * @return bool
   *   True if this is the insertion, false otherwise.
   */
  public function isInsertion(): bool {
    return $this->getType() === 'insertion';
  }

  /**
   * Compares the name attribute value with the given one.
   *
   * @param string $name
   *   The name to be used in comparison.
   *
   * @return bool
   *   True if the names are equal, false otherwise.
   */
  public function hasName(string $name): bool {
    return $this->getNameAttributeValue() === $name;
  }

}
