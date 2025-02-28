<?php

namespace Drupal\commerce_payment\Plugin\views\area;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Drupal\views\Plugin\views\argument\NumericArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an area handler that outputs the payment total summary for an order.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("commerce_payment_total_summary")
 */
class PaymentTotalSummary extends AreaPluginBase {

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
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['empty']['#description'] = $this->t("Even if selected, this area handler will never render if a valid order cannot be found in the View's arguments.");
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    if (!$empty || !empty($this->options['empty'])) {
      $order_storage = $this->entityTypeManager->getStorage('commerce_order');
      foreach ($this->view->argument as $name => $argument) {
        // First look for an order_id argument.
        if (!$argument instanceof NumericArgument) {
          continue;
        }
        if (!in_array($argument->getField(), [
          'commerce_order.order_id',
          'commerce_order_item.order_id',
          'commerce_payment.order_id',
        ], TRUE)) {
          continue;
        }
        /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
        if ($order = $order_storage->load($argument->getValue())) {
          return [
            'payment_total_summary' => [
              '#theme' => 'commerce_payment_total_summary',
              '#order_entity' => $order,
            ],
          ];
        }
      }
    }

    return [];
  }

}
