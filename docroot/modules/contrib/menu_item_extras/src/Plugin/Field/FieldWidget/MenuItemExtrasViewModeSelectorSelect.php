<?php

namespace Drupal\menu_item_extras\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\menu_item_extras\Utility\Utility;

/**
 * Base class for the menu item extras view mode widget.
 *
 * @FieldWidget(
 *  id = "menu_item_extras_view_mode_selector_select",
 *  label = @Translation("View modes select list"),
 *  field_types = {"string"}
 * )
 */
class MenuItemExtrasViewModeSelectorSelect extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  private $entityTypeManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  private $entityDisplayRepository;

  /**
   * Constructs an MenuItemExtrasViewModeSelectorSelect object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   The entity display repository.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entityTypeManager, EntityDisplayRepositoryInterface $entityDisplayRepository) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entityTypeManager;
    $this->entityDisplayRepository = $entityDisplayRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
        $plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['third_party_settings'], $container->get('entity_type.manager'), $container->get('entity_display.repository')
    );
  }

  /**
   * Extracts from form state menu link view modes.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Array of default values for this field.
   * @param int $delta
   *   The order of this item in the array of sub-elements (0, 1, 2, etc.).
   * @param array $element
   *   A form element array containing basic properties for the widget.
   * @param array $form
   *   The form structure where widgets are being attached to. This might be a
   *   full form structure, or a sub-element of a larger form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The view modes array of menu link.
   *
   * @see \Drupal\Core\Field\WidgetInterface::formElement()
   */
  private function getFromWidgetViewModes(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $bundle = $storage['form_display']->getTargetBundle();
    $entity_type = $storage['form_display']->getTargetEntityTypeId();
    // Get all view modes for the current bundle.
    $view_modes = $this->entityDisplayRepository->getViewModeOptionsByBundle($entity_type, $bundle);
    if (count($view_modes) === 0) {
      $view_modes['default'] = $this->t('Default');
    }
    return $view_modes;
  }

  /**
   * Checks that menu has extra fields.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Array of default values for this field.
   * @param int $delta
   *   The order of this item in the array of sub-elements (0, 1, 2, etc.).
   * @param array $element
   *   A form element array containing basic properties for the widget.
   * @param array $form
   *   The form structure where widgets are being attached to. This might be a
   *   full form structure, or a sub-element of a larger form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   If menu has extra fields return TRUE, FALSE otherwise.
   */
  private function checkFromWidgetMenuHasExtraFields(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $bundle = $storage['form_display']->getTargetBundle();
    $entity_type = $storage['form_display']->getTargetEntityTypeId();
    return Utility::checkBundleHasExtraFieldsThanEntity($entity_type, $bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $view_modes = $this->getFromWidgetViewModes($items, $delta, $element, $form, $form_state);
    $view_mode_keys = array_keys($view_modes);
    $element['value'] = $element + [
      '#type' => 'select',
      '#options' => $view_modes,
      '#default_value' => $items[$delta]->value ?: reset($view_mode_keys),
      '#access' => $this->checkFromWidgetMenuHasExtraFields($items, $delta, $element, $form, $form_state),
    ];
    return $element;
  }

}
