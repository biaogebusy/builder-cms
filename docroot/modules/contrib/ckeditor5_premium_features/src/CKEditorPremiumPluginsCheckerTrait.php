<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features;

/**
 * Trait for checking if in array of plugins are premium features.
 */
trait CKEditorPremiumPluginsCheckerTrait {

  /**
   * @param array $plugins
   * @return bool
   */
  private function hasPremiumFeaturesEnabled(array $config): bool {
    if (isset($config['plugins'])) {
      if (isset($config['plugins']['ckeditor5_premium_features_productivity_pack_base'])) {
        if (in_array(TRUE, $config['plugins']['ckeditor5_premium_features_productivity_pack_base'], TRUE)) {
          return TRUE;
        }
      }

      if (isset($config['plugins']['ckeditor5_premium_features_email_editing__email_editing'])) {
        if ($config["plugins"]["ckeditor5_premium_features_email_editing__email_editing"]["enable_configuration_helper"]) {
          return TRUE;
        }
      }
    }

    if (isset($config['toolbar']['items'])) {
      $enabledPremiumPlugins = array_intersect($this->getPremiumToolbarItems(), $config['toolbar']['items']);
      // Check the case when only insertTemplate is enabled. It can be enabled through Plugin Pack in that case it's
      // not considered as premium feature.
      if (!$enabledPremiumPlugins) {
        return FALSE;
      }
      if (count($enabledPremiumPlugins) === 1 && in_array('insertTemplate', $enabledPremiumPlugins, TRUE)) {
        $moduleHandler = \Drupal::service('module_handler');
        if ($moduleHandler->moduleExists('ckeditor5_plugin_pack_templates')) {
          return FALSE;
        }
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Returns array of premium features toolbar items.
   *
   * @return array
   */
  private function getPremiumToolbarItems(): array {
    return [
      'aiAssistant',
      'aiCommands',
      'aiQuickActions',
      'caseChange',
      'comment',
      'commentsArchive',
      'exportPdf',
      'exportWord',
      'formatPainter',
      'importWord',
      'insertFootnote',
      'insertMergeField',
      'insertTemplate',
      'lineHeight',
      'multiLevelList',
      'previewMergeFields',
      'revisionHistory',
      'sourceEditingEnhanced',
      'tableOfContents',
      'toggleAi',
      'trackChanges',
    ];
  }
}
