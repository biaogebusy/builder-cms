<?php


namespace Drupal\xinshi_api;

use \Drupal\user\UserAuth as BaseUserAuth;

class UserAuth extends BaseUserAuth {
  /**
   * {@inheritdoc}
   */
  public function authenticate($username, $password) {
    $uid = FALSE;

    if (!empty($username) && strlen($password) > 0) {
      $account_search = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $username]);
      if (empty($account_search)) {
        $account_search = $this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $username]);
      }
      if ($account = reset($account_search)) {
        if ($this->passwordChecker->check($password, $account->getPassword())) {
          // Successful authentication.
          $uid = $account->id();

          // Update user to new password scheme if needed.
          if ($this->passwordChecker->needsRehash($account->getPassword())) {
            $account->setPassword($password);
            $account->save();
          }
        }
      }
    }

    return $uid;
  }

}
