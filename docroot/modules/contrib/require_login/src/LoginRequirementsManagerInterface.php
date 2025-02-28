<?php

namespace Drupal\require_login;

use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Provides interface for login requirements manager.
 */
interface LoginRequirementsManagerInterface {

  /**
   * Evaluate login requirement for a request.
   *
   * @param \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface|null $exception
   *   The throwable request exception.
   *
   * @return \Drupal\Core\Url|false
   *   Returns login URL if authentication is required. Otherwise, FALSE.
   */
  public function evaluate(HttpExceptionInterface $exception = NULL);

  /**
   * Get the login redirect and set message.
   *
   * @return \Drupal\Core\Url
   *   Returns the login URL with destination.
   */
  public function redirect(): Url;

}
