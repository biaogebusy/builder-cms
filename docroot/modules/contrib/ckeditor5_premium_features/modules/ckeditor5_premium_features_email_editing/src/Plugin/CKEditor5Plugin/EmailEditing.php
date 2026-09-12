<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_email_editing\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 Premium Features Email Editing Plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class EmailEditing extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface, CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * Creates the plugin instance.
   *
   * @param \Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker $libraryVersionChecker
   *   Helper for checking CKEditor 5 version.
   * @param \Drupal\ckeditor5_premium_features\Utility\PluginHelper $pluginHelper
   *   Helper for getting the editor toolbar plugins.
   * @param mixed ...$parent_arguments
   *   The parent plugin arguments.
   */
  public function __construct(
    protected LibraryVersionChecker $libraryVersionChecker, ...$parent_arguments) {
    parent::__construct(...$parent_arguments);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('ckeditor5_premium_features.core_library_version_checker'),
      $configuration,
      $plugin_id,
      $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'enable_configuration_helper' => FALSE,
      'suppress_all' => FALSE,
      'suppress_html_element' => FALSE,
      'enable_export_inline_styles' => FALSE,
      'form_element' => '',
      'stylesheets' => '',
      'inline_css' => '',
      'strip_classes' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['configuration_helper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Email Configuration Helper'),
    ];
    $form['configuration_helper']['description'] = [
      '#markup' => $this->t('This plugin helps to configure text format used for creating email templates by providing information about configuration that is not well supported by email clients.'),

    ];
    $form['configuration_helper']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Email Configuration Helper'),
      '#default_value' => $this->configuration['enable_configuration_helper'] ?? FALSE,
      '#description' => $this->t('Enabling this setting will allow to use the Email Configuration Helper.'),
    ];
    $form['configuration_helper']['suppress_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Suppress all email editing logs'),
      '#default_value' => $this->configuration['suppress_all'] ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name="editor[settings][plugins][ckeditor5_premium_features_email_editing__email_editing][configuration_helper][enable]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['configuration_helper']['suppress_html'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Suppress unsupported HTML element logs'),
      '#default_value' => $this->configuration['suppress_html_element'] ?? FALSE,
      '#description' => $this->t('General HTML support is enabled by default. It allows to use some HTML tags that are not supported byt email clients. This setting allows to suppress logs about unsupported HTML elements.'),
      '#states' => [
        'visible' => [
          ':input[name="editor[settings][plugins][ckeditor5_premium_features_email_editing__email_editing][configuration_helper][enable]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['export_inline_styles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Export inline styles'),
    ];

    $form['export_inline_styles']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Export inline styles'),
      '#default_value' => $this->configuration['enable_export_inline_styles'] ?? FALSE,
      '#description' => $this->t('Enabling this setting will allow to use the Export Inline Styles.'),
    ];

    $form['export_inline_styles']['form_element'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Form field'),
      '#default_value' => $this->configuration['form_element'] ?? FALSE,
      '#description' => $this->t('The target form element id. The content with inline styles will be exported to this element on form submit'),
      '#states' => [
        'visible' => [
          ':input[name="editor[settings][plugins][ckeditor5_premium_features_email_editing__email_editing][export_inline_styles][enable]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="editor[settings][plugins][ckeditor5_premium_features_email_editing__email_editing][export_inline_styles][enable]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['export_inline_styles']['stylesheets'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Stylesheets'),
      '#default_value' => $this->configuration['stylesheets'] ?? FALSE,
      '#description' => $this->t('Paths to css files to be used for exporting inline styles. Each path should be on a new line and start at Drupal root folder.<br />The order matters as later files can override styles from earlier ones.'),
      '#states' => [
        'visible' => [
          ':input[name="editor[settings][plugins][ckeditor5_premium_features_email_editing__email_editing][export_inline_styles][enable]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['export_inline_styles']['inline_css'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Inline CSS'),
      '#default_value' => $this->configuration['inline_css'] ?? FALSE,
      '#description' => $this->t(''),
      '#states' => [
        'visible' => [
          ':input[name="editor[settings][plugins][ckeditor5_premium_features_email_editing__email_editing][export_inline_styles][enable]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['export_inline_styles']['strip_classes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Strip CSS classes'),
      '#default_value' => $this->configuration['strip_classes'] ?? FALSE,
      '#description' => $this->t('When enabled the CSS classes will be removed from elements after inline styles are applied.'),
      '#states' => [
        'visible' => [
          ':input[name="editor[settings][plugins][ckeditor5_premium_features_email_editing__email_editing][export_inline_styles][enable]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['enable_configuration_helper'] = $form_state->getValue(['configuration_helper', 'enable']);
    $this->configuration['suppress_all'] = $form_state->getValue(['configuration_helper', 'suppress_all']);
    $this->configuration['suppress_html_element'] = $form_state->getValue(['configuration_helper', 'suppress_html']);

    $this->configuration['enable_export_inline_styles'] = $form_state->getValue(['export_inline_styles', 'enable']);
    $this->configuration['form_element'] = $form_state->getValue(['export_inline_styles', 'form_element']);
    $this->configuration['stylesheets'] = $form_state->getValue(['export_inline_styles', 'stylesheets']);
    $this->configuration['inline_css'] = $form_state->getValue(['export_inline_styles', 'inline_css']);
    $this->configuration['strip_classes'] = $form_state->getValue(['export_inline_styles', 'strip_classes']);
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config['removePlugins'] = [];

    if ($this->configuration['enable_configuration_helper']) {
      if ($this->configuration['suppress_all']) {
        $static_plugin_config['email']['logs']['suppressAll'] = TRUE;
      }
      $static_plugin_config['email']['logs']['suppress'] = ['email-configuration-missing-merge-fields-plugin'];
      if ($this->configuration['suppress_html_element']) {
        $static_plugin_config['email']['logs']['suppress'][] = 'email-unsupported-html-element';
      }
    }
    else {
      $static_plugin_config['removePlugins'][] = 'EmailConfigurationHelper';
    }

    if ($this->configuration['enable_export_inline_styles']) {
      $static_plugin_config['exportInlineStyles'] = [
        'formElement' => $this->configuration['form_element'],
        'stripCssClasses' => $this->configuration['strip_classes'],
      ];
      if (!empty($this->configuration['inline_css'])) {
        $static_plugin_config['exportInlineStyles']['inlineCss'] = $this->configuration['inline_css'];
      }
      if (!empty($this->configuration['stylesheets'])) {
        $stylesheets = str_replace(array("\r\n", "\r"), "\n", $this->configuration['stylesheets']);
        $static_plugin_config['exportInlineStyles']['stylesheets'] = explode("\n", $stylesheets);
      }
    }
    else {
      $static_plugin_config['removePlugins'][] = 'ExportInlineStyles';
      $static_plugin_config['removePlugins'][] = 'ExportInlineStylesAdapter';
    }


    if (empty($static_plugin_config['removePlugins'])) {
      unset($static_plugin_config['removePlugins']);
    }

    return $static_plugin_config;
  }

}
