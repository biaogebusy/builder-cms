<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_import_word\Form;

use Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure CKEditor 5 Import from Word settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              TypedConfigManagerInterface $typedConfigManager,
                              protected LibraryVersionChecker $libraryVersionChecker) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('ckeditor5_premium_features.core_library_version_checker')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ckeditor5_premium_features_import_word_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ckeditor5_premium_features_import_word.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($form_state->getFormObject()->getFormId() == 'ckeditor5_premium_features_import_word_settings') {
      self::checkDependencyPackage();
    }
    $config = $this->config('ckeditor5_premium_features_import_word.settings');

    $form['converter_url'] = [
      '#type' => 'textfield',
      '#title' => t('Converter URL'),
      '#description' => t('Leave this field empty unless you are using the on-premises version of Import from Word.'),
      '#default_value' => $config->get('converter_url'),
    ];

    $form['env'] = [
      '#type' => 'textfield',
      '#title' => t('Environment ID'),
      '#required' => FALSE,
      '#description' => t('Leave this field empty unless, for Import from Word, you are using a different environment than the one from the main module configuration.'),
      '#default_value' => $config->get('env'),
    ];

    $form['access_key'] = [
      '#type' => 'textfield',
      '#title' => t('Access key'),
      '#required' => FALSE,
      '#description' => t('Leave this field empty unless, for Import from Word, you are using a different environment than the one from the main module configuration.'),
      '#default_value' => $config->get('access_key'),
    ];

    $form['info'] = [
      '#markup' => $this->t('You can learn more about configuration options in the <a target="_blank" href="@guides-url">Styles</a> guide for Import from Word.', ['@guides-url' => 'https://ckeditor.com/docs/cs/latest/guides/import-from-word/styles.html#default-styles']),
    ];
    $form['word_styles'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Word's default styles"),
      '#description' => $this->t('If checked, Word’s default styles will be preserved in the imported content.'),
      '#default_value' => $config->get('word_styles'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $cleanValues = $form_state->cleanValues()->getValues();
    $this->configFactory->getEditable('ckeditor5_premium_features_import_word.settings')
      ->setData($cleanValues)
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Checks if the required library is installed and displays warning message in case it's missing,
   */
  public static function checkDependencyPackage(): void {
    if (!ckeditor5_premium_features_check_dependency_class('Firebase\JWT\JWT')) {
      $message = t('Import from Word plugin will work in license key authentication mode because its required dependency <code>firebase/php-jwt</code> is not installed. This may result with limited functionality.');
      ckeditor5_premium_features_display_missing_dependency_warning($message);
    }
  }

}
