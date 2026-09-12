<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_mentions\DataProvider;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ckeditor5_premium_features_mentions\Utility\MentionSettings;
use Drupal\user\RoleInterface;
use Drupal\user\RoleStorageInterface;
use Drupal\user\UserStorageInterface;

/**
 * Provides the user data for the mentions autocomplete.
 */
class MentionDataProvider {

  /**
   * Tag so that other modules may alter the entity query below.
   *
   * This may be useful for sites with a large number of users, editorial
   * sections, groups, etc.
   *
   * @see \hook_entity_query_tag__TAG_alter()
   * @see \hook_entity_query_tag__ENTITY_TYPE__TAG_alter()
   */
  public const PRIVILEGED_EDITORS_QUERY_TAG = 'ckeditor5_premium_features_mentions';

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface|\Drupal\Core\Entity\EntityStorageInterface
   */
  protected UserStorageInterface $userStorage;

  /**
   * The role storage.
   *
   * @var \Drupal\user\RoleStorageInterface
   */
  protected RoleStorageInterface $roleStorage;

  /**
   * The mention settings.
   *
   * @var \Drupal\ckeditor5_premium_features_mentions\Utility\MentionSettings
   */
  protected MentionSettings $mentionSettings;

  /**
   * Creates the provider instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\ckeditor5_premium_features_mentions\Utility\MentionSettings $mention_settings
   *   The mention settings.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MentionSettings $mention_settings,
  ) {
    $this->userStorage = $entity_type_manager->getStorage('user');
    $this->roleStorage = $entity_type_manager->getStorage('user_role');
    $this->mentionSettings = $mention_settings;
  }

  /**
   * Returns users matching specified query with privilege to be mentioned.
   *
   * @param string $query
   *   Username query phrase.
   * @param int $users_limit
   *   Maximum number of users to return.
   */
  public function getPrivilegedEditors(string $query, int $users_limit = 10): array {
    $offset = 0;
    $query_limit = 100;
    $role_ids = [];
    $matched_users = [];

    // If the static role condition is enabled, load all role ids that have the "to be mentioned" permission.
    if ($this->mentionSettings->useStaticPermissionCondition()) {
      $roles = $this->roleStorage->loadMultiple();
      foreach ($roles as $role) {
        if ($role instanceof RoleInterface && $role->hasPermission('to be mentioned')) {
          $role_ids[] = $role->id();
        }
      }

      if (empty($role_ids)) {
        // No roles have the "to be mentioned" permission, so return an empty array.
        return [];
      }

      $user_ids = $this->userStorage->getQuery()
        ->accessCheck(TRUE)
        ->condition('name', $query, 'CONTAINS')
        ->condition('status', 1)
        ->condition('roles', $role_ids, 'IN')
        ->range(0, $users_limit)
        ->execute();

      return $this->userStorage->loadMultiple($user_ids);
    }

    $matching_users_count = $this->userStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('name', $query, 'CONTAINS')
      ->condition('status', 1)
      ->addTag(self::PRIVILEGED_EDITORS_QUERY_TAG)
      ->count()->execute();

    do {
      $user_ids = $this->userStorage->getQuery()
        ->accessCheck(TRUE)
        ->condition('name', $query, 'CONTAINS')
        ->condition('status', 1)
        ->range($offset, $query_limit)
        ->addTag(self::PRIVILEGED_EDITORS_QUERY_TAG)
        ->execute();

      /** @var \Drupal\user\UserInterface[] $users */
      $users = $this->userStorage->loadMultiple($user_ids);

      $unprivileged_user_ids = [];
      foreach ($users as $user_to_check) {
        if (count($matched_users) >= $users_limit) {
          break;
        }

        if ($user_to_check->hasPermission('to be mentioned')) {
          $matched_users[] = $user_to_check;
        }
        else {
          $unprivileged_user_ids[] = $user_to_check->id();
        }
      }
      // Do not waste memory resources caching users that can't be mentioned.
      $this->userStorage->resetCache($unprivileged_user_ids);
    } while ($offset + $query_limit < $matching_users_count && count($matched_users) < $users_limit);

    return $matched_users;
  }

}
