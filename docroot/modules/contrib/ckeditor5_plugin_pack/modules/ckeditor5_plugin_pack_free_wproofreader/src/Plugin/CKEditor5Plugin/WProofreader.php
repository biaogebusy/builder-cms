<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack_free_wproofreader\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\editor\EditorInterface;

/**
 * WProofreader ckeditor5 plugin class.
 */
final class WProofreader extends CKEditor5PluginDefault {

  /**
   * Trial service ID.
   *
   * @var string
   */
  private string $serviceId;

  /**
   * Default wscbundle url.
   *
   * @var string
   */
  private string $bundleUrl;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->serviceId = 'obL8buL18tyoubU';
    $this->bundleUrl = 'https://svc.webspellchecker.net/spellcheck31/wscbundle/wscbundle.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config['wproofreader']['autocomplete'] = FALSE;
    $static_plugin_config['wproofreader']['autocorrect'] = FALSE;
    $static_plugin_config['wproofreader']['disableDictionariesPreferences'] = TRUE;
    $static_plugin_config['wproofreader']['settingsSections'] = ['dictionaries', 'languages', 'options'];
    $static_plugin_config['wproofreader']['serviceId'] = $this->serviceId;
    $static_plugin_config['wproofreader']['srcUrl'] = $this->bundleUrl;
    return $static_plugin_config;
  }

}
