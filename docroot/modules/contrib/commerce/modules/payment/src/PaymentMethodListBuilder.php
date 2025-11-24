<?php

namespace Drupal\commerce_payment;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the list builder for payment methods.
 */
class PaymentMethodListBuilder extends EntityListBuilder {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  protected $entitiesKey = 'payment_methods';

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->routeMatch = $container->get('current_route_match');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_payment_methods';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $user = $this->routeMatch->getParameter('user');

    $query = $this->getStorage()->getQuery()
      ->accessCheck(TRUE)
      ->condition('uid', $user->id())
      ->condition('reusable', TRUE)
      ->sort('method_id');
    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Payment method');
    $header['expires'] = $this->t('Expires');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $entity */
    $expires = $entity->getExpiresTime();

    $row['label']['data'] = [
      '#markup' => $entity->label(),
    ];
    if ($entity->bundle() == 'credit_card') {
      $icon = 'payment-method-icon--' . $entity->get('card_type')->value;
      $row['label']['data']['#prefix'] = '<span class="payment-method-icon ' . $icon . '"></span>';
    }
    if ($entity->isDefault()) {
      $row['label']['data']['#markup'] .= ' <span class="payment-method-default-indicator">' . $this->t('(Default)') . '</span>';
    }

    $row['expires']['data'] = [
      '#markup' => $expires ? date('n/Y', $expires) : $this->t('Never'),
    ];
    if ($entity->isExpired()) {
      $row['expires']['data']['#suffix'] = '<br><strong>' . $this->t('Expired') . '</strong>';
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['#attached']['library'][] = 'commerce_payment/payment_method_icons';
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOperations(EntityInterface $entity) {
    $build = parent::buildOperations($entity);
    // Replace the dropbuttons with normal links.
    unset($build['#type']);
    $build['#theme'] = 'links';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = parent::getOperations($entity);
    if (!$entity->isDefault()) {
      $operations['set_default'] = [
        'title' => $this->t('Mark as default'),
        'url' => $entity->toUrl('set-default'),
        'parameter' => $entity,
      ];
    }
    return $operations;
  }

}
