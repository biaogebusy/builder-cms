<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_ai\Element;

use Drupal\ckeditor5_premium_features\CKeditorFieldKeyHelper;
use Drupal\ckeditor5_premium_features\Element\Ckeditor5TextFormatBaseInterface;
use Drupal\ckeditor5_premium_features\Storage\EditorStorageHandlerInterface;
use Drupal\ckeditor5_premium_features_cloud_services\Element\TextFormat as CloudServicesTextFormat;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the Text Format utility class for AI plugin.
 */
class TextFormat extends CloudServicesTextFormat implements Ckeditor5TextFormatBaseInterface {

  /**
   * Creates the text format element instance.
   *
   * @param \Drupal\ckeditor5_premium_features\Storage\EditorStorageHandlerInterface $editorStorageHandler
   *   The editor storage handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    protected EditorStorageHandlerInterface $editorStorageHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($entityTypeManager, $editorStorageHandler);
  }

  /**
   * {@inheritDoc}
   */
  public function processElement(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    parent::processElement($element, $form_state, $complete_form);

    $elementDrupalId = CKeditorFieldKeyHelper::cleanElementDrupalId($element['#id']);
    $element['value']["#attributes"]['data-ckeditorfieldid'] = $elementDrupalId;
    $element['value']['#theme'] = 'ckeditor5_textarea';

    // Creates an AI sidebar container with id specific for given field item.
    $aiSidebar = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['ck', 'ai-sidebar-container'],
        'id' => [
          $element["#attributes"]["data-drupal-selector"] . '-value-ck-ai-sidebar',
        ],
      ],
    ];

    $container_html = \Drupal::service('renderer')->render($aiSidebar);
    $element['value']['#ai_sidebar'] = $container_html;

    self::addCallback('onCompleteFormSubmit', [['actions', 'submit', '#submit']], $complete_form);
    return $element;
  }

  /**
   * {@inheritDoc}
   */
  public static function process(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $service = \Drupal::service('ckeditor5_premium_features_ai.element.text_format');
    return $service->processElement($element, $form_state, $complete_form);
  }

  /**
   * {@inheritdoc}
   */
  public static function onValidateForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
  }

}
