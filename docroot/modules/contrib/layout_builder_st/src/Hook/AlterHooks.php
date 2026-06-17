<?php

namespace Drupal\layout_builder_st\Hook;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\Form\OverridesEntityForm as CoreOverridesEntityForm;
use Drupal\layout_builder_st\Element\LayoutBuilder;
use Drupal\layout_builder_st\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder_st\Form\BlockContentInlineBlockTranslateForm;
use Drupal\layout_builder_st\Form\OverridesEntityForm;
use Drupal\layout_builder_st\Plugin\Block\InlineBlock;
use Drupal\layout_builder_st\Plugin\Field\FieldWidget\LayoutBuilderWidget;
use Drupal\layout_builder_st\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Helper class to handle altering core layout builder functionality.
 */
class AlterHooks {

  public function __construct(
    protected MessengerInterface $messenger,
  ) {}

  use StringTranslationTrait;

  /**
   * Alter layout builder entity type to use this module's implementation.
   *
   * @see https://www.drupal.org/project/drupal/issues/2946333#comment-13129737
   */
  #[Hook(hook: 'entity_type_alter', order: new OrderAfter(['layout_builder']))]
  public function entityTypeAlter(array &$entity_types): void {
    // Replace entity_view_display class with our own.
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    $entity_types['entity_view_display']
      ->setClass(LayoutBuilderEntityViewDisplay::class);

    /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
    foreach ($entity_types as $entity_type) {
      if ($entity_type->entityClassImplements(FieldableEntityInterface::class)) {
        $form_class = $entity_type->getFormClass('layout_builder');
        if ($form_class !== CoreOverridesEntityForm::class) {
          $this->messenger->addError(
            $this->t('The "layout_builder" form class for @entity_type entity type is already overridden by @class. This is incompatible with the layout_builder_st module'),
            [
              '@entity_type' => $entity_type->getLabel(),
              '@class' => $form_class,
            ]
          );
        }
        $entity_type->setFormClass('layout_builder', OverridesEntityForm::class);
      }
    }

    if (isset($entity_types['block_content'])) {
      $entity_types['block_content']->setFormClass('layout_builder_translate', BlockContentInlineBlockTranslateForm::class);
    }
  }

  /**
   * Implements hook_block_alter().
   */
  #[Hook(hook: 'block_alter')]
  public function blockAlter(array &$definitions): void {
    foreach ($definitions as &$definition) {
      if ($definition['id'] === 'inline_block') {
        // Replace with our extended InlineBlock class to handle translations.
        $definition['class'] = InlineBlock::class;
      }
    }
  }

  /**
   * Implements hook_layout_builder_section_storage_alter().
   */
  #[Hook(hook: 'layout_builder_section_storage_alter')]
  public function sectionStorageAlter(array &$definitions): void {
    $definition = $definitions['overrides'];
    $definition->setClass(OverridesSectionStorage::class);
  }

  /**
   * Implements hook_field_widget_info_alter().
   */
  #[Hook(hook: 'field_widget_info_alter')]
  public function fieldWidgetInfoAlter(array &$info): void {
    if (isset($info['layout_builder_widget'])) {
      $info['layout_builder_widget']['field_types'][] = 'layout_translation';
      $info['layout_builder_widget']['class'] = LayoutBuilderWidget::class;
      $info['layout_builder_widget']['provider'] = 'layout_builder_st';
    }
  }

  /**
   * Implements hook_modules_installed().
   */
  #[Hook(hook: 'modules_installed')]
  public function modulesInstalled(array $modules): void {
    if (in_array('layout_builder_at', $modules, TRUE)) {
      $this->messenger->addError('Layout Builder Symmetric Translations is not compatible with Layout Builder Asymmetric Translations. One of these should be uninstalled');
    }
  }

  /**
   * Implements hook_element_plugin_alter().
   */
  #[Hook(hook: 'element_plugin_alter')]
  public function elementPluginAlter(array &$definitions): void {
    if (isset($definitions['layout_builder'])) {
      $definitions['layout_builder']['class'] = LayoutBuilder::class;
      $definitions['layout_builder']['provider'] = 'layout_builder_st';
    }
  }

}
