<?php

namespace Drupal\entity_print;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Xss;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * The exception thrown when a implementation fails to generate a document.
 */
class PrintEngineException extends \Exception {

  use StringTranslationTrait;

  /**
   * Gets a pretty version of the exception message.
   *
   * @return string
   *   The pretty message.
   */
  public function getPrettyMessage() {
    // Build a safe markup string using Xss::filter() so that the instructions
    // for installing dependencies can contain quotes.
    $default_message = (string) $this->t('Error generating document: @message', ['@message' => new FormattableMarkup(Xss::filter($this->getMessage()), [])]);

    return $this->refineMessage($this->getMessage()) ?: $default_message;
  }

  /**
   * Attempt to refine the error message to help the user.
   *
   * @param string $message
   *   The error message.
   *
   * @return string
   *   The parsed error message, possibly more refined.
   */
  protected function refineMessage($message) {
    if ($this->isAuthFailure($message)) {
      return $this->getAuthFailureMessage();
    }
    return '';
  }

  /**
   * Check if this message looks like an authorisation failure.
   *
   * @param string $message
   *   The error message.
   *
   * @return bool
   *   TRUE if it was an auth failure otherwise FALSE.
   */
  protected function isAuthFailure($message) {
    $regexs = [
      // Dompdf.
      '/401 Unauthorized/',
      // Wkhtmltopdf.
      '/AuthenticationRequiredError/',
    ];
    return $this->evaluateRegex($regexs, $message);
  }

  /**
   * Gets a new auth failure message.
   *
   * @return string
   *   The pretty version of the auth failure message.
   */
  protected function getAuthFailureMessage() {
    return $this->t('Authorisation failed, are your resources behind HTTP authentication? Check the admin page to set credentials.');
  }

  /**
   * Evaluates our patterns against the subject.
   *
   * @param array $patterns
   *   An array of regular expressions to check.
   * @param string $subject
   *   The subject to check against.
   *
   * @return bool
   *   TRUE if anyone of the patterns match otherwise FALSE.
   */
  protected function evaluateRegex(array $patterns, $subject) {
    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $subject)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
