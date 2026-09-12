<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Tools;

/**
 * Disables doing a real logout and instead just removes session info from test.
 */
trait LogoutTrait {

  /**
   * Content locks are removed on user logout. This makes testing hard.
   *
   * @var bool
   */
  protected bool $userLogout = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function drupalLogout() {
    if ($this->userLogout) {
      parent::drupalLogout();
      return;
    }

    // Don't actually log out. Just reset the session in the test.
    $this->getSession()->setCookie(\Drupal::service('session_configuration')->getOptions(\Drupal::request())['name']);
    $this->drupalResetSession();
  }

}
