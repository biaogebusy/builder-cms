<?php

namespace Drupal\ai_api_explorer;

/**
 * Helper functions for the forms.
 *
 * @package Drupal\ai_api_explorer
 */
class ExplorerHelper {

  /**
   * Function to render an exception in a modal.
   *
   * @param \Exception $e
   *   The exception to render.
   *
   * @return string
   *   The rendered exception.
   */
  public static function renderException(\Exception $e): string {
    $message = '<div class="ai-error">';
    $message .= '<h2>' . t('An error occurred') . '</h2>';
    $message .= '<p>' . t('The following error occurred while processing your request:') . '</p>';
    $message .= '<p><em>' . $e->getMessage() . '</em></p>';
    $message .= '<p>' . t('Of the following exception type:') . '</p>';
    $message .= '<p>' . get_class($e) . '</p>';
    $message .= '</div>';
    return $message;
  }

  /**
   * Function to render an error.
   *
   * @param string $message
   *   The error message to render.
   *
   * @return string
   *   The rendered error.
   */
  public static function renderError(string $message): string {
    $message = '<div class="ai-error">';
    $message .= '<h2>' . t('An error occurred') . '</h2>';
    $message .= '<p>' . t('The following error occurred while processing your request:') . '</p>';
    $message .= '<p><em>' . $message . '</em></p>';
    $message .= '</div>';
    return $message;
  }

}
