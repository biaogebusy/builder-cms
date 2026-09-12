<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5_premium_features\Config\ExportFeaturesConfigHandler;
use Drupal\ckeditor5_premium_features\Form\BaseExportSettingsForm;
use Drupal\ckeditor5_premium_features\Form\SharedBuildConfigFormInterface;
use Drupal\ckeditor5_premium_features\Generator\FileNameGeneratorInterface;
use Drupal\ckeditor5_premium_features\Plugin\ExportPluginDefinitionInterface;
use Drupal\ckeditor5_premium_features\Utility\CssStyleProvider;
use Drupal\ckeditor5_premium_features\Utility\FormElement;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 export related modules base plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
abstract class ExportBase extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface, ContainerFactoryPluginInterface, ExportPluginDefinitionInterface {
  use CKEditor5PluginConfigurableTrait;

  const CUSTOM_CSS_DIRECTORY_PATH = 'public://styles/ckeditor5/export/';
  const CONVERT_IMAGES_TO_BASE_64_CONFIG_NAME = 'convertImagesToBase64';
  const CAPTIONS_FILTER_ENABLED_CONFIG_NAME = 'captionsFilterEnabled';
  /**
   * The settings form object.
   *
   * @var \Drupal\ckeditor5_premium_features\Form\SharedBuildConfigFormInterface
   */
  protected SharedBuildConfigFormInterface $settingsForm;

  /**
   * Creates the plugin instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\ckeditor5_premium_features\Config\ExportFeaturesConfigHandlerInterface $settingsConfigHandler
   *   The settings configuration handler.
   * @param \Drupal\ckeditor5_premium_features\Generator\FileNameGeneratorInterface $fileNameGenerator
   *   The file name generator service.
   * @param \Drupal\ckeditor5_premium_features\Utility\CssStyleProvider $cssStyleProvider
   *   The style css list provider service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   File system service.
   * @param mixed ...$parent_arguments
   *   The parent plugin arguments.
   *
   * @throws \ReflectionException
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ExportFeaturesConfigHandler $settingsConfigHandler,
    protected FileNameGeneratorInterface $fileNameGenerator,
    protected CssStyleProvider $cssStyleProvider,
    protected FileSystemInterface $fileSystem,
    ...$parent_arguments
  ) {
    parent::__construct(...$parent_arguments);
    $this->settingsForm = (new \ReflectionClass($this->getSettingsForm()))->newInstanceWithoutConstructor();
    $this->settingsConfigHandler->setConfig($this->getConfigId());
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get('config.factory'),
      $container->get('ckeditor5_premium_features.config_handler.export_settings'),
      $container->get('ckeditor5_premium_features.file_name_generator'),
      $container->get('ckeditor5_premium_features.css_style_provider'),
      $container->get('file_system'),
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $plugin = $this->getFeaturedPluginId();

    if ($this->settingsConfigHandler->hasConverterUrl()) {
      $static_plugin_config[$plugin]['converterUrl'] = $this->settingsConfigHandler->getConverterUrl();
    }
    if ($this->settingsConfigHandler->getEnvironmentId() && $this->settingsConfigHandler->getAccessKey() && ckeditor5_premium_features_check_jwt_installed()) {
      $static_plugin_config[$plugin]['tokenUrl'] = $this->settingsConfigHandler->getTokenUrl();
    }

    $static_plugin_config[$plugin]['converterOptions'] = $this->getCurrentConfiguration();
    $static_plugin_config[$plugin]['converterOptions']['base_url'] = \Drupal::request()->getSchemeAndHttpHost();

    $file_extension = $this->getExportFileExtension();
    $file_name = $this->fileNameGenerator->generateFromRequest();
    $this->fileNameGenerator->addExtensionFile($file_name, $file_extension);
    $static_plugin_config[$plugin]['fileName'] = $file_name;
    $static_plugin_config[$plugin]['stylesheets'] = $this->cssStyleProvider->getFormattedListOfCssFiles($editor);
    $customCssFile = $this->getCustomCssFilePath($editor->getOriginalId());
    if ($customCssFile) {
      $static_plugin_config[$plugin]['stylesheets'][] = $customCssFile;
    }

    $settings = $editor->getSettings();
    $isBase64ConverterEnabled = $settings['plugins'][$this->pluginId]['base64_converter']['convert_images_to_base64'] ?? FALSE;
    $base64ConverterFilesType = $settings['plugins'][$this->pluginId]['base64_converter']['images_to_base64_files_type'] ?? NULL;
    $static_plugin_config[$plugin][self::CONVERT_IMAGES_TO_BASE_64_CONFIG_NAME]['enabled'] = $isBase64ConverterEnabled;
    $static_plugin_config[$plugin][self::CONVERT_IMAGES_TO_BASE_64_CONFIG_NAME]['filesType'] = $base64ConverterFilesType;

    $filters = $editor->getFilterFormat()->get('filters');
    $static_plugin_config[$plugin][self::CAPTIONS_FILTER_ENABLED_CONFIG_NAME] = FALSE;
    if (isset($filters['filter_caption']) && $filters['filter_caption']['status']) {
      $static_plugin_config[$plugin][self::CAPTIONS_FILTER_ENABLED_CONFIG_NAME] = TRUE;
    }

    return $static_plugin_config;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get($this->getPluginId());

    $global_options = $this->settingsConfigHandler->getConverterOptions();
    $global_custom_css = $global_options['custom_css'] ?? NULL;
    $override_global = $this->configuration['override_global'] ?? FALSE;

    $form['override_global'] = [
      '#type' => 'checkbox',
      '#title' => 'Override global settings',
      '#description' => $this->t('Using below form you can overwrite the <a href="@url">global export settings </a>.', [
        '@url' => Url::fromRoute($this->settingsForm::getSettingsRouteName())->toString(),
      ]),
      '#default_value' => $override_global,
    ];

    if (!$override_global) {
      $config->initWithData([
        'custom_css' => $global_custom_css,
        'converter_options' => $global_options,
      ]);
    }
    else {
      $config->initWithData($this->configuration);
    }

    $export_form = $this->settingsForm::form($form, $form_state, $config);
    unset($export_form['converter_url']);

    FormElement::setPlaceholders($export_form, $global_options);

    if (!$override_global) {
      FormElement::disableFormFields($export_form);
    }
    $export_form['converter_options']['#open'] = FALSE;

    $export_form['base64_converter'] = [
      '#type' => 'details',
      '#title' => $this->t('Base64 image converter settings'),
      '#open' => FALSE,
    ];

    $export_form['base64_converter']['convert_images_to_base64'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable the Base64 image converter'),
      '#default_value' => $this->configuration['base64_converter']['convert_images_to_base64'] ?? FALSE,
      '#description' => $this->t('It will process the whole document and change all image URLs into their Base64-encoded representations.
                                  <br/> <b>Attention! This setting may cause server overload.</b> '),
    ];

    $export_form['base64_converter']['images_to_base64_files_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Image file type'),
      '#options' => [
        'private' => $this->t('Only private images'),
        'all' => $this->t('All images (private and public)'),
      ],
      '#default_value' => $this->configuration['base64_converter']['images_to_base64_files_type'] ?? 'private',
      '#description' => $this->t('Choose whether only private images or all images should be converted into base64-encoded ones in the document.'),
    ];

    return $export_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration = $form_state->cleanValues()->getValues();

    if (!empty($this->configuration['override_global']) &&
        $this->settingsForm instanceof BaseExportSettingsForm) {
      $formObject = $form_state->getFormObject();
      $editor = $formObject->getEntity();
      $fileName = $this->settingsForm->getCustomCssFileName() . '-' . $editor->getOriginalId();
      $this->cssStyleProvider->updateCustomCssFile($this->configuration['converter_options']['custom_css'], $fileName);
    }

    unset($this->configuration['converter_options']['header']['actions']);
    unset($this->configuration['converter_options']['footer']['actions']);
  }

  /**
   * Returns final plugin configuration.
   */
  protected function getCurrentConfiguration(): array {
    $global_config = array_filter($this->settingsConfigHandler->getConverterOptions());
    $this->processConfigCleanup($global_config);

    if (empty($this->configuration['override_global']) && !empty($global_config)) {
      return $global_config;
    }

    $format_config = array_filter($this->configuration['converter_options']);
    $this->processConfigCleanup($format_config);

    /*
     * Here we are merging two configurations, from the custom settings form
     * and from the text format plugin page. The current order, means that
     * the plugin settings will overwrite the custom settings form values.
     */
    $merged_config = NestedArray::mergeDeepArray([
      $global_config,
      $format_config,
    ], TRUE);

    $adds = [
      'header',
      'footer',
    ];
    foreach ($adds as $placement) {
      if (isset($format_config[$placement])) {
        $merged_config[$placement] = $format_config[$placement];
      }
    }

    return $merged_config;
  }

  /**
   * Processing export configuration to perform required clean-ups.
   *
   * @param array $config
   *   Config array to be processed.
   */
  protected function processConfigCleanup(array &$config): void {
    $margins = [
      'top',
      'bottom',
      'left',
      'right',
    ];
    foreach ($margins as $direction) {
      $key = 'margin_' . $direction;
      if (empty($config[$key]) || !is_array($config[$key])) {
        continue;
      }
      $config[$key] = $config[$key]['value'] . $config[$key]['units'];
    }

    $adds = [
      'header',
      'footer',
    ];
    foreach ($adds as $placement) {
      unset($config[$placement]['actions']);
      if (!isset($config[$placement])) {
        continue;
      }
      if (is_array($config[$placement])) {
        foreach ($config[$placement] as $key => $item) {
          if ($item['html'] == '') {
            unset($config[$placement][$key]);
          }
        }
      }
      if (empty($config[$placement])) {
        unset($config[$placement]);
      }
    }

    $config = array_filter($config);
  }

  /**
   * Get the file with custom css.
   *
   * @param string $editorId
   *   The Editor id.
   *
   * @return string|bool
   *   The file path for custom css or null.
   */
  protected function getCustomCssFilePath(string $editorId): bool|string {
    if (!$this->settingsForm instanceof BaseExportSettingsForm) {
      return FALSE;
    }

    if (!empty($this->configuration['override_global'])) {
      $fileName = $this->settingsForm->getCustomCssFileName() . '-' . $editorId;
    }
    else {
      $fileName = $this->settingsForm->getCustomCssFileName();
    }

    return $this->cssStyleProvider->getCustomCssFile($fileName);
  }

  /**
   * Converts the export plugins converterOptions to the V2 format.
   *
   * @param array $config
   *   The export plugins converterOptions array.
   * @return void
   */
  protected function transformConverterOptionsFormatToV2(array &$config): void {
    $oldFormatConfig = $config;
    $config = [];
    $config['document']['size'] = $oldFormatConfig['format'];
    $config['document']['margins'] = [
      'top' => $oldFormatConfig['margin_top'],
      'bottom' => $oldFormatConfig['margin_bottom'],
      'left' => $oldFormatConfig['margin_left'],
      'right' => $oldFormatConfig['margin_right'],
    ];
    if (isset($oldFormatConfig['orientation'])) {
      $config['document']['orientation'] = $oldFormatConfig['orientation'];
    }
    if (isset($oldFormatConfig['enable_mirror_margins'])) {
      $config['document']['margins']['enable_mirror_margins'] = $oldFormatConfig['enable_mirror_margins'];
    }
    if (isset($oldFormatConfig['header'])) {
      $config['headers'] = $this->transformHeaderAndFooterConfigToV2($oldFormatConfig['header']);
    }
    if (isset($oldFormatConfig['footer'])) {
      $config['footers'] = $this->transformHeaderAndFooterConfigToV2($oldFormatConfig['footer']);
    }
  }

  /**
   * Converts the export plugins header and footer configuration to the V2 format.
   *
   * @param array $v1Config
   *   The V1 configuration array.
   * @return array
   *   The V2 configuration array.
   */
  private function transformHeaderAndFooterConfigToV2($v1Config): array {
    $v2Config = [];
    foreach ($v1Config as $v1ConfigItem) {
      $type = $v1ConfigItem['type'];
      $v2Config[$type] = [
        'html' => $v1ConfigItem['html'],
        'css' => $v1ConfigItem['css'],
      ];
    }
    return $v2Config;
  }

}
