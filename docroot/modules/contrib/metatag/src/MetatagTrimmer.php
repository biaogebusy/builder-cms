<?php

namespace Drupal\metatag;

use PHPUnit\Framework\Exception;

/**
 * MetatagTrimmer service class for trimming metatags.
 */
class MetatagTrimmer {

  /**
   * Trims a given string after the word on the given length.
   *
   * @param string $string
   *   The string to trim.
   * @param int $maxlength
   *   The maximum length where the string approximately gets trimmed.
   *
   * @return string
   *   The trimmed string.
   */
  public function trimAfterValue($string, $maxlength): string {
    // If the string is shorter than the max length then skip the rest of the
    // logic.
    if ($maxlength > mb_strlen($string)) {
      return $string;
    }

    $spacePos = mb_strpos($string, ' ', $maxlength - 1);
    if (FALSE === $spacePos) {
      return $string;
    }
    $subString = mb_substr($string, 0, $spacePos);

    return trim($subString);
  }

  /**
   * Trims a given string before the word on the given length.
   *
   * @param string $string
   *   The string to trim.
   * @param int $maxlength
   *   The maximum length where the string approximately gets trimmed.
   *
   * @return string
   *   The trimmed string.
   */
  public function trimBeforeValue($string, $maxlength): string {
    // If the string is shorter than the max length then skip the rest of the
    // logic.
    if ($maxlength > mb_strlen($string)) {
      return $string;
    }

    $subString = mb_substr($string, 0, $maxlength + 1);
    if (' ' === mb_substr($subString, -1)) {
      return trim($subString);
    }
    $spacePos = mb_strrpos($subString, ' ', 0);
    if (FALSE === $spacePos) {
      return $string;
    }
    $returnedString = mb_substr($string, 0, $spacePos);

    return trim($returnedString);
  }

  /**
   * Trims characters at the end of a string.
   *
   * @param string $string
   *   The string to apply the trimming to.
   * @param string $trimEndChars
   *   The characters to trim at the end of the string.
   *
   * @return string
   *   The string with the requested end characters removed.
   */
  public function trimEndChars(string $string, string $trimEndChars = ''): string {
    if (empty($trimEndChars)) {
      return rtrim($string);
    }
    else {
      // Note the use of str_replace() so "\" won't be recognized as a parameter
      // for an escape sequence.
      return rtrim($string, " \n\r\t\v\x00" . str_replace("\\", "\\\\", $trimEndChars));
    }
  }

  /**
   * Trims a value based on the given length and the given method.
   *
   * @param string $value
   *   The string to trim.
   * @param int $maxlength
   *   The maximum length where the string approximately gets trimmed.
   * @param string $method
   *   The trim method to use for the trimming.
   *   Allowed values: 'afterValue', 'onValue' and 'beforeValue'.
   * @param string $trimEndChars
   *   The characters to trim at the end of the string.
   * @param string $suffix
   *   Characters to optionally add to the end of the trimmed string.
   *
   * @return string
   *   The updated string.
   */
  public function trimByMethod($value, $maxlength, $method, $trimEndChars = '', $suffix = ''): string {
    if (empty($value) || empty($maxlength)) {
      return $value;
    }

    // If the string is shorter than the max length then skip the rest of the
    // logic.
    if ($maxlength > mb_strlen($value)) {
      return $value;
    }

    if ($trimEndChars === NULL) {
      $trimEndChars = '';
    }

    switch ($method) {
      case 'afterValue':
        $value = $this->trimAfterValue($value, $maxlength);
        break;

      case 'onValue':
        $value = trim(mb_substr($value, 0, $maxlength));
        break;

      case 'beforeValue':
        $value = $this->trimBeforeValue($value, $maxlength);
        break;

      default:
        throw new Exception('Unknown trimming method: ' . $method);
    }

    // Do additional cleanup trimming, append the suffix.
    return $this->trimEndChars($value, $trimEndChars) . $suffix;
  }

}
