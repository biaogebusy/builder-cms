<?php

namespace Drupal\webform_views\Plugin\views\field;

use Drupal\views\Entity\Render\EntityTranslationRenderTrait;
use Drupal\views\ResultRow;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Reasonable starting point for fields regarding source entity.
 */
trait WebformSubmissionSubmittedToTrait {

  use EntityTranslationRenderTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing, just override the parent.
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return $this->getSourceEntity($this->getView()->result[$this->getView()->row_index])->getEntityTypeId();
  }

  /**
   * Get the entity type manager service.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function getEntityTypeManager() {
    if (!$this->entityTypeManager) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }
    return $this->entityTypeManager;
  }

  /**
   * Get the entity repository service.
   *
   * @return \Drupal\Core\Entity\EntityRepositoryInterface
   *   The entity repository.
   */
  protected function getEntityRepository() {
    if (!$this->entityRepository) {
      $this->entityRepository = \Drupal::service('entity.repository');
    }
    return $this->entityRepository;
  }

  /**
   * {@inheritdoc}
   */
  protected function getLanguageManager() {
    if (!$this->languageManager) {
      $this->languageManager = \Drupal::languageManager();
    }
    return $this->languageManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getView() {
    return $this->view;
  }

  /**
   * Retrieve the "submitted to" entity from a result row.
   *
   * @param \Drupal\views\ResultRow $row
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Source entity of this submission is submitted to or NULL should it not
   *   have one
   */
  protected function getSourceEntity(ResultRow $row) {
    /** @var WebformSubmissionInterface $entity */
    $entity = $this->getEntity($row);
    return $entity->getSourceEntity();
  }

}
