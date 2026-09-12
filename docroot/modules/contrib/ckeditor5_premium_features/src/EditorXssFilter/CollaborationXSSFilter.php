<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features\EditorXssFilter;

use Drupal\editor\EditorXssFilter\Standard;
use Drupal\filter\FilterFormatInterface;

/**
 * Ckeditor XSS filter class to keep collaboration attributes in the HTML.
 */
class CollaborationXSSFilter extends Standard {

  /**
   * {@inheritdoc}
   */
  public static function filterXss($html, FilterFormatInterface $format, ?FilterFormatInterface $original_format = NULL): string {
    $html = static::collaborationFilterText($html);
    $html = parent::filterXss($html, $format, $original_format);

    return static::collaborationFilterTextRevert($html);
  }

  /**
   * Masks collaboration tags name attribute from XSS filtering.
   *
   * @param string $original
   *   HTML string.
   */
  public static function collaborationFilterText(string $original): string {
    return self::preprocessElementValue($original, [CollaborationXSSFilter::class, 'replaceSecurity']);
  }

  /**
   * Reverts masking of collaboration tags name attribute.
   *
   * @param string $original
   *   HTML string.
   */
  public static function collaborationFilterTextRevert(string $original): string {
    return self::preprocessElementValue($original, [CollaborationXSSFilter::class, 'revertSecurityReplace']);
  }

  /**
   * Performs collaboration name attribute masking/unmasking.
   *
   * @param string $original
   *   HTML content.
   * @param callable $callback
   *   Callback to be used to process collaboration tag.
   */
  protected static function preprocessElementValue(string $original, callable $callback): string {
    $tagPattern = '(?:suggestion-start|suggestion-end|comment-start|comment-end)';
    $attributePattern = '\bname="([^"]*)"';

    $original = preg_replace_callback(
      '/<\s*' . $tagPattern . '\b[^>]*' . $attributePattern . '[^>]*>/si',
      $callback,
      $original
    );

    return $original;
  }

  /**
   * Masks collaboration tag attribute.
   *
   * @param array $tag
   *   HTML tag info.
   */
  protected static function replaceSecurity(array $tag): string {
    $original = $tag[1];
    $replaced = str_replace(":", "##", $original);
    return str_replace($original, $replaced, $tag[0]);
  }

  /**
   * Unmask collaboration tag attribute.
   *
   * @param array $tag
   *   HTML tag info.
   */
  protected static function revertSecurityReplace(array $tag): string {
    $original = $tag[1];
    $replaced = str_replace("##", ":", $original);
    return str_replace($original, $replaced, $tag[0]);
  }

}
