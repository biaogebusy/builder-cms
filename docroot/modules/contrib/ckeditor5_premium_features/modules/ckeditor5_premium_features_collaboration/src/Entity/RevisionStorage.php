<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Entity;

use Drupal\Core\Access\AccessException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Provides the storage class for the Revision entity.
 */
class RevisionStorage extends SqlContentEntityStorage implements
  CollaborationEntityStorageInterface,
  EditorDataStorageProviderInterface,
  StorageIdSpecificationAwareInterface {

  use CollaborationEntityStorageTrait;

  /**
   * Creates the storage instance.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $user
   *   THe current user object.
   * @param mixed ...$parent_arguments
   *   The parent paramters.
   */
  public function __construct(
    protected AccountProxyInterface $user,
    ...$parent_arguments
  ) {
    parent::__construct(...$parent_arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('current_user'),
      $entity_type,
      $container->get('database'),
      $container->get('entity_field.manager'),
      $container->get('cache.entity'),
      $container->get('language_manager'),
      $container->get('entity.memory_cache'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function serializeCollection(array $entities): string {
    $serialized = [];
    usort($entities, function (RevisionInterface $a, RevisionInterface $b) {
      // First, sort by timestamp.
      $compare_result = $a->getCreatedTime() <=> $b->getCreatedTime();
      if (!$compare_result) {
        // In case of equal timestamps, sort by version precedence.
        $compare_result = $a->getCurrentVersion() <=> $b->getCurrentVersion();
        if (!$compare_result) {
          $compare_result = $a->getPreviousVersion() <=> $b->getPreviousVersion();
        }
      }
      return $compare_result;
    });

    $initLoaded = FALSE;
    foreach ($entities as $entity) {
      if (str_starts_with($entity->getId(), 'initial_')) {
        if ($initLoaded) {
          continue;
        }
        $initLoaded = TRUE;
      }

      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $serialized[] = $entity->toArray();
    }

    return (string) json_encode($serialized);
  }

  /**
   * {@inheritdoc}
   */
  public function add(array $raw_data): CollaborationEntityInterface|NULL {
    $raw_data = Revision::normalize($raw_data);
    $data = new ParameterBag($raw_data);

    // Try getting the creator id. Since Drupal 11, Symfony's ParameterBag->getInt() will throw an exception if the
    // value is null instead returning 0.
    try {
      $uid = $data->getInt('creator');
    }
    catch (\Exception $e) {
      $uid = 0;
    }

    $object_data = [
      'id' => $data->get('id'),
      'uid' => $uid,
      'entity_id' => $data->get('entity_id'),
      'created' => $data->getInt('created'),
      'langcode' => $data->get('langcode'),
    ];
    $attributes = [
      'key' => $raw_data['key'],
    ] + $data->get('attributes') ?? [];

    $authors = $data->get('authors');
    if (!empty($authors) && $uid > 0 && !in_array($uid, $authors)) {
      $object_data['uid'] = reset($authors);
    }

    /** @var \Drupal\ckeditor5_premium_features_collaboration\Entity\Revision $revision */
    $revision = $this->create($object_data);
    $revision
      ->setEntityTypeTargetId($data->get('entity_type', ''))
      ->setName($data->get('name'))
      ->setAuthors($data->get('authors'))
      ->setDiffData($data->get('diff_data'))
      ->setPreviousVersion($data->get('previous_version'))
      ->setCurrentVersion($data->get('current_version'));

    // Set the 'draft' attribute if the creator is empty.
    if (!$data->get('creator')) {
      $attributes['draft'] = TRUE;
    }
    $revision->setAttributes($attributes);

    if (!$revision->access('create') && !$revision->access('update')) {
      throw new AccessException();
    }

    $revision->save();
    return $revision;
  }

  /**
   * {@inheritdoc}
   */
  public function update(CollaborationEntityInterface $entity, array $raw_data): CollaborationEntityInterface|NULL {
    if (!$entity->access('create') && !$entity->access('update')) {
      throw new AccessException();
    }
    if (!$entity instanceof Revision) {
      return NULL;
    }

    $raw_data = Revision::normalize($raw_data);
    $data = new ParameterBag($raw_data);

    $attributes = [
      'key' => $raw_data['key'],
    ] + $data->get('attributes') ?? [];

    $entity
      ->setName($data->get('name'))
      ->setAuthors($data->get('authors'))
      ->setDiffData($data->get('diff_data'))
      ->setAttributes($attributes)
      ->setPreviousVersion($data->get('previous_version'))
      ->setCurrentVersion($data->get('current_version'))
      ->save();

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function isCommonId(string $id): bool {
    return $id == 'initial';
  }

  /**
   * Returns array of revisions ids.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Related entity.
   * @param int $offset
   *   Maximum number of revisions.
   * @param int $created
   *   Timestamp date.
   *
   * @return array
   *   Array of ids.
   */
  public function getRevisionIds(EntityInterface $entity, string $keyId, int $offset = 0, int $created = 0): array {
    if (!$entity->uuid()) {
      return [];
    }
    $query = $this->getQuery()
      ->condition('entity_id', $entity->uuid())
      ->condition('uid', 0, '!=')
      ->condition('attributes', "%\"key\":\"" . $keyId . "\"%", 'LIKE')
      ->condition('name', NULL, 'IS NOT NULL')
      ->sort('created', 'DESC')
      ->accessCheck(FALSE);

    if ($offset && $created) {
      $createdQuery = clone $query;
      $createdQuery->condition('created', $created, '<');
      // Query returns revisions older than provided date.
      $createdQueryResult = $createdQuery->execute();

      $allRevisionQuery = $query;
      // Query returns all revisions.
      $allRevisionQueryResults = $allRevisionQuery->execute();
      // Get revisions which will be kept.
      $revisionsToKept = array_slice($allRevisionQueryResults, 0, $offset);

      // Return array without ids from revisionsToKept array.
      return array_diff($createdQueryResult, $revisionsToKept);
    }

    if ($created) {
      $query->condition('created', $created, '<');
      return $query->execute();
    }

    if ($offset) {
      $result = $query->execute();
      return array_slice($result, $offset);
    }

    return [];
  }

}
