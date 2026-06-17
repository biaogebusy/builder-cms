<?php

namespace Drupal\layout_builder_st\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\Plugin\Field\FieldWidget\LayoutBuilderWidget as CoreLayoutBuilderWidget;
use Drupal\layout_builder_st\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder_st\TranslatableSectionStorageInterface;

/**
 * Extended LayoutBuilderWidget to extract the value translation field.
 */
final class LayoutBuilderWidget extends CoreLayoutBuilderWidget {

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    if ($field_name === OverridesSectionStorage::FIELD_NAME) {
      parent::extractFormValues($items, $form, $form_state);
      return;
    }
    if (!$form_state->isValidationComplete()) {
      return;
    }
    $section_storage = $this->getSectionStorage($form_state);
    if ($field_name === OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME && $section_storage instanceof TranslatableSectionStorageInterface) {
      // The translated configuration is stored in single value field because it
      // stores configuration for components in all sections.
      $items->set(0, $section_storage->getTranslatedConfiguration());
    }
    else {
      throw new \LogicException("Widget used with unexpected field, $field_name for section storage: " . $section_storage->getStorageType());
    }
  }

  /**
   * Gets the section storage.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The section storage loaded from the tempstore.
   */
  private function getSectionStorage(FormStateInterface $form_state) {
    return $form_state->getFormObject()->getSectionStorage();
  }

}
