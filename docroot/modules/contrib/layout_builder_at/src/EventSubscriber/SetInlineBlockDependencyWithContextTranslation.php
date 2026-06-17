<?php

namespace Drupal\layout_builder_at\EventSubscriber;

use Drupal\Core\Language\LanguageInterface;
use Drupal\block_content\BlockContentInterface;
use Drupal\layout_builder\EventSubscriber\SetInlineBlockDependency;

/**
 * Overrides SetInlineBlockDependency to load the correct translation.
 *
 * This class takes over
 * \Drupal\layout_builder\EventSubscriber\SetInlineBlockDependency to ensure
 * that the entity is loaded in the correct language context.
 */
class SetInlineBlockDependencyWithContextTranslation extends SetInlineBlockDependency {

  /**
   * {@inheritdoc}
   */
  protected function getInlineBlockDependency(BlockContentInterface $block_content) {
    // Override to call getTranslationFromContext() on the entity.
    $layout_entity_info = $this->usage->getUsage($block_content->id());
    if (empty($layout_entity_info)) {
      // If the block does not have usage information then we cannot set a
      // dependency. It may be used by another module besides layout builder.
      return NULL;
    }
    $layout_entity_storage = $this->entityTypeManager->getStorage($layout_entity_info->layout_entity_type);
    $layout_entity = $layout_entity_storage->load($layout_entity_info->layout_entity_id);
    $langcode = $block_content->language()->getId();
    $layout_entity = \Drupal::service('entity.repository')->getTranslationFromContext($layout_entity, $langcode !== LanguageInterface::LANGCODE_NOT_SPECIFIED ? $langcode : NULL);
    if ($this->isLayoutCompatibleEntity($layout_entity)) {
      if ($this->isBlockRevisionUsedInEntity($layout_entity, $block_content)) {
        return $layout_entity;
      }

    }
    return NULL;
  }

}
