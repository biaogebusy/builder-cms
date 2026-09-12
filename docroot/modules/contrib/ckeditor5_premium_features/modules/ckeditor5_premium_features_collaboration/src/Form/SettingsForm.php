<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Form;

use Drupal\ckeditor5_premium_features\Form\SharedBuildConfigFormBase;
use Drupal\Core\Config\Config;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the configuration form of the "Collaboration" feature.
 */
class SettingsForm extends SharedBuildConfigFormBase {

  const COLLABORATION_SETTINGS_ID = 'ckeditor5_premium_features_collaboration.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ckeditor5_premium_features_collaboration_settings';
  }

  /**
   * {@inheritdoc}
   */
  public static function getSettingsRouteName(): string {
    return 'ckeditor5_premium_features_collaboration.form.settings';
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

    $form['revision_history'] = [
      '#type' => 'fieldset',
      '#title' => t('Revision History'),
    ];

    $form['revision_history']['add_revision_on_submit'] = [
      '#type' => 'checkbox',
      '#title' => t('Add revisions on form submit'),
      '#default_value' => $config->get('add_revision_on_submit') ?? TRUE,
      '#description' => t('If you leave this unchecked, new revisions will only be saved on demand.'),
    ];

    $form['revision_history']['limits'] = [
      '#type' => 'details',
      '#title' => t('Revisions Limits'),
      '#description' => t(
        '<strong>Warning</strong><br />
        This setting will only remove CKEditor5 revisions. Drupal revisions will not be affected. <br/>
        If both checkboxes are checked, revisions will be removed after the provided number of days <strong>AND</strong> when the maximum quantity is reached.<br/> <br/>
        For example, the quantity limitation is set to 30 revisions and the time limitation is set to 14 days.
        If a new node is created and during 14 days 50 new revisions were created, the 20 oldest revisions will be removed on save after 14 days.
        If during these 14 days, only 20 revisions were created, none will be removed after 14 days.
        '),
    ];

    $form['revision_history']['limits']['revisions_quantity_limitation'] = [
      '#type' => 'checkbox',
      '#title' => t('Quantity limitation'),
      '#default_value' => $config->get('revisions_quantity_limitation') ?? FALSE,
      '#description' => t('Remove old revisions after reaching provided quantity.'),
    ];

    $form['revision_history']['limits']['revisions_quantity_limit'] = [
      '#type' => 'number',
      '#title' => t('Maximum revisions per node'),
      '#min' => 0,
      '#default_value' => $config->get('revisions_quantity_limit') ?? 0,
      '#description' => t('Old revisions will be removed after reached provided quantity.'),
      '#states' => [
        'visible' => [
          ':input[name="revisions_quantity_limitation"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['revision_history']['limits']['revisions_time_limitation'] = [
      '#type' => 'checkbox',
      '#title' => t('Time limitation'),
      '#default_value' => $config->get('revisions_time_limitation') ?? FALSE,
      '#description' => t('Old revision will be removed after reaching the provided number.'),
    ];

    $form['revision_history']['limits']['revisions_time_limit'] = [
      '#type' => 'number',
      '#title' => t('Maximum lifetime of a revision.'),
      '#min' => 0,
      '#default_value' => $config->get('revisions_time_limit') ?? 0,
      '#description' => t('Revision older than the provided number of days will be removed.'),
      '#states' => [
        'visible' => [
          ':input[name="revisions_time_limitation"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return $form;
  }

}
