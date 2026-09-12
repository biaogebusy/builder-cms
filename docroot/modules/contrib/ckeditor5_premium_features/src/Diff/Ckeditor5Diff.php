<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features\Diff;

use Drupal\ckeditor5_premium_features\Utility\ContextHelper;

/**
 * Ckeditor5 helper class for detecting document changes.
 */
class Ckeditor5Diff implements Ckeditor5DiffInterface {

  /**
   * String representing recently processed document with all changes marked.
   *
   * @var string
   */
  protected string $context;

  /**
   * Constructor.
   *
   * @param \Drupal\ckeditor5_premium_features\Utility\ContextHelper $contextHelper
   *   Context detecting helper service.
   */
  public function __construct(
    protected ContextHelper $contextHelper,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getDiff(string $oldDocument, string $newDocument): ?string {
    // It will prevent a wall of warnings about invalid html tags.
    $originalLibxmlErrorState = libxml_use_internal_errors(TRUE);

    // When <img> tag is right next to paragraph or heading is not recognize by HtmlDiff e.g. </p><img>
    // Adding space after closing tag fix that issue.
    $newDocument = str_replace('<img', ' <img', $newDocument);

    $htmlDiff = new Ckeditor5HtmlDiff($oldDocument, $newDocument);
    $htmlDiff->getConfig()
      ->setPurifierEnabled(FALSE);

    $tags = $htmlDiff->getConfig()->getIsolatedDiffTags();
    $tags['u'] = '[[REPLACE_U]]';
    $tags['s'] = '[[REPLACE_S]]';
    $tags['h1'] = '[[REPLACE_H1]]';
    $tags['h2'] = '[[REPLACE_H2]]';
    $tags['h3'] = '[[REPLACE_H3]]';
    $tags['h4'] = '[[REPLACE_H4]]';
    $tags['h5'] = '[[REPLACE_H5]]';
    $tags['h6'] = '[[REPLACE_H6]]';
    $tags['blockquote'] = '[[REPLACE_BLOCKQUOTE]]';
    $tags['code'] = '[[REPLACE_CODE]]';
    $tags['pre'] = '[[REPLACE_PRE]]';
    $htmlDiff->getConfig()->setIsolatedDiffTags($tags);

    $this->context = $htmlDiff->build();

    // Clear the buffer and set the original state of libxml errors.
    libxml_clear_errors();
    libxml_use_internal_errors($originalLibxmlErrorState);

    return $htmlDiff->getAddedContent();
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentChanges(string $oldDocument, string $newDocument): array {
    // It will prevent a wall of warnings about invalid html tags.
    $originalLibxmlErrorState = libxml_use_internal_errors(TRUE);

    $htmlDiff = new Ckeditor5HtmlDiff($oldDocument, $newDocument);
    $htmlDiff->getConfig()
      ->setPurifierEnabled(FALSE);

    $this->context = $htmlDiff->build();

    // Clear the buffer and set the original state of libxml errors.
    libxml_clear_errors();
    libxml_use_internal_errors($originalLibxmlErrorState);

    return $htmlDiff->getChanges();
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffAddedContext(): ?string {
    $highlights = $this->contextHelper->getDocumentChangesContext($this->context, TRUE);

    return implode('<div class="spacer">...</div>', $highlights);
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffContext(): ?string {
    $highlights = $this->contextHelper->getDocumentChangesContext($this->context);

    return implode('<div class="spacer">...</div>', $highlights);
  }

}
