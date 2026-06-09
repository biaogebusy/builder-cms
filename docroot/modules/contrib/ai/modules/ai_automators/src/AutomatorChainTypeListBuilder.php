<?php

declare(strict_types=1);

namespace Drupal\ai_automators;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of automator chain type entities.
 *
 * @see \Drupal\ai_automators\Entity\AutomatorChainType
 */
final class AutomatorChainTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row['label'] = $entity->label();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();

    $build['message'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('This is where you can add or edit Automator Chain Types, which are collections of AI Automators that can be run on your content using the AI CK Editor Integration module or with your own custom code.'),
      '#weight' => -50,
    ];

    $build['table']['#empty'] = $this->t(
      'No automator chain types available. <a href=":link">Add Automator Chain type</a>.',
      [':link' => Url::fromRoute('entity.automator_chain_type.add_form')->toString()],
    );

    return $build;
  }

}
