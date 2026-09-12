<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\ComposerInstaller;

use Drupal\package_manager\SandboxManagerBase;

/**
 * Defines a service to perform installs.
 */
final class Installer extends SandboxManagerBase {

  /**
   * {@inheritdoc}
   */
  protected string $type = 'ckeditor5_premium_features.installer';

  /**
   * Checks if the stage tempstore lock was created by this module.
   *
   * @return bool
   *   True if the stage tempstore lock was created by CKEditor 5 Premium Features.
   */
  public function isInternalLock(): bool {
    $lock_data = $this->tempStore->get(static::TEMPSTORE_LOCK_KEY);
    return !empty($lock_data[1]) && $lock_data[1] === self::class;
  }

}
