<?php

namespace Drupal\xinshi_api\Plugin\rest\resource;

use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Creates a resource for account.
 *
 * @RestResource(
 *   id = "xinshi_api_account_rest",
 *   label = @Translation("Account Rest"),
 *   uri_paths = {
 *     "canonical" = "/api/v3/accountProfile"
 *   }
 * )
 */
class AccountResource extends XinshibResourceBase {

  /**
   * @param Request $request
   * @return ResourceResponse
   */
  public function get() {
    $user = \Drupal::currentUser();
    $data['uid'] = $user->id();
    $data['name'] = $user->getDisplayName();
    foreach ($user->getRoles() as $key => $role) {
      $data['roles'][] = $role;
    }
    $this->addCacheTags(['user:' . $user->id()]);
    return $this->getResponse($data);
  }

  /**
   * @param $data
   * @return ResourceResponse
   */
  protected function getResponse($data) {
    $response = new ResourceResponse($data);
    $response->getCacheableMetadata()->addCacheTags($this->getCacheTags());
    $response->getCacheableMetadata()->addCacheContexts(['user']);
    if ($this->config->get('debug')) {
      $response->getCacheableMetadata()->setCacheMaxAge(0);
    }
    return $response;
  }
}
