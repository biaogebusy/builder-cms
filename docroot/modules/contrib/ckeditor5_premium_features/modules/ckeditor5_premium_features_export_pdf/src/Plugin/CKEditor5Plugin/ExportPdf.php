<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_export_pdf\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5_premium_features\Config\ExportFeaturesConfigHandler;
use Drupal\ckeditor5_premium_features\Generator\FileNameGeneratorInterface;
use Drupal\ckeditor5_premium_features\Plugin\CKEditor5Plugin\ExportBase;
use Drupal\ckeditor5_premium_features\Utility\CssStyleProvider;
use Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker;
use Drupal\ckeditor5_premium_features_export_pdf\Form\SettingsForm;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 "Export to Pdf" plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class ExportPdf extends ExportBase {

  const EXPORT_PDF_PLUGIN_ID = 'exportPdf';

  const EXPORT_FILE_EXTENSION = '.pdf';

  const CONFIGURATION_ID = 'ckeditor5_premium_features_export_pdf.settings';

  const EXPORT_SETTING_FORM = SettingsForm::class;

  /**
   * Constructs an ExportPdf object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\ckeditor5_premium_features\Config\ExportFeaturesConfigHandler $settingsConfigHandler
   *   The settings configuration handler.
   * @param \Drupal\ckeditor5_premium_features\Generator\FileNameGeneratorInterface $fileNameGenerator
   *   The file name generator service.
   * @param \Drupal\ckeditor5_premium_features\Utility\CssStyleProvider $cssStyleProvider
   *   The style css list provider service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   File system service.
   * @param \Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker $libraryVersionChecker
   *   The library version checker.
   * @param mixed ...$parent_arguments
   *   The parent plugin arguments.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    ExportFeaturesConfigHandler $settingsConfigHandler,
    FileNameGeneratorInterface $fileNameGenerator,
    CssStyleProvider $cssStyleProvider,
    FileSystemInterface $fileSystem,
    protected LibraryVersionChecker $libraryVersionChecker,
    ...$parent_arguments
  ) {
    parent::__construct(
      $configFactory,
      $settingsConfigHandler,
      $fileNameGenerator,
      $cssStyleProvider,
      $fileSystem,
      ...$parent_arguments
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get('config.factory'),
      $container->get('ckeditor5_premium_features_export_pdf.config_handler.export_settings'),
      $container->get('ckeditor5_premium_features.file_name_generator'),
      $container->get('ckeditor5_premium_features.css_style_provider'),
      $container->get('file_system'),
      $container->get('ckeditor5_premium_features.core_library_version_checker'),
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'converter_url' => NULL,
      'converter_options' => [
        'format' => NULL,
        'margin_top' => [
          'value' => NULL,
          'units' => NULL,
        ],
        'margin_bottom' => [
          'value' => NULL,
          'units' => NULL,
        ],
        'margin_left' => [
          'value' => NULL,
          'units' => NULL,
        ],
        'margin_right' => [
          'value' => NULL,
          'units' => NULL,
        ],
        'enable_mirror_margins' => NULL,
        'page_orientation' => NULL,
        'custom_css' => NULL,
        'header_html' => NULL,
        'footer_html' => NULL,
        'header_and_footer_css' => NULL,
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getSettingsForm(): string {
    return self::EXPORT_SETTING_FORM;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigId(): string {
    return self::CONFIGURATION_ID;
  }

  /**
   * {@inheritDoc}
   */
  public function getFeaturedPluginId(): string {
    return self::EXPORT_PDF_PLUGIN_ID;
  }

  /**
   * {@inheritDoc}
   */
  public function getExportFileExtension(): string {
    return self::EXPORT_FILE_EXTENSION;
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    if ($this->settingsConfigHandler->getEnvironmentId() && $this->settingsConfigHandler->getAccessKey() && !ckeditor5_premium_features_check_jwt_installed()) {
      $message = $this->t("Export to PDF plugin is working in license key authentication mode because its required dependency <code>firebase/php-jwt</code> is not installed. This may result with limited functionality.");
      ckeditor5_premium_features_display_missing_dependency_warning($message);
    }

    $pluginConfig = parent::getDynamicPluginConfig($static_plugin_config, $editor);

    $pluginId = $this->getFeaturedPluginId();
    $converterOptions = &$pluginConfig[$pluginId]['converterOptions'];

    $isV2 = $this->libraryVersionChecker
      ->isLibraryVersionHigherOrEqual('47.5.0');

    // For V2 only: assemble headers/footers from new flat fields and additional types.
    if ($isV2) {
      $headers = [];
      $footers = [];

      $defaultCss = isset($converterOptions['header_and_footer_css']) ? trim((string) $converterOptions['header_and_footer_css']) : '';
      $defaultHeaderHtml = isset($converterOptions['header_html']) ? trim((string) $converterOptions['header_html']) : '';
      $defaultFooterHtml = isset($converterOptions['footer_html']) ? trim((string) $converterOptions['footer_html']) : '';

      if ($defaultHeaderHtml !== '' || $defaultCss !== '') {
        $headers[] = [
          'type' => 'default',
          'html' => $defaultHeaderHtml,
          'css' => $defaultCss,
        ];
      }
      if ($defaultFooterHtml !== '' || $defaultCss !== '') {
        $footers[] = [
          'type' => 'default',
          'html' => $defaultFooterHtml,
          'css' => $defaultCss,
        ];
      }

      if (isset($converterOptions['additional_header_footer']) && is_array($converterOptions['additional_header_footer'])) {
        foreach (['first', 'even', 'odd'] as $type) {
          if (empty($converterOptions['additional_header_footer'][$type]) || !is_array($converterOptions['additional_header_footer'][$type])) {
            continue;
          }
          $group = $converterOptions['additional_header_footer'][$type];
          $css = isset($group['header_and_footer_css']) ? trim((string) $group['header_and_footer_css']) : '';
          $h = isset($group['header_html']) ? trim((string) $group['header_html']) : '';
          $f = isset($group['footer_html']) ? trim((string) $group['footer_html']) : '';

          if ($h !== '' || $css !== '') {
            $headers[] = [
              'type' => $type,
              'html' => $h,
              'css' => $css,
            ];
          }
          if ($f !== '' || $css !== '') {
            $footers[] = [
              'type' => $type,
              'html' => $f,
              'css' => $css,
            ];
          }
        }

      }

      if (!empty($headers)) {
        $converterOptions['header'] = $headers;
      }
      if (!empty($footers)) {
        $converterOptions['footer'] = $footers;
      }

      // Remove flat fields so they do not leak into the final payload.
      unset($converterOptions['header_html'], $converterOptions['footer_html'], $converterOptions['header_and_footer_css'], $converterOptions['additional_header_footer']);

      // Convert assembled V1-like structure to V2.
      $this->transformConverterOptionsFormatToV2($converterOptions);
      $pluginConfig[$pluginId]['version'] = 2;
      // Set default V2 converter URL if not already provided by settings.
      if (empty($pluginConfig[$pluginId]['converterUrl'])) {
        $pluginConfig[$pluginId]['converterUrl'] = 'https://pdf-converter.cke-cs.com/v2/convert/html-pdf';
      }

    }

    return $pluginConfig;
  }

}
