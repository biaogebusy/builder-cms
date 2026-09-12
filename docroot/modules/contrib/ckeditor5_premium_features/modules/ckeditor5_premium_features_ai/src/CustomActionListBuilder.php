<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_ai;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for Custom Action entities.
 */
final class CustomActionListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('ID');
    $header['type'] = $this->t('Type');
    $header['model'] = $this->t('Model');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\ckeditor5_premium_features_ai\Entity\CustomAction $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->get('id');
    $row['type'] = $entity->get('type');
    $row['model'] = $entity->get('model');
    return $row + parent::buildRow($entity);
  }
}
