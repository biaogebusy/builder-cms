<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_realtime_collaboration;

use Drupal\ckeditor5_premium_features\CollaborationPermissions;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides dynamic permissions for text formats which uses realtime
 * collaboration plugins.
 */
class RealtimeCollaborationPermissions extends CollaborationPermissions {

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactory $config_factory) {
    $this->permissions = parent::COMMON_PERMISSIONS;

    parent::__construct($entity_type_manager, $config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public function permissions(): array {

    $premiumPlugins = [
      'ckeditor5_premium_features_realtime_collaboration__comment',
      'ckeditor5_premium_features_realtime_collaboration__track_changes',
    ];

    if ($this->configFactory->get('ckeditor5_premium_features_realtime_collaboration.settings')->get('realtime_permissions')) {
      return $this->getPermissions($premiumPlugins, $this->permissions);
    }

    return [];
  }

}
