<?php


namespace Drupal\xinshi_sms;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Session\AccountInterface;

/**
 * Class AccessCheck
 * @package Drupal\xinshi_sms
 */
class AccessCheck {
  /**
   * Checks if the user is user 1 and grants access if so.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkSignin(AccountInterface $account) {
    // The access result is uncacheable because it is just limiting access to
    // the migrate UI which is not worth caching.
    $config = \Drupal::configFactory()->get('xinshi_sms.settings');
    return AccessResultAllowed::allowedIf($config->get('activate'))->addCacheTags($config->getCacheTags());
  }

  /**
   * Checks if the user is user 1 and grants access if so.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function FindPassword(AccountInterface $account) {
    // The access result is uncacheable because it is just limiting access to
    // the migrate UI which is not worth caching.
    $config = \Drupal::configFactory()->get('xinshi_sms.settings');
    return AccessResultAllowed::allowedIf($config->get('enabled_find_password') && empty($config->get('override_reset_pass')))->addCacheTags($config->getCacheTags());
  }
}
