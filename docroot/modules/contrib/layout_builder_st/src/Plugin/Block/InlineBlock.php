<?php

namespace Drupal\layout_builder_st\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\Plugin\Block\InlineBlock as CoreInlineBlock;

/**
 * InlineBlock overridden to load correct block for editing.
 *
 * @todo Remove if fixed in https://www.drupal.org/node/3052042.
 */
final class InlineBlock extends CoreInlineBlock {

  /**
   * {@inheritdoc}
   *
   * Remove if fixed.
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $block = $this->getEntity();
    if (!$this->isNew && !$block->isNew() && empty($this->configuration['block_serialized'])) {
      // Get the active block for editing purposes.
      $block = \Drupal::service('entity.repository')->getActive('block_content', $block->id());
    }
    $form['block_form']['#block'] = $block;
    return $form;
  }

}
