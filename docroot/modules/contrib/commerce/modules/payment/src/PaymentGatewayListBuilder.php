<?php

namespace Drupal\commerce_payment;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the list builder for payment gateways.
 */
class PaymentGatewayListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  protected $entitiesKey = 'gateways';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_payment_gateways';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Name');
    $header['id'] = $this->t('ID');
    $header['plugin'] = $this->t('Payment gateway');
    $header['mode'] = $this->t('Mode');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $entity */
    $payment_gateway_plugin = $entity->getPlugin();
    $type = $payment_gateway_plugin->getLabel();
    $modes = $payment_gateway_plugin->getSupportedModes();
    $mode = $modes[$payment_gateway_plugin->getMode()] ?? $this->t('N/A');
    $status = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    $row['label'] = $entity->label();
    $row['id']['#markup'] = $entity->id();
    // $this->weightKey determines whether the table will be rendered as a form.
    if (!empty($this->weightKey)) {
      $row['plugin']['#markup'] = $type;
      $row['mode']['#markup'] = $mode;
      $row['status']['#markup'] = $status;
    }
    else {
      $row['plugin'] = $type;
      $row['mode'] = $mode;
      $row['status'] = $status;
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $entities = $this->load();
    // If there are less than 2 gateways, disable dragging.
    if (count($entities) <= 1) {
      unset($this->weightKey);
    }
    return parent::render();
  }

}
