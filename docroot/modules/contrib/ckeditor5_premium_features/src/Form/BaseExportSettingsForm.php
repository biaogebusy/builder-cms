<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Form;

use Drupal\ckeditor5_premium_features\Utility\CssStyleProvider;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the base configuration for export feature.
 */
abstract class BaseExportSettingsForm extends SharedBuildConfigFormBase {

  /**
   * Name of the custom css file.
   */
  abstract public function getCustomCssFileName():string;

  /**
   * Constructs a BaseExportSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\ckeditor5_premium_features\Utility\CssStyleProvider $cssStyleProvider
   *   The css style provider.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              TypedConfigManagerInterface $typedConfigManager,
                              protected CssStyleProvider $cssStyleProvider) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('ckeditor5_premium_features.css_style_provider'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $customCss = $form_state->getValue('converter_options')['custom_css'] ?? NULL;
    $this->cssStyleProvider->updateCustomCssFile($customCss, $this->getCustomCssFileName());
    parent::submitForm($form, $form_state);
  }

}
