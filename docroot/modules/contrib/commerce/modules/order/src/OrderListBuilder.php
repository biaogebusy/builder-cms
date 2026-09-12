<?php

namespace Drupal\commerce_order;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\commerce_order\Entity\OrderType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the list builder for orders.
 */
class OrderListBuilder extends EntityListBuilder {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'order_id' => [
        'data' => $this->t('Order ID'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'type' => [
        'data' => $this->t('Type'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'customer' => [
        'data' => $this->t('Customer'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'state' => [
        'data' => $this->t('State'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'created' => [
        'data' => $this->t('Created'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $entity  */
    $order_type = OrderType::load($entity->bundle());
    $row = [
      'order_id' => $entity->id(),
      'type' => $order_type->label(),
      'customer' => [
        'data' => [
          '#theme' => 'username',
          '#account' => $entity->getCustomer(),
        ],
      ],
      'state' => $entity->getState()->getLabel(),
      'created' => $this->dateFormatter->format($entity->getCreatedTime(), 'short'),
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity/* , ?CacheableMetadata $cacheability = NULL */) {
    $cacheability = func_num_args() > 1 ? func_get_arg(1) : NULL;
    $operations = parent::getDefaultOperations($entity, $cacheability);

    $view_access = $entity->access('view', NULL, TRUE);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $entity */
    if ($view_access->isAllowed()) {
      $operations['view'] = [
        'title' => $this->t('View'),
        'weight' => 5,
        'url' => $entity->toUrl('canonical'),
      ];
    }
    $update_access = $entity->access('update', NULL, TRUE);
    if ($update_access->isAllowed() && $entity->hasLinkTemplate('reassign-form')) {
      $operations['reassign'] = [
        'title' => $this->t('Reassign'),
        'weight' => 20,
        'url' => $this->ensureDestination($entity->toUrl('reassign-form')),
      ];
    }
    $unlock_access = $entity->access('unlock', NULL, TRUE);
    if ($unlock_access->isAllowed()) {
      $operations['unlock'] = [
        'title' => $this->t('Unlock'),
        'weight' => 25,
        'url' => $this->ensureDestination($entity->toUrl('unlock-form')),
      ];
    }
    $resend_receipt_access = $entity->access('resend_receipt', NULL, TRUE);
    if ($resend_receipt_access->isAllowed() && $entity->hasLinkTemplate('resend-receipt-form')) {
      $operations['resend_receipt'] = [
        'title' => $this->t('Resend receipt'),
        'weight' => 20,
        'url' => $this->ensureDestination($entity->toUrl('resend-receipt-form')),
      ];
    }
    if ($cacheability instanceof CacheableMetadata) {
      $cacheability
        ->addCacheableDependency($view_access)
        ->addCacheableDependency($update_access)
        ->addCacheableDependency($unlock_access)
        ->addCacheableDependency($resend_receipt_access);
    }

    return $operations;
  }

}
