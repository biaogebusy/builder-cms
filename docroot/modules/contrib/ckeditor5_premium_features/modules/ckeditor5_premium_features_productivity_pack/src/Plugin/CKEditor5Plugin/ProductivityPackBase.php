<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_productivity_pack\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 Productivity Pack Base Plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class ProductivityPackBase extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface, ContainerFactoryPluginInterface {

  use CKEditor5PluginConfigurableTrait;

  const PLUGIN_CONFIG_NAME = 'ckeditor5_premium_features_productivity_pack_base';

  /**
   * The CKEditor 5 library version checker service.
   *
   * @var \Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker
   */
  protected LibraryVersionChecker $libraryVersionChecker;

  /**
   * Constructs a \Drupal\ckeditor5_premium_features_productivity_pack\Plugin\CKEditor5Plugin\ProductivityPackBase object.
   *
   * @param array $configuration
   *    A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *    The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *    The plugin implementation definition.
   * @param LibraryVersionChecker $library_version_checker
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LibraryVersionChecker $library_version_checker) {
    $this->libraryVersionChecker = $library_version_checker;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ckeditor5_premium_features.core_library_version_checker'));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      DocumentOutline::CONFIG_FIELD_ENABLED => FALSE,
      SlashCommand::CONFIG_FIELD_ENABLED => FALSE,
      PasteFromOfficeEnhanced::CONFIG_FIELD_ENABLED => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form[DocumentOutline::CONFIG_FIELD_ENABLED] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Document Outline'),
      '#default_value' => $this->configuration[DocumentOutline::CONFIG_FIELD_ENABLED] ?? FALSE,
      '#description' => $this->t('Enable Document Outline in the editor'),
    ];
    $form[SlashCommand::CONFIG_FIELD_ENABLED] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Slash Command'),
      '#default_value' => $this->configuration[SlashCommand::CONFIG_FIELD_ENABLED] ?? FALSE,
      '#description' => $this->t('Enable Slash Command in the editor'),
    ];
    $form[PasteFromOfficeEnhanced::CONFIG_FIELD_ENABLED] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Paste from Office Enhanced'),
      '#default_value' => $this->configuration[PasteFromOfficeEnhanced::CONFIG_FIELD_ENABLED] ?? FALSE,
      '#description' => $this->t('Enable Paste from Office Enhanced in the editor'),
    ];
    if (!$this->libraryVersionChecker->isLibraryVersionHigherOrEqual('39.0.0')) {
      $form[PasteFromOfficeEnhanced::CONFIG_FIELD_ENABLED]['#attributes']['disabled'] = 'disabled';
      $currentVersion = $this->libraryVersionChecker->getCurrentVersion();
      $descriptionSuffix = $this->t('The feature is available for CKEditor 5 version 39.0.0 or higher. Currently installed version is ');
      $descriptionSuffix .= $currentVersion;
      $form[PasteFromOfficeEnhanced::CONFIG_FIELD_ENABLED]['#description'] .= "<br />" . $descriptionSuffix;

    }
    return $form;
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
    $formValues = $form_state->getValues();
    $isDocumentOutlineEnabled = $formValues[DocumentOutline::CONFIG_FIELD_ENABLED] ?? FALSE;
    $isSlashCommandEnabled = $formValues[SlashCommand::CONFIG_FIELD_ENABLED] ?? FALSE;
    $isPasteFromOfficeEnhancedEnabled = $formValues[PasteFromOfficeEnhanced::CONFIG_FIELD_ENABLED] ?? FALSE;
    $this->configuration[DocumentOutline::CONFIG_FIELD_ENABLED] = (bool) $isDocumentOutlineEnabled;
    $this->configuration[SlashCommand::CONFIG_FIELD_ENABLED] = (bool) $isSlashCommandEnabled;
    $this->configuration[PasteFromOfficeEnhanced::CONFIG_FIELD_ENABLED] = (bool) $isPasteFromOfficeEnhancedEnabled;
  }

}
