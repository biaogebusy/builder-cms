<?php

namespace Drupal\panelizer\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\field_ui\FieldUI;
use Drupal\panelizer\LayoutBuilderMigration;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A confirmation form for migrating an entity display to Layout Builder.
 */
final class LayoutBuilderMigrationConfirmForm extends ConfirmFormBase {

  /**
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  private $entityDisplayRepository;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * LayoutBuilderMigrationConfirmForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   (optional) The current route match service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   (optional) The string translation service.
   */
  public function __construct(EntityDisplayRepositoryInterface $entity_display_repository, EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match = NULL, TranslationInterface $translation = NULL) {
    $this->entityDisplayRepository = $entity_display_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
    if ($translation) {
      $this->setStringTranslation($translation);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_display.repository'),
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('string_translation')
    );
  }

  /**
   * Determines access to the form.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access() {
    $entity_type_id = $this->getDisplay()->getTargetEntityTypeId();
    return AccessResult::allowedIfHasPermission($this->currentUser(), "administer $entity_type_id display");
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'panelizer_layout_builder_migration_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirectUrl($this->getCancelUrl());

    $display = $this->getDisplay();
    $batch = LayoutBuilderMigration::fromDisplay($display)->toArray();
    batch_set($batch);
  }

  /**
   * Returns the entity view display being migrated.
   *
   * @return \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface
   *   The entity view display being migrated.
   */
  private function getDisplay() {
    $route_match = $this->getRouteMatch();

    return $this->entityDisplayRepository->getViewDisplay(
      $route_match->getParameter('entity_type_id'),
      $route_match->getParameter('bundle'),
      $route_match->getParameter('view_mode')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $display = $this->getDisplay();
    $entity_type_id = $display->getTargetEntityTypeId();
    $parameters = FieldUI::getRouteBundleParameter(
      $this->entityTypeManager->getDefinition($entity_type_id),
      $display->getTargetBundle()
    );
    $parameters['view_mode_name'] = $display->getMode();
    return Url::fromRoute("entity.entity_view_display.{$entity_type_id}.view_mode", $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to migrate this entity display to Layout Builder?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $description = '';
    $description .= '<p>' . $this->t('Hold your horses, cowpoke. <strong>This migration has <em>NOT</em> been thoroughly tested</strong>, and it will modify both the content and configuration of your site. That means <strong>there is a risk of data loss!</strong>') . '</p>';
    $description .= '<p>' . $this->t('If you choose to proceed, it is <strong><em>strongly recommended that you back up</em></strong> your database and configuration first, in case things go wrong and you need to restore everything to a pre-migration state. You should also thoroughly examine your site after the migration to ensure everything looks and works the way you expect it to.') . '</p>';
    $description .= '<p>' . $this->t('If you discover problems, please <a href=":url">post an issue in the Panelizer issue queue</a> describing what went awry, in much detail as possible. That will help us fix it and make this migration better for everyone.', [
      ':url' => 'https://www.drupal.org/node/add/project-issue/panelizer',
    ]) . '</p>';
    $description .= '<p>' . $this->t('You <strong>cannot undo this operation!</strong>') . '</p>';
    return $description;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('I understand the risks and have backed up my database. Proceed!');
  }

}
