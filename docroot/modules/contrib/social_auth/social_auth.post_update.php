<?php

/**
 * @file
 * Method hook_post_update_NAME.
 */

/**
 * Encrypts all tokens currently stored by Social Auth.
 */
function social_auth_post_update_encrypt_tokens(&$sandbox = NULL) {
  $storage = \Drupal::entityTypeManager()->getStorage('social_auth');
  // Initializes some variables during the first pass through.
  if (!isset($sandbox['total'])) {
    $sandbox['total'] = $storage->getQuery()->accessCheck(FALSE)->count()->execute();
    $sandbox['progress'] = 0;
  }

  $ids = $storage->getQuery()->checkAccess(FALSE)->range($sandbox['progress'], 50)->execute();
  /** @var \Drupal\social_auth\Entity\SocialAuth[] $social_auth_users */
  $social_auth_users = $storage->loadMultiple($ids);
  foreach ($social_auth_users as $user) {
    $token = $user->get('token')->value;
    // Sets token take care of the encryption.
    $user->setToken($token)->save();
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = ($sandbox['total'] == $sandbox['progress']) ? 1 : $sandbox['progress'] / $sandbox['total'];

  // Once finished.
  if ($sandbox['#finished']) {
    return t('Updated %n out of %t Social Auth users', [
      '%n' => $sandbox['progress'],
      '%t' => $sandbox['total'],
    ]);
  }
}
