<?php

namespace Drupal\entity\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityDuplicateController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new EntityDuplicateController object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder, TranslationInterface $string_translation) {
    $this->entityRepository = $entity_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('string_translation')
    );
  }

  /**
   * Builds the duplicate form.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return array
   *   The rendered form.
   */
  public function form(RouteMatchInterface $route_match) {
    $entity_type_id = $route_match->getRouteObject()->getDefault('entity_type_id');
    $source_entity = $route_match->getParameter($entity_type_id);
    $entity = $source_entity->createDuplicate();
    /** @var \Drupal\entity\Form\EntityDuplicateFormInterface $form_object */
    $form_object = $this->entityTypeManager->getFormObject($entity_type_id, 'duplicate');
    $form_object->setEntity($entity);
    $form_object->setSourceEntity($source_entity);
    $form_state = new FormState();

    return $this->formBuilder->buildForm($form_object, $form_state);
  }

  /**
   * Provides the duplicate form title.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return string
   *   The duplicate form title.
   */
  public function title(RouteMatchInterface $route_match) {
    $entity_type_id = $route_match->getRouteObject()->getDefault('entity_type_id');
    $source_entity = $route_match->getParameter($entity_type_id);
    $source_entity = $this->entityRepository->getTranslationFromContext($source_entity);

    return $this->t('Duplicate %label', ['%label' => $source_entity->label()]);
  }

}
