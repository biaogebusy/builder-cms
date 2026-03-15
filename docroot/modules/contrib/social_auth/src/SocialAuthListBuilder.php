<?php

namespace Drupal\social_auth;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\user\UserInterface;

/**
 * Defines a class to build a listing of social_auth entities.
 */
class SocialAuthListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'id' => [
        'data' => $this->t('ID'),
      ],
      'user' => [
        'data' => $this->t('User'),
      ],
      'plugin_id' => [
        'data' => $this->t('Provider'),
      ],
      'provider_user_id' => [
        'data' => $this->t('Provider User ID'),
      ],
    ];

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\social_auth\Entity\SocialAuth $entity */
    $row['id']['data'] = [
      '#markup' => $entity->id(),
    ];

    $users = $entity->get('user_id')->referencedEntities();
    /** @var \Drupal\user\UserInterface $user */
    $user = reset($users);
    $row['user']['data'] = [
      '#type' => 'link',
      '#title' => $user->getDisplayName(),
      '#url' => $user->toUrl(),
    ];

    foreach (['plugin_id', 'provider_user_id'] as $field) {
      $value = $entity->hasField($field) && !$entity->get($field)->isEmpty()
        ? $entity->get($field)->first()->getValue()['value']
        : $this->t('Broken/Missing');

      $row[$field]['data'] = [
        '#markup' => $value,
      ];
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * Builds a listing of entities for the given entity type.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user for which the social_auth profiles list is made.
   *
   * @return array
   *   A render array as expected by
   *   \Drupal\Core\Render\RendererInterface::render().
   */
  public function renderForUser(UserInterface $user) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $entity_query */
    $entity_query = $this->storage->getQuery();
    $entity_query
      ->condition('user_id', $user->id())
      ->accessCheck(TRUE);
    $result = $entity_query->execute();
    $entities = $result ? $this->storage
      ->loadMultiple($result) : [];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $this
        ->buildHeader(),
      '#title' => $this
        ->getTitle(),
      '#rows' => [],
      '#empty' => $this
        ->t('There are no @label yet.', [
          '@label' => $this->entityType
            ->getPluralLabel(),
        ]),
      '#cache' => [
        'contexts' => $this->entityType
          ->getListCacheContexts(),
        'tags' => $this->entityType
          ->getListCacheTags(),
      ],
    ];
    foreach ($entities as $entity) {
      if ($row = $this
        ->buildRow($entity)) {
        $build['table']['#rows'][$entity
          ->id()] = $row;
      }
    }

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $build['pager'] = [
        '#type' => 'pager',
      ];
    }
    return $build;
  }

}
