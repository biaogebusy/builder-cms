<?php

namespace Drupal\layout_library\Hook;

use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for layout_library.
 */
final class LayoutLibraryHooks {
  use StringTranslationTrait;

  public function __construct(
    private readonly ConfigInstallerInterface $configInstaller,
    private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   */
  #[Hook('entity_view_display_insert')]
  public static function entityViewDisplayInsert(LayoutEntityDisplayInterface $display) {
    layout_library_entity_view_display_update($display);
  }

  /**
   * Implements hook_ENTITY_TYPE_update().
   */
  #[Hook('entity_view_display_update')]
  public function entityViewDisplayUpdate(LayoutEntityDisplayInterface $display) {
    // This function changes config, so bail out immediately during config sync.
    if ($this->configInstaller->isSyncing()) {
      return;
    }
    if (!$display->isLayoutBuilderEnabled()) {
      return;
    }
    $entity_type = $display->getTargetEntityTypeId();
    $bundle = $display->getTargetBundle();
    $field_name = 'layout_selection';
    // If the display supports layout overrides, create an entity reference
    // field so that entities which are using layout can "import" a stored
    // layout from their library as a base for customization. If the display
    // does not support overrides, remove the field from the target entity type
    // and bundle, if it exists.
    if ($display->getThirdPartySetting('layout_library', 'enable', FALSE)) {
      // Reuse the existing field storage if possible.
      $field_storage = FieldStorageConfig::loadByName($entity_type, $field_name);
      if (!$field_storage) {
        $field_storage = FieldStorageConfig::create([
          'field_name' => $field_name,
          'entity_type' => $entity_type,
          'type' => 'entity_reference',
        ]);
        $field_storage->setSetting('target_type', 'layout');
        $field_storage->setLocked(TRUE);
        $field_storage->save();
      }
      // Create the entity reference field if it doesn't already exist.
      $field = FieldConfig::loadByName($entity_type, $bundle, $field_name);
      if (!$field) {
        $field = FieldConfig::create([
          'field_storage' => $field_storage,
          'bundle' => $bundle,
        ]);
        $field->setSetting('handler', 'layout_library');
        $field->setLabel($this->t('Layout'));
        $field->save();
      }
      $form_display = $this->entityDisplayRepository->getFormDisplay($entity_type, $bundle, 'default');
      if (!$form_display->getComponent($field_name)) {
        $form_display->setComponent($field_name, [
          'type' => $this->moduleHandler->moduleExists('options') ? 'options_select' : 'entity_reference_autocomplete',
          'region' => 'content',
        ])->save();
      }
    }
    else {
      $field_is_used = (bool) $this->entityTypeManager->getStorage($display->getEntityTypeId())->getQuery()->accessCheck(TRUE)->condition('targetEntityType', $display->getTargetEntityTypeId())->condition('bundle', $display->getTargetBundle())->condition('mode', $display->getMode(), '<>')->condition('third_party_settings.layout_library.enable', TRUE)->count()->execute();
      if (!$field_is_used && $field = FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
        $field->delete();
        // @codingStandardsIgnoreStart
        // phpcs:disable
        // @phpstan-ignore-next-line
        DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.4.0', fn() => \Drupal::service('Drupal\Core\Field\FieldPurger')->purgeBatch(10), fn() => field_purge_batch(10));
        // phpcs:enable
        // @codingStandardsIgnoreEnd
        $this->entityDisplayRepository->getFormDisplay($entity_type, $bundle, 'default')->removeComponent($field_name)->save();
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_entity_view_display_edit_form_alter')]
  public function formEntityViewDisplayEditFormAlter(array &$form, FormStateInterface $form_state) {
    $enable_library = $form_state->getFormObject()->getEntity()->getThirdPartySetting('layout_library', 'enable', FALSE);
    $form['layout']['library'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow content editors to use stored layouts'),
      '#default_value' => $enable_library,
    ];
    $form['#entity_builders']['layout_library'] = '_layout_library_entity_view_display_form_entity_builder';
  }

}
