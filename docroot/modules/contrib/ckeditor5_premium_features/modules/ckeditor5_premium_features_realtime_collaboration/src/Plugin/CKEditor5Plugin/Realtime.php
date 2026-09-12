<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_realtime_collaboration\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginElementsSubsetInterface;
use Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface;
use Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker;
use Drupal\ckeditor5_premium_features\Utility\PluginHelper;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Utility\CollaborationSettings;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 realtime plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class Realtime extends CKEditor5PluginDefault implements CKEditor5PluginElementsSubsetInterface, ContainerFactoryPluginInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * Creates the Realtime collaboration plugin instance.
   *
   * @param \Drupal\ckeditor5_premium_features\Utility\PluginHelper $pluginHelper
   *   Plugin helper service.
   * @param mixed ...$parent_arguments
   *   The parent plugin arguments.
   */
  public function __construct(
    protected PluginHelper $pluginHelper,
    protected LibraryVersionChecker $libraryVersionChecker,
    protected CollaborationSettings $collaborationSettings,
    protected SettingsConfigHandlerInterface $settingsConfigHandler,
  ...$parent_arguments
  ) {
    parent::__construct(...$parent_arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$parent_arguments): static {
    return new static(
      $container->get('ckeditor5_premium_features.plugin_helper'),
      $container->get('ckeditor5_premium_features.core_library_version_checker'),
      $container->get('ckeditor5_premium_features_realtime_collaboration.collaboration_settings'),
      $container->get('ckeditor5_premium_features.config_handler.settings'),
      ...$parent_arguments
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config['realtime']['readonly'] = FALSE;
    if (!ckeditor5_premium_features_check_jwt_installed()) {
      $static_plugin_config['removePlugins'] = [
        'PresenceList',
        'Comments',
        'TrackChanges',
        'TrackChangesPreview',
        'CommentsAdapter',
        'RealTimeCollaborativeComments',
        'RealTimeCollaborativeTrackChanges',
        'RevisionHistory',
        'RealTimeCollaborativeRevisionHistory',
        'RealtimeRevisionHistoryAdapter'
      ];
      $static_plugin_config['realtime']['readonly'] = TRUE;

      $message = $this->t("Realtime collaboration plugins are disabled because their required dependency <code>firebase/php-jwt</code> is not installed. The editor is initialized in read-only mode.");
      ckeditor5_premium_features_display_missing_dependency_warning($message);

      return $static_plugin_config;
    }
    $static_plugin_config['presenceList']['container'] = '';
    if (!$this->collaborationSettings->isPresenceListEnabled()) {
      $static_plugin_config['removePlugins'] = ['PresenceList'];
    }
    return $static_plugin_config;
  }

  /**
   * {@inheritdoc}
   */
  public function getElementsSubset(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    // A dummy configuration value because of the parent class
    // which force to have a form related methods
    // in case we want to use `getElementsSubset` method.
    return [
      'enabled' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $note = $this->t('In order to setup the Real Time Collaboration, use the <a href="@url">global realtime collaboration configuration instead</a>.', [
      '@url' => Url::fromRoute('ckeditor5_premium_features_realtime_collaboration.form.settings')->toString(),
    ]);
    $form['note'] = [
      ['#markup' => '<p>' . $this->t('The configuration for this plugin is not available.') . '</p>'],
      ['#markup' => '<p>' . $note . '</p>'],
    ];

    // A dummy form element in order to make the submission works.
    $form['enabled'] = [
      '#type' => 'hidden',
      '#default_value' => $this->configuration['enabled'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $toolbars = $this->pluginHelper->getFormToolbars($form_state);
    $labels = [];
    if (in_array('comment', $toolbars)) {
      $labels[] = 'Comments';
    }
    if (in_array('trackChanges', $toolbars)) {
      $labels[] = 'Track Changes';
    }
    if (in_array('revisionHistory', $toolbars)) {
      $labels[] = 'Revision History';
    }
    if (in_array('commentsArchive', $toolbars)) {
      $labels[] = 'Comments Archive';
    }
    if (!$this->settingsConfigHandler->getApiKey() && !empty($labels)) {
      $pluginsLabels[] = implode(' and ', array_splice($labels, -2));
      $form_state->setErrorByName('realtime',
        $this->t('API Key required for using %plugins. Check <a href="@config_page">Premium features configuration.</a>',
          [
            '@config_page' => '/admin/config/ckeditor5-premium-features/settings',
            '%plugins' => implode(', ', $pluginsLabels),
          ]));
    }

    if (!empty($labels)) {
      if (in_array('sourceEditing', $toolbars)) {
        $form_state->setErrorByName('editor', $this->t('Source editing can`t be enabled when Realtime Collaboration module is used'));
      }
      if (in_array('sourceEditingEnhanced', $toolbars)) {
        $form_state->setErrorByName('editor', $this->t('Source Editing Enhanced can`t be enabled when Realtime Collaboration module is used'));
      }
    }

    if (in_array('commentsArchive', $toolbars) && !$this->libraryVersionChecker->isLibraryVersionHigherOrEqual('37.1.0')) {
      $form_state->setErrorByName('editor', $this->t('The Comments Archive is available since CKEditor 5 v37.1.0. CKEditor 5 v38.0.1 was introduced in Drupal 10.1. Please update your Drupal core in order to use this feature.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

}
