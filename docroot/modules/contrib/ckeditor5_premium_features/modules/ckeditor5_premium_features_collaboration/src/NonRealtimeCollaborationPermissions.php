<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration;

use Drupal\ckeditor5_premium_features\CollaborationPermissions;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides dynamic permissions for text formats which uses non-realtime
 * collaboration plugins..
 */
class NonRealtimeCollaborationPermissions extends CollaborationPermissions {

  use StringTranslationTrait;

  public const DOCUMENT_SUGGESTIONS = 'document_suggestions';

  public const SPECIFIC_PERMISSIONS = [
    self::DOCUMENT_SUGGESTIONS,
  ];



  /**
   * Constructs a new CollaborationPermissions instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactory $config_factory) {
    $privatePermissions = [
      self::DOCUMENT_SUGGESTIONS,
    ];

    $this->permissions = array_merge(parent::COMMON_PERMISSIONS, $privatePermissions);

    parent::__construct($entity_type_manager, $config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public function permissions(): array {

    $premiumPlugins = [
      'ckeditor5_premium_features_collaboration__comments',
      'ckeditor5_premium_features_collaboration__track_changes',
    ];

    return parent::getPermissions($premiumPlugins, $this->permissions);
  }

  /**
   * {@inheritdoc}
   */
  public static function getModulePermissions(): array {
    return array_merge(static::COMMON_PERMISSIONS, self::SPECIFIC_PERMISSIONS);
  }

  /**
   * {@inheritdoc}
   */
  protected function getPermissionLabel(string $permission): string|TranslatableMarkup {
    $label = match ($permission) {
      self::DOCUMENT_SUGGESTIONS => $this->t('Add suggestions'),
      parent::DOCUMENT_WRITE => $this->t('Evaluate suggestions and edit content'),
      default => '',
    };
    return $label ? $label : parent::getPermissionLabel($permission);
  }

  /**
   * {@inheritdoc}
   */
  protected function getPermissionDescription(string $permission): string|TranslatableMarkup {
    $description = match ($permission) {
      self::DOCUMENT_SUGGESTIONS => $this->t('Allows to add and edit suggestions only.'),
      default => '',
    };
    return $description ? $description : parent::getPermissionDescription($permission);
  }



}
