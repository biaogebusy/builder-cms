<?php

namespace Drupal\comment_delete;

use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides comment delete permissions for each comment field.
 */
class CommentDeleteAccess implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * CommentDeleteAccess constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, EntityFieldManagerInterface $entityFieldManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager'),
    );
  }

  /**
   * Define comment delete user permissions.
   *
   * @return array
   *   Returns an associative array of user permissions.
   */
  public function permissions(): array {
    $permissions = [];

    $definitions = $this->entityTypeManager->getDefinitions();
    $bundleInfo = $this->entityTypeBundleInfo->getAllBundleInfo();

    foreach ($this->entityFieldManager->getFieldMapByFieldType('comment') as $entityTypeId => $fields) {
      foreach ($fields as $fieldName => $map) {
        foreach ($map['bundles'] as $bundleId) {
          $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entityTypeId, $bundleId);
          /** @var \Drupal\field\FieldConfigInterface $fieldDefinition */
          $fieldDefinition = $fieldDefinitions[$fieldName];

          $args = [
            '@type' => $definitions[$entityTypeId]->getLabel(),
            '%bundle' => $bundleInfo[$entityTypeId][$bundleId]['label'],
            '%field' => $fieldDefinition->getLabel(),
          ];

          $permissions += [
            "delete own $entityTypeId $bundleId $fieldName" => [
              'title' => $this->t('@type: Delete own %bundle %field', $args),
            ],
            "delete own $entityTypeId $bundleId $fieldName anytime" => [
              'title' => $this->t('@type: Delete own %bundle %field anytime', $args),
            ],
            "delete any $entityTypeId $bundleId $fieldName" => [
              'title' => $this->t('@type: Delete any %bundle %field', $args),
            ],
            "delete any $entityTypeId $bundleId $fieldName anytime" => [
              'title' => $this->t('@type: Delete any %bundle %field anytime', $args),
            ],
            "delete $entityTypeId $bundleId $fieldName replies" => [
              'title' => $this->t('@type: Delete %bundle %field replies', $args),
              'description' => $this->t('Delete any immediate reply to own comment.'),
            ],
            "delete $entityTypeId $bundleId $fieldName replies anytime" => [
              'title' => $this->t('@type: Delete %bundle %field replies anytime', $args),
              'description' => $this->t('Delete any immediate reply to own comment.'),
            ],
            "allow $entityTypeId $bundleId $fieldName hard delete" => [
              'title' => $this->t('@type: Allow %bundle %field hard delete', $args),
              'description' => $this->t('Use the hard delete operation.'),
            ],
            "allow $entityTypeId $bundleId $fieldName hard_partial delete" => [
              'title' => $this->t('@type: Allow %bundle %field partial hard delete', $args),
              'description' => $this->t('Use the partial hard delete operation.'),
            ],
            "allow $entityTypeId $bundleId $fieldName soft delete" => [
              'title' => $this->t('@type: Allow %bundle %field soft delete', $args),
              'description' => $this->t('Use the soft delete operation.'),
            ],
          ];
        }
      }
    }

    return $permissions;
  }

  /**
   * Delete operation access callback for comments.
   *
   * @param \Drupal\comment\CommentInterface $comment
   *   The comment entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The users account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Returns the access result.
   */
  public static function access(CommentInterface $comment, AccountInterface $account): AccessResultInterface {
    /** @var \Drupal\comment_delete\CommentDeleteManagerInterface $commentDeleteManager */
    $commentDeleteManager = \Drupal::service('comment_delete.manager');

    if (!$config = $commentDeleteManager->getConfig($comment)) {
      return AccessResult::neutral();
    }
    $entityTypeId = $config['commented_entity']->getEntityTypeId();
    $bundleId = $config['commented_entity']->bundle();
    $fieldName = $comment->getFieldName();
    $isOwner = $comment->getOwnerId() === $account->id();
    $hasParent = $comment->hasParentComment();
    $parentComment = $comment->getParentComment();
    $isParentOwner = $hasParent && $parentComment->getOwnerId() === $account->id();

    // Only check timed out when enabled.
    $timedOut = $config['time_limit'] && \Drupal::time()->getRequestTime() - $comment->getCreatedTime() > $config['timer'];
    $parentTimedOut = $hasParent && $config['time_limit'] && \Drupal::time()->getRequestTime() - $parentComment->getCreatedTime() > $config['timer'];

    return AccessResult::allowedIf(
      // Delete any comment.
      ($account->hasPermission('delete any comment') && !$timedOut) ||
      ($account->hasPermission('delete any comment anytime')) ||
      ($account->hasPermission("delete any $entityTypeId $bundleId $fieldName") && !$timedOut) ||
      ($account->hasPermission("delete any $entityTypeId $bundleId $fieldName anytime")) ||
      // Delete own comment.
      ($account->hasPermission('delete own comment') && $isOwner && !$timedOut) ||
      ($account->hasPermission('delete own comment anytime') && $isOwner) ||
      ($account->hasPermission("delete own $entityTypeId $bundleId $fieldName") && $isOwner && !$timedOut) ||
      ($account->hasPermission("delete own $entityTypeId $bundleId $fieldName anytime") && $isOwner) ||
      // Delete immediate replies to own comment.
      ($hasParent && $account->hasPermission("delete $entityTypeId $bundleId $fieldName replies") && $isParentOwner && !$parentTimedOut) ||
      ($hasParent && $account->hasPermission("delete $entityTypeId $bundleId $fieldName replies anytime") && $isParentOwner)
    );
  }

}
