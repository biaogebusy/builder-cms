<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Builder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of FormsSteps entities.
 *
 * @see \Drupal\forms_steps\Entity\FormsSteps
 */
class FormsStepsListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the contact list.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['label'] = $this->t('Label');
    $header['description'] = $this->t('Description');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\forms_steps\Entity\FormsSteps $entity */
    $row['id'] = $entity->id();
    $row['label'] = $entity->label();
    $row['description'] = $entity->getDescription();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    $operations = parent::getDefaultOperations($entity);
    $first_step = $entity->getFirstStep();

    // View action is only displayed when the forms steps has at least one step.
    if ($first_step) {
      $uri = $first_step->url();

      $operations['display'] = [
        'title' => $this->t('View'),
        'weight' => 20,
        'url' => Url::fromUri("internal:$uri"),
      ];
    }
    return $operations;
  }

}
