<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference;

use Drupal\node\NodeInterface;

/** Retains an explicit empty split-field string while preserving legacy field emptiness. */
trait ChatSessionEmptyString {

  /** {@inheritdoc} */
  public function isEmpty() {
    $node = $this->getEntity();
    if ($this->get('value')->getValue() === '' && $node instanceof NodeInterface &&
      $node->bundle() === 'ai_session' && $node->hasField('storage_version') &&
      in_array($this->getFieldDefinition()->getName(), ['content', 'reasoning_content',
        'summary', 'img', 'style_preset', 'content_format'], TRUE) &&
      in_array($node->get('storage_version')->value, [2, '2', 3, '3'], TRUE)) {
      return FALSE;
    }
    return parent::isEmpty();
  }

}
