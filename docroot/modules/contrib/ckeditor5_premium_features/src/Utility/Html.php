<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features\Utility;

class Html extends \Drupal\Component\Utility\Html {

  /**
   * Parses an HTML snippet and returns it as a DOM object.
   *
   * This is a \Drupal\Component\Utility\Html::load() override without a
   * LIBXML_NOBLANKS flag.
   *
   * This function loads the body part of a partial (X)HTML document and returns
   * a full \DOMDocument object that represents this document.
   *
   * Use \Drupal\Component\Utility\Html::serialize() to serialize this
   * \DOMDocument back to a string.
   *
   * @param string $html
   *   The partial (X)HTML snippet to load. Invalid markup will be corrected on
   *   import.
   *
   * @return \DOMDocument
   *   A \DOMDocument that represents the loaded (X)HTML snippet.
   */
  public static function load($html) {
    $document = <<<EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /></head>
<body>!html</body>
</html>
EOD;
    // PHP's \DOMDocument serialization adds extra whitespace when the markup
    // of the wrapping document contains newlines, so ensure we remove all
    // newlines before injecting the actual HTML body to be processed.
    $document = strtr($document, ["\n" => '', '!html' => $html]);

    $dom = new \DOMDocument();
    // Ignore warnings during HTML soup loading.
    @$dom->loadHTML($document);

    return $dom;
  }

}
