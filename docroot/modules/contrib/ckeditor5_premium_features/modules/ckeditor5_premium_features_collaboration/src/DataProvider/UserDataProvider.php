<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\DataProvider;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\Entity\File;
use Drupal\image\ImageStyleStorageInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;

/**
 * Provides the user data for the editor features.
 */
class UserDataProvider implements UserCollaborationPermissionsInterface {

  /**
   * The image style storage.
   *
   * @var \Drupal\image\ImageStyleStorageInterface|\Drupal\Core\Entity\EntityStorageInterface
   */
  protected ImageStyleStorageInterface $imageStyleStorage;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface|\Drupal\Core\Entity\EntityStorageInterface
   */
  protected UserStorageInterface $userStorage;

  /**
   * Creates the provider instance.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    protected AccountProxyInterface $account,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->userStorage = $entity_type_manager->getStorage('user');
    $this->imageStyleStorage = $entity_type_manager->getStorage('image_style');
  }

  /**
   * {@inheritDoc}
   */
  public function getFromEntities(array $entities): array {
    $users = [];
    foreach ($entities as $entity) {
      $user = $entity->getAuthor();
      if ($user && $user->id() > 0) {
        $users[$user->id()] = $user;
      }
      elseif ($entity->hasField('uid') && $entity->get('uid')->target_id > 0) {
        $users[$entity->get('uid')->target_id] = NULL;
      }
      if ($entity->hasField('authors')) {
        $authors = $entity->get('authors')->value;
        if (!empty($authors)) {
          $decoded_authors = (array) unserialize($authors);
          foreach ($decoded_authors as $author_id) {
            $users[$author_id] = NULL;
          }
        }
      }
    }

    if (!array_key_exists($this->account->id(), $users)) {
      $users[$this->account->id()] = $this->userStorage->load($this->account->id());
    }

    $missing_users = array_filter($users, fn($item) => !$item);
    if (!empty($missing_users)) {
      $missing_users_data = $this->userStorage->loadMultiple(array_keys($missing_users));
      foreach ($missing_users_data as $id => $user) {
        $users[$id] = $user;
      }
    }
    return $this->getData($users);
  }

  /**
   * Gets the normalized users data.
   *
   * @param array|\Drupal\user\UserInterface[] $users
   *   The user entities.
   *
   * @return array
   *   The normalized data.
   */
  protected function getData(array $users): array {
    $data = [];

    foreach ($users as $userId => $user) {
      $data[$userId] = [
        'id' => $userId . '',
      ];

      if (!$user) {
        continue;
      }

      $data[$user->id()]['name'] = $user->getDisplayName();

      if ($user->access('view')) {
        $data[$user->id()]['avatar'] = $this->getUserPicture($user);
      }
    }

    return $data;
  }

  /**
   * Gets the user picture URL if defined.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return string|null
   *   URL or null.
   */
  protected function getUserPicture(UserInterface $user): ?string {
    if (!$user->hasField('user_picture')) {
      return NULL;
    }

    /** @var \Drupal\file\Entity\File $image */
    $image = $user->get('user_picture')->entity;
    $picture = NULL;
    if ($image instanceof File) {
      $style = $this->imageStyleStorage->load('thumbnail');
      $picture = $style?->buildUrl($image->getFileUri());
    }

    return $picture;
  }

}
