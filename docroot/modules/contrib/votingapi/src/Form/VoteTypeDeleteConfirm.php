<?php

namespace Drupal\votingapi\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for vote type deletion.
 */
class VoteTypeDeleteConfirm extends EntityDeleteForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a VoteTypeDeleteConfirm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
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
  public function buildForm(array $form, FormStateInterface $form_state) {
    $num_nodes = $this->entityTypeManager->getStorage('vote')->getQuery()
      ->condition('type', $this->entity->id())
      ->count()
      ->execute();
    if ($num_nodes) {
      $caption = $this->formatPlural($num_nodes, '%type is used by 1 piece of content on your site. You can not remove this vote type until you have removed that vote.', '%type is used by @count pieces of content on your site. You may not remove %type until you have removed all of the %type votes.', ['%type' => $this->entity->label()]);
      $form['#title'] = $this->getQuestion();
      $form['description'] = [
        '#prefix' => '<p>',
        '#markup' => $caption,
        '#suffix' => '</p>',
      ];

      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

}
