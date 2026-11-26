<?php

namespace Drupal\xinshi_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\user\RoleInterface;

/**
 * Lists the roles the builder offers for role-restricted content.
 *
 * JSON:API exposes role entities only with "administer permissions", which
 * branding editors do not hold; the builder needs just the id and label.
 */
class RoleController extends XinshiApiControllerBase {

  /**
   * Returns every role a signed-in user can hold, as a flat list.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   Each item carries the role id and its label in the request language.
   */
  public function roleList(): CacheableJsonResponse {
    $this->addCacheTags(['config:user_role_list']);
    // Role labels follow configuration translation.
    $this->addCacheContexts(['languages:language_interface']);
    $data = [];
    /** @var \Drupal\user\RoleInterface $role */
    foreach ($this->entityTypeManager()->getStorage('user_role')->loadMultiple() as $role) {
      // The frontend hides role-restricted content from anonymous visitors,
      // so the anonymous role can never match.
      if ($role->id() === RoleInterface::ANONYMOUS_ID) {
        continue;
      }
      $this->addCacheTags($role->getCacheTags());
      $data[] = [
        'id' => $role->id(),
        'label' => (string) $role->label(),
      ];
    }
    return $this->getResponse($data);
  }

}
