<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Entity;

use Drupal\ckeditor5_premium_features\Event\CollaborationEventBase;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Provides the storage class for the Suggestion entity.
 */
class SuggestionStorage extends SqlContentEntityStorage implements
  CollaborationEntityStorageInterface,
  EditorDataStorageProviderInterface,
  CollaborationEntityEventDispatcherInterface {

  use CollaborationEntityStorageTrait;

  /**
   * Creates the storage instance.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $user
   *   THe current user object.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   * @param mixed ...$parent_arguments
   *   The parent parameters.
   */
  public function __construct(
    protected AccountProxyInterface $user,
    protected EventDispatcherInterface $event_dispatcher,
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
      $container->get('event_dispatcher'),
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
    $suggestions = $entities;

    $serialized = [];
    foreach ($suggestions as $suggestion) {
      $serializedSuggestion = $suggestion->toArray();
      // Do not pass head attribute as it may break suggestion annotations for some specific changes (for example list type change).
      unset($serializedSuggestion['attributes']['head']);
      $serialized[] = $serializedSuggestion;
    }

    return (string) json_encode($serialized);
  }

  /**
   * {@inheritdoc}
   */
  public function add(array $raw_data): CollaborationEntityInterface|NULL {
    $raw_data = Suggestion::normalize($raw_data);
    $data = new ParameterBag($raw_data);

    $raw_attributes = $data->get('attributes');
    if (!empty($raw_attributes['status'])) {
      // Skip adding the entity, because it was already accepted/rejected.
      return NULL;
    }

    $object_data = [
      'id' => $data->getAlnum('id'),
      'entity_id' => $data->get('entity_id'),
      'langcode' => $data->get('langcode'),
    ];

    $original_suggestion = $this->loadOriginalSuggestionFromData($data);
    $has_original = $original_suggestion instanceof SuggestionInterface;

    $callback = $has_original ? 'getSuggestionEntityData' : 'getSuggestionData';
    $source = $has_original ? $original_suggestion : $data;

    [
      $object_data,
      $suggestion_data,
      $attributes,
      $type,
    ] = call_user_func([__CLASS__, $callback], $object_data, $source);

    $attributes['key'] = $data->get('key');

    /** @var \Drupal\ckeditor5_premium_features_collaboration\Entity\Suggestion $suggestion */
    $suggestion = $this->create($object_data);
    $suggestion
      ->setEntityTypeTargetId($data->get('entity_type', ''))
      ->setType($type)
      ->setData($suggestion_data)
      ->setAttributes($attributes);

    if (!$suggestion->access('create') && !$suggestion->access('update')) {
      throw new AccessException();
    }

    $suggestion->save();

    return $suggestion;
  }

  /**
   * {@inheritdoc}
   */
  public function update(CollaborationEntityInterface $entity, array $raw_data): CollaborationEntityInterface|NULL {
    if (!$entity->access('create') && !$entity->access('update')) {
      throw new AccessException();
    }
    if (!$entity instanceof SuggestionInterface) {
      return NULL;
    }

    $raw_data = Suggestion::normalize($raw_data);
    $data = new ParameterBag($raw_data);
    $has_comments = $data->getBoolean('has_comments');
    $suggestion_data = $data->get('data') ?? [];
    $suggestion_attributes = $data->get('attributes') ?? [];
    $suggestion_attributes['key'] = $data->get('key');
    $head_id = $suggestion_attributes['head'] ?? NULL;

    $entity
      ->setCommentState($has_comments)
      ->setData($suggestion_data)
      ->setAttributes($suggestion_attributes)
      ->setChainId($head_id)
      ->save();

    return $entity;
  }

  /**
   * Returns the list of IDs present in the source data array.
   *
   * @param array $source_data
   *   Collaboration source data.
   */
  public function getSuggestionEntityIds(array $source_data): array {
    return array_map(function ($value) {
      return $value['id'];
    }, $source_data);
  }

  /**
   * {@inheritdoc}
   */
  public function dispatchNewEntity(CollaborationEntityInterface $entity): void {
    if (!$entity instanceof SuggestionInterface) {
      return;
    }
    if ($entity->isInChain() && !$entity->isHeadOfChain()) {
      return;
    }
    $event = new CollaborationEventBase($entity, $this->user, CollaborationEventBase::SUGGESTION_ADDED);

    if ($newContent = $this->getDocumentNewValue()) {
      $event->setNewContent($newContent);
    }

    $this->event_dispatcher->dispatch(
      $event,
      CollaborationEventBase::SUGGESTION_ADDED
    );
  }

  /**
   * {@inheritdoc}
   */
  public function dispatchUpdatedEntity(CollaborationEntityInterface $oldEntity, CollaborationEntityInterface $newEntity): void {
    if (!$oldEntity instanceof SuggestionInterface || !$newEntity instanceof SuggestionInterface) {
      return;
    }

    if ($oldEntity->getStatus() == $newEntity->getStatus() ||
      $newEntity->isInChain() && !$newEntity->isHeadOfChain()) {
      return;
    }

    switch ($newEntity->getStatus()) {
      case SuggestionInterface::SUGGESTION_ACCEPTED:
        $event_type = CollaborationEventBase::SUGGESTION_ACCEPT;
        break;

      case SuggestionInterface::SUGGESTION_REJECTED:
        $event_type = CollaborationEventBase::SUGGESTION_DISCARD;
        break;
    }

    if (!isset($event_type)) {
      return;
    }

    $event = new CollaborationEventBase($newEntity, $this->user, $event_type);
    if ($originalContent = $this->getDocumentOriginalValue()) {
      $event->setOriginalContent($originalContent);
    }

    if ($newContent = $this->getDocumentNewValue()) {
      $event->setNewContent($newContent);
    }

    $this->event_dispatcher->dispatch(
      $event,
      $event_type
    );
  }

  /**
   * Loads the original suggestion if present in the data.
   *
   * @param \Symfony\Component\HttpFoundation\ParameterBag $data
   *   The data key/value.
   *
   * @return \Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionInterface|null
   *   The suggestion entity or null.
   */
  protected function loadOriginalSuggestionFromData(ParameterBag $data): ?SuggestionInterface {
    $original_suggestion_id = $data->getAlnum('original');

    return $original_suggestion_id ? $this->load($original_suggestion_id) : NULL;
  }

  /**
   * Gets the suggestion data based on what was passed in the data.
   *
   * @param array $current_data
   *   The common data already added.
   * @param \Symfony\Component\HttpFoundation\ParameterBag $data
   *   The data key/value.
   *
   * @return array
   *   The data required to be stored on the entity.
   */
  protected function getSuggestionData(array $current_data, ParameterBag $data): array {
    $object_data = [
      'uid' => $data->getInt('authorId'),
    ] + $current_data;

    $attributes = $data->get('attributes');
    if (!empty($attributes['head'])) {
      $object_data['chain_id'] = $attributes['head'];
    }

    return [
      $object_data,
      $data->get('data') ?? [],
      $attributes ?? [],
      $data->get('type'),
    ];
  }

  /**
   * Gets the suggestion data based on the suggestion entity..
   *
   * @param array $current_data
   *   The common data already added.
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionInterface $suggestion
   *   The suggestion entity.
   *
   * @return array
   *   The data required to be stored on the entity.
   */
  protected function getSuggestionEntityData(array $current_data, SuggestionInterface $suggestion): array {
    $object_data = [
      'uid' => $suggestion->getAuthorId(),
      'type' => $suggestion->getType(),
      'created' => $suggestion->getCreatedTime(),
    ] + $current_data;

    return [
      $object_data,
      $suggestion->getData(),
      $suggestion->getAttributes(),
      $suggestion->getType(),
    ];
  }

}
