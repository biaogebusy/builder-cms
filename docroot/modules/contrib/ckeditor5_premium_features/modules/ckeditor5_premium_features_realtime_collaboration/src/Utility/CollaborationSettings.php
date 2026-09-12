<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features_realtime_collaboration\Utility;

use Drupal\ckeditor5_premium_features\Utility\CommonCollaborationSettingsInterface;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Form\SettingsForm;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Class for accessing collaboration config values.
 */
class CollaborationSettings implements CommonCollaborationSettingsInterface {

  /**
   * Settings object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $collaborationSettings;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->collaborationSettings = $configFactory->get(SettingsForm::COLLABORATION_SETTINGS_ID);
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotationSidebarType(): string {
    return $this->collaborationSettings->get('sidebar') ?? 'auto';
  }

  /**
   * {@inheritdoc}
   */
  public function isScrollingAnnotationsOutOfViewForbidden(): bool {
    return (bool) ($this->collaborationSettings->get('prevent_scroll_out_of_view') ?? FALSE);
  }

  /**
   * Returns mentions revision history on submit config.
   */
  public function isPresenceListEnabled(): bool {
    return (bool) ($this->collaborationSettings->get('presence_list') ?? TRUE);
  }

  /**
   * Returns mentions revision history on submit config.
   */
  public function getPresenceListCollapseAt(): int {
    return (int) ($this->collaborationSettings->get('presence_list_collapse_at') ?? 8);
  }

  /**
   * Check whether the realtime collaboration permissions are enabled.
   */
  public function isPermissionsEnabled(): bool {
    return (bool) ($this->collaborationSettings->get('realtime_permissions') ?? FALSE);
  }

}
