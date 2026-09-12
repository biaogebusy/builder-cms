<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_productivity_pack\Element;

use Drupal\ckeditor5_premium_features\Element\Ckeditor5TextFormatBaseInterface;
use Drupal\ckeditor5_premium_features\Storage\EditorStorageHandlerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the Text Format utility class for handling the collaboration data.
 */
class TextFormat implements Ckeditor5TextFormatBaseInterface {

  /**
   * Creates the text format element instance.
   *
   * @param \Drupal\ckeditor5_premium_features\Storage\EditorStorageHandlerInterface $editorStorageHandler
   *   The editor storage handler.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    protected EditorStorageHandlerInterface $editorStorageHandler,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public function processElement(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $documentOutlineEnabled = $this->editorStorageHandler->hasDocumentOutlineFeaturesEnabled($element);
    if (!$documentOutlineEnabled) {
      return $element;
    }
    // Creates a document outline container with id specific for given field item.
    $document_outline_container = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['ck', 'document-outline-container', 'collapsed', 'hidden'],
        'id' => [
          $element["#attributes"]["data-drupal-selector"] . '-value-ck-document-outline',
        ],
      ],
    ];

    $container_html = \Drupal::service('renderer')->render($document_outline_container);
    $element['value']['#document_outline'] = $container_html;

    $element['value']['#theme'] = 'ckeditor5_textarea';
    return $element;
  }

  /**
   * {@inheritDoc}
   */
  public static function process(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $service = \Drupal::service('ckeditor5_premium_features_productivity_pack.element.text_format');
    return $service->processElement($element, $form_state, $complete_form);
  }

}
