<?php

namespace Drupal\commerce_price\Hook;

use Drupal\commerce_price\CurrencyImporterInterface;
use Drupal\commerce_price\Plugin\Field\FieldFormatter\PriceCalculatedFormatter;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\views\Plugin\views\field\EntityField;

/**
 * Hook implementations for Commerce Price.
 */
class CommercePriceHooks {

  /**
   * Constructs a new CommercePriceHooks object.
   *
   * @param \Drupal\Core\Config\ConfigInstallerInterface $configInstaller
   *   The config installer.
   * @param \Drupal\commerce_price\CurrencyImporterInterface $currencyImporter
   *   The currency importer.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(
    protected readonly ConfigInstallerInterface $configInstaller,
    protected readonly CurrencyImporterInterface $currencyImporter,
    protected readonly EntityFieldManagerInterface $entityFieldManager,
  ) {
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for 'configurable_language'.
   */
  #[Hook('configurable_language_insert')]
  public function configurableLanguageInsert(ConfigurableLanguage $language): void {
    if (!$this->configInstaller->isSyncing()) {
      // Import currency translations for the new language.
      $this->currencyImporter->importTranslations([$language->getId()]);
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * Removes the "Calculated price" formatter from views field options, if
   * it is not applicable. Since the formatter is product variation specific,
   * this prevents it from accidentally being used on other entity types.
   *
   * @todo Remove when https://www.drupal.org/node/2991660 gets fixed.
   */
  #[Hook('form_views_ui_config_item_form_alter')]
  public function formViewsUiConfigItemFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\views\Plugin\views\field\EntityField $handler */
    $handler = $form_state->get('handler');
    if ($handler instanceof EntityField && !empty($handler->definition['entity_type'])) {
      $entity_type_id = $handler->definition['entity_type'];
      $field_name = $handler->definition['field_name'] ?? NULL;
      $field_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      $field_definition = $field_definitions[$field_name] ?? NULL;
      if (!$field_definition || $field_definition->getType() != 'commerce_price') {
        return;
      }
      // Remove the formatter from configurable fields, and non-applicable ones.
      if (!($field_definition instanceof BaseFieldDefinition) || !PriceCalculatedFormatter::isApplicable($field_definition)) {
        unset($form['options']['type']['#options']['commerce_price_calculated']);
      }
    }
  }

}
