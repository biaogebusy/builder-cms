<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Plugin;

/**
 * Interface describing definition of configurable Premium feature plugin.
 */
interface ConfigurablePluginDefinitionInterface extends PremiumFeaturesPluginDefinitionInterface {

  /**
   * Gets the settings form class name.
   *
   * @return string
   *   The settings form name.
   */
  public function getSettingsForm(): string;

  /**
   * Gets the plugin config id.
   *
   * @return string
   *   The CKEditor plugin config id.
   */
  public function getConfigId(): string;

}
