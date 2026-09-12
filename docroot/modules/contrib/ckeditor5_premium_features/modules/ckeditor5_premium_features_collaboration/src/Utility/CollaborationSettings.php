<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Utility;

use Drupal\ckeditor5_premium_features\Utility\CommonCollaborationSettingsInterface;
use Drupal\ckeditor5_premium_features\Utility\RevisionLimitationSettingsInterface;
use Drupal\ckeditor5_premium_features_collaboration\Form\SettingsForm;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Class for accessing collaboration config values.
 */
class CollaborationSettings implements CommonCollaborationSettingsInterface, RevisionLimitationSettingsInterface {

  /**
   * Config object.
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
   * Returns revision history on submit config.
   */
  public function isRevisionHistoryOnSubmit(): bool {
    return (bool) ($this->collaborationSettings->get('add_revision_on_submit') ?? TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function isRevisionQuantityLimitation(): bool {
    return (bool) ($this->collaborationSettings->get('revisions_quantity_limitation') ?? TRUE);

  }

  /**
   * {@inheritdoc}
   */
  public function isRevisionTimeLimitation(): bool {
    return (bool) ($this->collaborationSettings->get('revisions_time_limitation') ?? TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getRevisionQuantityLimit(): int {
    return (int) ($this->collaborationSettings->get('revisions_quantity_limit') ?? 0);
  }

  /**
   * {@inheritdoc}
   */
  public function getRevisionTimeLimit(): int {
    return (int) ($this->collaborationSettings->get('revisions_time_limit') ?? 0);
  }

  /**
   * {@inheritdoc}
   */
  public function isRevisionsLimitationEnabled(): bool {
    if ($this->isRevisionQuantityLimitation() ||
      $this->isRevisionTimeLimitation()) {
      return TRUE;
    }
    return FALSE;
  }

}
