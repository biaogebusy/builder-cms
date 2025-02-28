<?php

namespace Drupal\commerce_order\Plugin\Field\FieldFormatter;

use Drupal\commerce\AjaxFormTrait;
use Drupal\commerce\EntityHelper;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\ViewEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_order_item_table' formatter.
 *
 * @FieldFormatter(
 *   id = "commerce_order_item_table",
 *   label = @Translation("Order item table"),
 *   field_types = {
 *     "entity_reference",
 *   },
 * )
 */
class OrderItemTable extends FormatterBase implements ContainerFactoryPluginInterface {

  use AjaxFormTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'view' => 'commerce_order_item_table',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];

    $view_storage = $this->entityTypeManager->getStorage('view');
    $default_view = $this->getSetting('view');
    $applicable_views = array_filter($view_storage->loadMultiple(), function (ViewEntityInterface $view) {
      return str_contains($view->get('tag'), 'commerce_order_item_table') ||
        $view->id() === $this->getSetting('view');
    });
    $elements['view'] = [
      '#type' => 'select',
      '#title' => $this->t('Order item table view'),
      '#description' => $this->t("Only views tagged with 'commerce_order_item_table' are displayed."),
      '#options' => EntityHelper::extractLabels($applicable_views),
      '#required' => TRUE,
      '#default_value' => $default_view,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $view = $this->entityTypeManager->getStorage('view')->load($this->getSetting('view'));
    $summary[] = $this->t('View: @view.', [
      '@view' => $view->label(),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $items->getEntity();
    $elements = [];
    $order_item_ids = array_column($order->get('order_items')->getValue(), 'target_id');
    $elements[0] = [
      '#type' => 'view',
      '#name' => $this->getSetting('view'),
      '#arguments' => $order_item_ids ? [implode('+', $order_item_ids)] : NULL,
      '#embed' => TRUE,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getTargetEntityTypeId() === 'commerce_order' &&  $field_definition->getName() === 'order_items';
  }

}
