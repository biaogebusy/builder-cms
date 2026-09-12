<?php

namespace Drupal\commerce_order\Plugin\views\area;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsArea;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Drupal\views\Plugin\views\argument\NumericArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an order total area handler.
 *
 * Shows the order total field with its components listed in the footer of a
 * View.
 *
 * @ingroup views_area_handlers
 */
#[ViewsArea("commerce_order_total")]
class OrderTotal extends AreaPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

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
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['empty']['#description'] = $this->t("Even if selected, this area handler will never render if a valid order cannot be found in the View's arguments.");
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    if (!$empty || !empty($this->options['empty'])) {
      /** @var \Drupal\commerce_order\OrderStorageInterface $order_storage */
      $order_storage = $this->entityTypeManager->getStorage('commerce_order');
      foreach ($this->view->argument as $name => $argument) {
        if (!$argument instanceof NumericArgument) {
          continue;
        }
        $field_name_parts = explode('.', $argument->getField());
        $order_id = NULL;

        // The field is an order item ID, or list of order item IDs, so get the
        // order ID from it.
        if (!empty($field_name_parts[1]) && $field_name_parts[1] == 'order_item_id') {
          $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');
          $order_item_ids = preg_split('/[+, ]+/', $argument->getValue(), -1, PREG_SPLIT_NO_EMPTY);
          $order_item = $order_item_storage->load(reset($order_item_ids));
          if ($order_item instanceof OrderItemInterface) {
            $order_id = $order_item->getOrderId();
          }
        }

        // The field is an order ID.
        elseif (!empty($field_name_parts[1]) && $field_name_parts[1] == 'order_id') {
          $order_id = $argument->getValue();
        }

        // If the order ID was found, make sure the order can be loaded, i.e.
        // it's a valid order ID and that the person has permission to view it.
        /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
        if ($order_id && $order = $order_storage->load($order_id)) {
          $order_total = $order->get('total_price')->view([
            'label' => 'hidden',
            'type' => 'commerce_order_total_summary',
            'weight' => $this->position,
          ]);
          $order_total['#prefix'] = '<div data-drupal-selector="order-total-summary">';
          $order_total['#suffix'] = '</div>';
          return $order_total;
        }
      }
    }

    return [];
  }

}
