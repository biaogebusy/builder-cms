<?php

namespace Drupal\core_context_test\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * @Block(
 *   id = "context_block",
 *   admin_label = @Translation("Context block"),
 *   context_definitions = {
 *     "value" = @ContextDefinition("any"),
 *     "letter" = @ContextDefinition("string"),
 *   },
 * )
 */
class ContextBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => $this->t('The context value is %value, brought to you by the letter %letter.', [
        '%value' => $this->getContextValue('value'),
        '%letter' => $this->getContextValue('letter'),
      ]),
    ];
  }

}
