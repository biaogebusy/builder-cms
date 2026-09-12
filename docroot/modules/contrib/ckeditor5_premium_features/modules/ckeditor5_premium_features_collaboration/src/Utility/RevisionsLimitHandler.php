<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Utility;

use Drupal\ckeditor5_premium_features\Utility\CommonCollaborationSettingsInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\RevisionInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\RevisionStorage;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Helper for removing revisions if any of limitation is enabled.
 */
class RevisionsLimitHandler {

  /**
   * The revision storage.
   *
   * @var \Drupal\ckeditor5_premium_features_collaboration\Entity\RevisionStorage
   */
  protected RevisionStorage $revisionStorage;

  /**
   * Constructor.
   *
   * @param \Drupal\ckeditor5_premium_features\Utility\CommonCollaborationSettingsInterface $collaborationSettings
   *   Collaboration settings helper.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(protected CommonCollaborationSettingsInterface $collaborationSettings,
                              protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->revisionStorage = $this->entityTypeManager->getStorage(RevisionInterface::ENTITY_TYPE_ID);
  }

  /**
   * Remove revision if any of limitation is enabled.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Related entity.
   * @param string $keyId
   *   Editor key id.
   */
  public function clearRevisions(EntityInterface $entity, string $keyId): void {
    $revisionsLimit = 0;
    $timestamp = 0;
    if ($this->collaborationSettings->isRevisionQuantityLimitation()) {
      $revisionsLimit = $this->collaborationSettings->getRevisionQuantityLimit();
    }

    if ($this->collaborationSettings->isRevisionTimeLimitation()) {
      $timeLimit = $this->collaborationSettings->getRevisionTimeLimit();
      if ($timeLimit) {
        $date = new \DateTime();
        $date->sub(new \DateInterval('P' . $timeLimit . 'D'));
        $timestamp = $date->getTimestamp();
      }
    }

    if ($revisionsLimit || $timestamp) {
      $revisionsIds = $this->revisionStorage->getRevisionIds($entity, $keyId, $revisionsLimit, $timestamp);
      if (!empty($revisionsIds)) {
        $revisions = $this->revisionStorage->loadMultiple($revisionsIds);
        $this->revisionStorage->delete($revisions);
      }
    }
  }

}
