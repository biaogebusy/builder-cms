<?php

namespace Drupal\xinshi_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;

/**
 * The account profile of the current user.
 */
class AccountController extends XinshiApiControllerBase {

  /**
   * @return CacheableJsonResponse
   */
  public function accountProfile() {
    $user = $this->currentUser();
    $data['uid'] = $user->id();
    $data['name'] = $user->getDisplayName();
    foreach ($user->getRoles() as $key => $role) {
      $data['roles'][] = $role;
    }
    $this->addCacheTags(['user:' . $user->id()]);
    $this->addCacheContexts(['user']);
    return $this->getResponse($data);
  }

}
