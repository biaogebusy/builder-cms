<?php

namespace Drupal\votingapi\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for vote deletion.
 */
class VoteDeleteConfirm extends EntityDeleteForm {

  /**
   * The entity type manager to create entity queries.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new VoteDeleteConfirm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity query object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->getRedirectUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the vote?');
  }

  /**
   * Gets the message to display to the user after deleting the entity.
   *
   * @return string
   *   The translated string of the deletion message.
   */
  protected function getDeletionMessage() {
    /** @var \Drupal\votingapi\Entity\Vote $vote */
    $vote = $this->getEntity();
    $entity = $this->entityTypeManager->getStorage($vote->getVotedEntityType())->load($vote->getVotedEntityId());
    return $this->t('The vote by %user on @entity-type %label has been deleted.', [
      '%user' => $vote->getOwner()->getDisplayName(),
      '@entity-type' => $entity->getEntityType()->getSingularLabel(),
      '%label' => $entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    /** @var \Drupal\votingapi\Entity\Vote $vote */
    $vote = $this->getEntity();
    $entity = $this->entityTypeManager->getStorage($vote->getVotedEntityType())->load($vote->getVotedEntityId());
    return $this->t('You are about to delete a vote by %user on @entity-type %label. This action cannot be undone.', [
      '%user' => $vote->getOwner()->getDisplayName(),
      '@entity-type' => $entity->getEntityType()->getSingularLabel(),
      '%label' => $entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function afterBuild(array $element, FormStateInterface $form_state) {
    return $element;
  }

}
