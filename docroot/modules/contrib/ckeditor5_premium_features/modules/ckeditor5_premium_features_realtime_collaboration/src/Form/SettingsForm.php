<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_realtime_collaboration\Form;

use Drupal\ckeditor5_premium_features\Form\SharedBuildConfigFormBase;
use Drupal\ckeditor5_premium_features\Utility\PermissionHelper;
use Drupal\ckeditor5_premium_features_realtime_collaboration\BundleUploadHelper;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the configuration form of the "Realtime collaboration" feature.
 */
class SettingsForm extends SharedBuildConfigFormBase {

  const COLLABORATION_SETTINGS_ID = 'ckeditor5_premium_features_realtime_collaboration.settings';

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param TypedConfigManagerInterface $typedConfigManager
   *  The typed configuration manager.
   * @param EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param BundleUploadHelper $bundleUploadHelper
   *   The bundle upload helper.
   * @param PermissionHelper $permissionHelper
   *   The premium features permissions helper.
   */
  public function __construct(
      ConfigFactoryInterface $config_factory,
      TypedConfigManagerInterface $typedConfigManager,
      protected EntityTypeManagerInterface $entityTypeManager,
      protected BundleUploadHelper $bundleUploadHelper,
      protected PermissionHelper $permissionHelper
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
        $container->get('config.factory'),
        $container->get('config.typed'),
        $container->get('entity_type.manager'),
        $container->get('ckeditor5_premium_features_realtime_collaboration.bundle_upload_helper'),
        $container->get('ckeditor5_premium_features.permission_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  final public function getFormId(): string {
    return 'ckeditor5_premium_features_realtime_collaboration_settings';
  }

  /**
   * {@inheritdoc}
   */
  public static function getSettingsRouteName(): string {
    return 'ckeditor5_premium_features_realtime_collaboration.form.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigId(): string {
    return self::COLLABORATION_SETTINGS_ID;
  }

  /**
   * {@inheritdoc}
   */
  public static function form(array $form, FormStateInterface $form_state, Config $config): array {
    $form['sidebar'] = [
      '#type' => 'select',
      '#title' => t('Annotation sidebar'),
      '#options' => [
        'auto' => t('Automatic'),
        'inline' => t('Use inline balloons'),
        'narrowSidebar' => t('Use narrow sidebar'),
        'wideSidebar' => t('Use wide sidebar'),
      ],
      '#default_value' => $config->get('sidebar') ?? 'auto',
    ];

    $form['prevent_scroll_out_of_view'] = [
        '#type' => 'checkbox',
        '#title' => t('Prevent scrolling sidebar items out of view.'),
        '#default_value' => $config->get('prevent_scroll_out_of_view') ?? FALSE,
        '#description' => t('If selected, the top annotation in the sidebar will never be scrolled above the top edge of the sidebar (which would make it hidden).'),
    ];

    $form['presence_list'] = [
      '#type' => 'checkbox',
      '#title' => t('Presence list'),
      '#default_value' => $config->get('presence_list') ?? TRUE,
    ];

    $form['presence_list_collapse_at'] = [
      '#type' => 'number',
      '#min' => 1,
      '#title' => t('Presence list collapse items'),
      '#default_value' => $config->get('presence_list_collapse_at') ?? 8,
    ];

    $form['realtime_permissions'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable Realtime Collaboration permissions'),
      '#default_value' => $config->get('realtime_permissions') ?? FALSE,
    ];

    $form['allow_text_format_change'] = [
      '#type' => 'checkbox',
      '#title' => t('Allow text format select'),
      '#description' => t('Change of text format will cause editing session reset and all other users in session will be disconnected. Changing text format is not supported at all when permissions system is enabled. Text format change is always available on node add form.'),
      '#default_value' => $config->get('allow_text_format_change') ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $this->handlePermissionsChange($form, $form_state);
  }

  /**
   * If permission system is enabled - upload editor bundles having collaboration
   * plugins active. If deactivated - remove all granted permissions related to
   * realtime permissions.
   *
   * @param array $form
   *    An associative array containing the structure of the form.
   * @param FormStateInterface $form_state
   *    The current state of the form.
   */
  public function handlePermissionsChange(array $form, FormStateInterface $form_state): void {
    $original = $form["realtime_permissions"]["#default_value"];
    $current = $form_state->getValue('realtime_permissions');
    if ($original == $current) {
      return;
    }

    $editors = $this->entityTypeManager->getStorage('editor')->loadMultiple();

    $formats = [];
    foreach ($editors as $editor) {
      if ($current) {
        if (!$editor->status()) {
          continue;
        }
        $toolbarItems = $editor->getSettings()['toolbar']['items'] ?? [];

        if (array_intersect($toolbarItems, $this->bundleUploadHelper::COLLABORATION_TOOLBAR_ITEMS)) {
          $this->bundleUploadHelper->uploadBundle($editor);
        }
      }
      else {
        $formats[] = $editor->getFilterFormat();
      }
    }
    if ($formats) {
      $this->permissionHelper->revokeCollaborationPermissions($formats);
    }
  }

}
