<?php

namespace Drupal\commerce_product\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_product_single_variation' widget.
 */
#[FieldWidget(
  id: "commerce_product_single_variation",
  label: new TranslatableMarkup("Single variation (Product information)"),
  field_types: ["entity_reference"],
  multiple_values: TRUE,
)]
class SingleVariationWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->inlineFormManager = $container->get('plugin.manager.commerce_inline_form');
    $instance->entityDisplayRepository = $container->get('entity_display.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'form_mode' => 'default',
      'field_title_text' => 'Product information',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $entity_type_id = $this->getFieldSetting('target_type');
    $element = [];
    $element['form_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Form mode'),
      '#default_value' => $this->getSetting('form_mode'),
      '#options' => $this->entityDisplayRepository->getFormModeOptions($entity_type_id),
      '#required' => TRUE,
    ];
    $element['field_title_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field title text'),
      '#default_value' => $this->getSetting('field_title_text'),
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $form_mode = $this->getSetting('form_mode');
    $form_mode_label = $this->t('Default');

    if ($form_mode !== 'default') {
      $entity_type_id = $this->getFieldSetting('target_type');
      $form_mode = $this->entityTypeManager->getStorage('entity_form_mode')->load($entity_type_id . '.' . $form_mode);
      $form_mode_label = $form_mode->label();
    }

    $summary[] = $this->t('Form mode: @mode', ['@mode' => $form_mode_label]);
    $summary[] = $this->t('Field title: "@text"', [
      '@text' => $this->getSetting('field_title_text'),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    // Do not allow this widget to be used as a default value widget.
    if ($this->isDefaultValueWidget($form_state)) {
      return $form;
    }

    return parent::form($items, $form, $form_state, $get_delta);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $variation = !$items->isEmpty() ? $items->first()->entity : NULL;
    if (!$variation) {
      $variation = $this->entityTypeManager->getStorage('commerce_product_variation')->create([
        'type' => reset($this->getFieldSetting('handler_settings')['target_bundles']),
        'langcode' => $items->getEntity()->language()->getId(),
      ]);
    }

    $inline_form = $this->inlineFormManager->createInstance('content_entity', ['form_mode' => $this->getSetting('form_mode')], $variation);

    $element = [
      '#type' => 'details',
      '#open' => TRUE,
      // Remove the "required" cue, it's display-only and confusing.
      '#required' => FALSE,
      // Use a custom title for the widget because "Variations" doesn't make
      // sense in a single variation context.
      '#field_title' => $this->t($this->getSetting('field_title_text')),
      '#after_build' => [
        [get_class($this), 'removeTranslatabilityClue'],
      ],
    ] + $element;

    $element['entity'] = [
      '#parents' => array_merge($element['#field_parents'], [$items->getName(), 'entity']),
      '#inline_form' => $inline_form,
    ];
    $element['entity'] = $inline_form->buildInlineForm($element['entity'], $form_state);

    return $element;
  }

  /**
   * After-build callback for removing the translatability clue from the widget.
   *
   * The variations field is not translatable, to avoid different translations
   * having different references. However, that causes ContentTranslationHandler
   * to add an "(all languages)" suffix to the widget title. That suffix is
   * incorrect, since the content_entity inline form does ensure that specific
   * entity translations are being edited.
   *
   * @see ContentTranslationHandler::addTranslatabilityClue()
   */
  public static function removeTranslatabilityClue(array $element, FormStateInterface $form_state) {
    $element['#title'] = $element['#field_title'];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    if ($this->isDefaultValueWidget($form_state)) {
      $items->filterEmptyItems();
      return;
    }

    $parents = [$this->fieldDefinition->getName(), 'widget'];
    if ($form['#type'] != 'inline_entity_form') {
      $parents = array_merge($form['#parents'], $parents);
    }
    $element = NestedArray::getValue($form, $parents);
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
    $inline_form = $element['entity']['#inline_form'];
    $values = $items->getValue();
    $values[0] = ['entity' => $inline_form->getEntity()];
    $items->setValue($values);
    $items->filterEmptyItems();
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $field_name = $field_definition->getName();
    return $entity_type == 'commerce_product' && $field_name == 'variations';
  }

}
