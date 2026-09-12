<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_export_word\Form;

use Drupal\ckeditor5_premium_features\Form\BaseExportSettingsForm;
use Drupal\ckeditor5_premium_features\Utility\FormElement;
use Drupal\Core\Config\Config;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Provides the configuration form of the "Export to Word" feature.
 */
class SettingsForm extends BaseExportSettingsForm {

  const EXPORT_WORD_CONFIG_NAME = 'ckeditor5_premium_features_export_word.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ckeditor5_premium_features_export_word_settings';
  }

  /**
   * {@inheritdoc}
   */
  public static function getSettingsRouteName(): string {
    return 'ckeditor5_premium_features_export_word.form.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomCssFileName(): string {
    return 'ckeditor5-custom-word-styles';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigId(): string {
    return self::EXPORT_WORD_CONFIG_NAME;
  }

  /**
   * {@inheritdoc}
   */
  public static function form(array $form, FormStateInterface $form_state, Config $config): array {
    if ($form_state->getFormObject()->getFormId() == 'ckeditor5_premium_features_export_word_settings') {
      self::checkDependencyPackage();
    }
    $form['converter_url'] = [
      '#type' => 'textfield',
      '#title' => t('Converter URL'),
      '#description' => t('Leave this field empty unless you are using the on-premises version of Export to Word.'),
      '#default_value' => $config->get('converter_url'),
    ];

    $form['env'] = [
      '#type' => 'textfield',
      '#title' => t('Environment ID'),
      '#required' => FALSE,
      '#description' => t('Leave this field empty unless, for Export to Word, you are using a different environment than the one from the main module configuration.'),
      '#default_value' => $config->get('env'),
    ];

    $form['access_key'] = [
      '#type' => 'textfield',
      '#title' => t('Access key'),
      '#required' => FALSE,
      '#description' => t('Leave this field empty unless, for Export to Word, you are using a different environment than the one from the main module configuration.'),
      '#default_value' => $config->get('access_key'),
    ];

    $options_key = 'converter_options';
    $form[$options_key] = [
      '#type' => 'details',
      '#title' => t('Converter options'),
      '#tree' => TRUE,
      '#open' => TRUE,
    ];

    $options = &$form[$options_key];

    FormElement::format($options, [
      '#default_value' => $config->get($options_key . '.format') ?? 'A4',
    ]);

    $margins = [
      'top',
      'bottom',
      'left',
      'right',
    ];

    foreach ($margins as $margin) {
      $margin_config = $config->get($options_key . '.margin_' . $margin);
      FormElement::marginElement($options, $margin, $margin_config);
    }

    FormElement::pageOrientation($options, [
      '#default_value' => $config->get($options_key . '.page_orientation') ?? 'portrait',
    ]);

    $options['custom_css'] = [
      '#type' => 'textarea',
      '#title' => t('Custom css'),
      '#default_value' => $config->get($options_key . '.custom_css'),
    ];

    $num_headers = $form_state->get('num_headers');
    $num_footers = $form_state->get('num_footers');

    if ($num_headers === NULL) {
      $headers_items = $config->get("$options_key.header") ?? [];
      unset($headers_items['actions']);
      $num_headers = max(count($headers_items), 1);
      $form_state->set('num_headers', max($num_headers, 1));
    }
    if ($num_footers === NULL) {
      $footer_items = $config->get("$options_key.footer") ?? [];
      unset($footer_items['actions']);
      $num_footers = max(count($footer_items), 1);
      $form_state->set('num_footers', $num_footers);
    }

    foreach (['header' => $num_headers, 'footer' => $num_footers] as $type => $type_count) {
      FormElement::headingFooter($options, $type, $config->get("$options_key.$type") ?? [], $type_count);
    }

    $form['converter_options']['watermark'] = [
      '#type' => 'details',
      '#title' => t('Watermark'),
      '#open' => FALSE,
    ];
    $form['converter_options']['watermark']['image_url'] = [
      '#type' => 'textfield',
      '#title' => t('Watermark Image URL'),
      '#default_value' => $config->get($options_key . '.watermark.image_url'),
      '#description' => t('URL of the watermark image to be applied to the exported Word document.'),
    ];
    $form['converter_options']['watermark']['image'] = [
      '#type' => 'managed_file',
      '#title' => t('Upload Watermark Image'),
      '#upload_location' => 'public://ckeditor5/',
      '#default_value' => $config->get($options_key . '.watermark.image') ? $config->get($options_key . '.watermark.image') : NULL,
      '#description' => t('Alternatively, upload an image to be used as a watermark. This will override the URL above if both are provided.'),
    ];
    $form['converter_options']['watermark']['width'] = [
      '#type' => 'textfield',
      '#title' => t('Width'),
      '#default_value' => $config->get($options_key . '.watermark.width'),
      '#description' => t('Width of the watermark image. Supports <i>px</i>, <i>cm</i>, <i>mm</i> and <i>in</i> units.<br /> The minimal values are: 96px, 2.54cm, 25.4mm and 1in. Lower values will result with a conversion error.'),
    ];
    $form['converter_options']['watermark']['height'] = [
      '#type' => 'textfield',
      '#title' => t('Height'),
      '#default_value' => $config->get($options_key . '.watermark.height'),
      '#description' => t('Width of the watermark image. Supports <i>px</i>, <i>cm</i>, <i>mm</i> and <i>in</i> units.<br /> The minimal values are: 96px, 2.54cm, 25.4mm and 1in. Lower values will result with a conversion error.'),
    ];
    $form['converter_options']['watermark']['washout'] = [
      '#type' => 'checkbox',
      '#title' => t('Washout'),
      '#default_value' => $config->get($options_key . '.watermark.washout'),
      '#description' => t('If checked, the watermark will be displayed with reduced opacity.'),
    ];


    return $form;
  }

  /**
   * Checks if the required library is installed and displays warning message in case it's missing,
   */
  public static function checkDependencyPackage(): void {
    if (!ckeditor5_premium_features_check_dependency_class('Firebase\JWT\JWT')) {
      $message = t('Export to Word plugin will work in license key authentication mode because its required dependency <code>firebase/php-jwt</code> is not installed. This may result with limited functionality.');
      ckeditor5_premium_features_display_missing_dependency_warning($message);
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $converterOptions = $form_state->getValue('converter_options');
    if (isset($converterOptions['watermark']['image']) && is_array($converterOptions['watermark']['image']) && isset($converterOptions['watermark']['image'][0])) {
      $fileId = $converterOptions['watermark']['image'][0];
      $file = File::load($fileId);
      if ($file) {
        $file->setPermanent();
        $file->save();
      }
    }
  }

}
