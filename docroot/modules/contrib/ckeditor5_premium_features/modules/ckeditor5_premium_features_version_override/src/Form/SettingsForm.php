<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_version_override\Form;

use Drupal\ckeditor5_premium_features_version_override\Utility\OverrideHandler;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides the form for the main module & submodule configuration.
 */
class SettingsForm extends ConfigFormBase {

  const PREMIUM_FEATURES_VERSION_OVERRIDE_CONFIG_NAME = 'ckeditor5_premium_features_version_override.settings';

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $config;

  /**
   * Class constructor.
   *
   * @param ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param TypedConfigManagerInterface $typedConfigManager
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              TypedConfigManagerInterface $typedConfigManager,
                              protected OverrideHandler $overrideHandler) {
    $this->config = $config_factory->get(self::PREMIUM_FEATURES_VERSION_OVERRIDE_CONFIG_NAME);
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('ckeditor5_premium_features_version_override.override_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ckeditor5_premium_features_version_override_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      self::PREMIUM_FEATURES_VERSION_OVERRIDE_CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable CKEditor 5 DLL override'),
      '#default_value' => (bool) $this->config->get('enabled') ?? FALSE,
    ];
    $values = [
      'none' => $this->t('Not set'),
      '44.0.0' => '44.0.0',
      '44.3.0' => '44.3.0',
    ];

    $absoluteDirLocation = $this->overrideHandler->getAbsoluteLibrariesLocation();

    $directories = array_filter(glob($absoluteDirLocation . '*'), 'is_dir');
    $keyValueArray = [];
    foreach ($directories as $directory) {
      $dirName = basename($directory);
      $keyValueArray[$dirName] = $dirName;
    }

    $form['core_version'] = [
      '#markup' => $this->t('The default CKEditor 5 library version for your current Drupal version is <strong>%version</strong>.<br/>
        You should disable the override once the Drupal core provides the same or newer CKEditor 5 version.', [
        '%version' => $this->getDefaultLibraryVersion(),
      ]),
    ];

    $options = $values + $keyValueArray;
    $form['version'] = [
      '#type' => 'select',
      '#title' => $this->t('Version'),
      '#options' => $options,
      '#default_value' => $this->config->get('version') ?? 'none',
      '#states' => [
        'visible' => [
          'input[name="enabled"]' => ['checked' => TRUE],
        ],
      ]
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config(self::PREMIUM_FEATURES_VERSION_OVERRIDE_CONFIG_NAME);
    $clean_values = $this->processCleanValues($form_state);

    if ($config->get('enabled') !== $clean_values['enabled'] ||
      $config->get('version') !== $clean_values['version']) {
      $invalidate_tags = [
        'library_info',
      ];
      Cache::invalidateTags($invalidate_tags);
    }

    $config
      ->setData($clean_values)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Additionally cleans up the form state values.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object that values should be cleaned up additionally.
   *
   * @return array
   *   Form state clean values.
   */
  protected function processCleanValues(FormStateInterface $form_state): array {
    $clean_values = $form_state->cleanValues()->getValues();

    foreach ($clean_values as &$value) {
      if (is_string($value)) {
        $value = trim($value);
      }
    }
    $form_state->setValues($clean_values);

    return $clean_values;
  }

  /**
   * Get the default CKEditor 5 library version.
   *
   * @return string
   *   Default library version.
   */
  private function getDefaultLibraryVersion(): string {
    $filePath = DRUPAL_ROOT . '/core/core.libraries.yml';
    $fileContents = file_get_contents($filePath);
    $ymlData = Yaml::parse($fileContents);

    return $ymlData['ckeditor5']['version'];
  }


}
