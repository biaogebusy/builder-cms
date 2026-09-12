<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Plugin;

/**
 * Interface describing definition of export Premium feature plugin.
 */
interface ExportPluginDefinitionInterface extends ConfigurablePluginDefinitionInterface {

  /**
   * Gets the export file extension.
   *
   * @return string
   *   The file extension.
   */
  public function getExportFileExtension(): string;

}
