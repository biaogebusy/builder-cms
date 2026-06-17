<?php

namespace Drupal\layout_builder_st\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\Form\OverridesEntityForm as CoreOverridesEntityForm;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_st\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder_st\TranslationsHelperTrait;

/**
 * This class extends core class OverridesEntityForm.
 */
final class OverridesEntityForm extends CoreOverridesEntityForm {

  use TranslationsHelperTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL) {
    $form = parent::buildForm($form, $form_state, $section_storage);
    $form[OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME]['#access'] = TRUE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function init(FormStateInterface $form_state) {
    parent::init($form_state);

    $field_name = static::isTranslation($this->sectionStorage) ?
      OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME :
      OverridesSectionStorage::FIELD_NAME;
    if ($field_name === OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME) {
      $form_display = $this->getFormDisplay($form_state);
      $form_display->removeComponent(OverridesSectionStorage::FIELD_NAME);
      $form_display->setComponent($field_name, [
        'type' => 'layout_builder_widget',
        'weight' => -10,
        'settings' => [],
      ]);
      $this->setFormDisplay($form_display, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    if (static::isTranslation($this->sectionStorage)) {
      unset($actions['revert']);
    }
    return $actions;
  }

}
