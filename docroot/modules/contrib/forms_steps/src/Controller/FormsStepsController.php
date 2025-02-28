<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\forms_steps\Entity\Workflow;
use Drupal\forms_steps\Exception\AccessDeniedException;
use Drupal\forms_steps\Exception\FormsStepsNotFoundException;
use Drupal\user\Access\RegisterAccessCheck;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Main controller for the Forms Steps entities behavior.
 *
 * @package Drupal\forms_steps\Controller
 */
class FormsStepsController extends ControllerBase {

  /**
   * User register access check service.
   *
   * @var \Drupal\user\Access\RegisterAccessCheck
   */
  private RegisterAccessCheck $registerAccessCheck;

  /**
   * Entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  private EntityDisplayRepository $entityDisplayRepository;

  /**
   * Form steps controller construct for dependencies injection.
   */
  public function __construct(
    EntityDisplayRepository $entityDisplayRepository,
    RegisterAccessCheck $registerAccessCheck
  ) {
    $this->entityDisplayRepository = $entityDisplayRepository;
    $this->registerAccessCheck = $registerAccessCheck;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): FormsStepsController {
    return new static(
      $container->get('entity_display.repository'),
      $container->get('access_check.user.register')
    );
  }

  /**
   * Display the step form.
   *
   * @param string $forms_steps
   *   Forms Steps id to display step from.
   * @param mixed $step
   *   Step to display.
   * @param string $instance_id
   *   Instance id of the forms steps ref to load.
   *
   * @return array
   *   Form that match the input parameters.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\forms_steps\Exception\AccessDeniedException
   * @throws \Drupal\forms_steps\Exception\FormsStepsNotFoundException
   */
  public function step(string $forms_steps, $step, string $instance_id = NULL): array {
    return $this->getForm($forms_steps, $step, $instance_id);
  }

  /**
   * Get a form based on the $step and $nid.
   *
   * If $nid is empty or not existing we provide a create form, we edit
   * otherwise.
   *
   * @param string $forms_steps
   *   Forms Steps id to get the form from.
   * @param mixed $step
   *   Step to get the Form from.
   * @param string $instance_id
   *   Instance ID of the forms steps reference to load.
   *
   * @return array
   *   Returns the Form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\forms_steps\Exception\AccessDeniedException
   * @throws \Drupal\forms_steps\Exception\FormsStepsNotFoundException
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *
   * @todo Do we need to move it in a service?
   */
  public function getForm(string $forms_steps, $step, string $instance_id = ''): array {
    /** @var \Drupal\forms_steps\Entity\FormsSteps $formsSteps */
    $formsSteps = $this->entityTypeManager()
      ->getStorage('forms_steps')
      ->load($forms_steps);

    if (!$formsSteps->hasStep($step)) {
      // @todo Propose a better error management.
      throw new \InvalidArgumentException("The Step '$step' does not exist in forms steps '{$forms_steps}'");
    }

    $step = $formsSteps->getStep($step);

    $entity_key_type = $this->entityTypeManager()
      ->getDefinition($step->entityType())
      ->getKey('bundle');

    // We initialize the entity with its potential last revision.
    $entity = NULL;
    $entities = [];
    if (!is_null($instance_id)) {
      try {
        /** @var \Drupal\forms_steps\Entity\Workflow $entities */
        $entities = $this->entityTypeManager()
          ->getStorage(Workflow::ENTITY_TYPE)
          ->loadByProperties(['instance_id' => $instance_id]);
      }
      catch (\Exception $ex) {
      }
      if ($entities) {
        // We look for the same entity bundle.
        foreach ($entities as $_entity) {
          if (strcmp($_entity->get('entity_type')->value, $step->entityType()) == 0
            && strcmp($_entity->get('bundle')->value, $step->entityBundle()) == 0) {
            // We load the entity.
            $storage = $this->entityTypeManager()->getStorage($_entity->entity_type->value);
            $idKey = $storage->getEntityType()->getKey('id');

            if (!$storage->getEntityType()->isRevisionable()) {
              $revision = NULL;
            }
            else {
              $revision = $storage->getQuery()
                ->accessCheck()
                ->condition($idKey, $_entity->entity_id->value)
                ->latestRevision()
                ->execute();
            }

            if ($revision) {
              $rid = key($revision);
              $entity = $storage->loadRevision($rid);
            }
            else {
              $entity = $storage->load($_entity->entity_id->value);
            }
            break;
          }
        }
      }
    }

    $userRegistrationAccess = FALSE;
    if ($step->entityType() == 'user') {
      $account = User::load($this->currentUser()->id());
      $registrationAccess = $this->registerAccessCheck
        ->access($account);
      $userRegistrationAccess = $registrationAccess->isAllowed();
    }

    // If entity not found, this is a new entity to create.
    if (is_null($entity)) {
      $entity = $this->entityTypeManager()
        ->getStorage($step->entityType())
        ->create([$entity_key_type => $step->entityBundle()]);

      if ($entity) {
        if (!empty($instance_id)) {
          if (count($entities) == 0) {
            // No Forms Steps exists with that UUID - Error.
            throw new FormsStepsNotFoundException(t('No multi-step instance found.')->render());
          }
        }
        else {
          if (
            ($step->entityType() !== 'user' && !$entity->access('create')) ||
            ($step->entityType() === 'user' && !($userRegistrationAccess || $entity->access('create')))
          ) {
            throw new AccessDeniedHttpException();
          }
          elseif ($formsSteps->getFirstStep()->id() != $step->id()) {
            throw new AccessDeniedException(t('First step of the multi-step forms is required.')->render());
          }
        }
      }
    }
    else {
      if (
        ($step->entityType() !== 'user' && !$entity->access('update')) ||
        ($step->entityType() === 'user' && !($entity->access('update')))
      ) {
        throw new AccessDeniedException(t('First step of the multi-step forms is required.')->render());
      }
    }

    $formMode = preg_replace("/^{$step->entityType()}\./", '', $step->formMode());
    try {
      // We load the form.
      $form = $this->entityFormBuilder()
        ->getForm(
          $entity,
          $formMode,
          ['form_steps' => TRUE]
        );
    }
    catch (InvalidPluginDefinitionException $e) {
      $entityTypeId = $entity->getEntityTypeId();
      $formModeOptions = $this->entityDisplayRepository
        ->getFormModeOptions($entityTypeId);

      if (isset($formModeOptions[$formMode])) {
        $this->messenger()->addError(
          t(
            "Site's cache must be cleared after adding new form mode: :formMode on :entityTypeId",
            [':formMode' => $formMode, ':entityTypeId' => $entityTypeId]
          )
        );
      }
      else {
        $this->messenger()->addWarning(t(':message - The form class could not be loaded.', [':message' => $e->getMessage()]));
      }
      throw new NotFoundHttpException();
    }

    // Hiding the button following to the configuration.
    if ($step->hideDelete()) {
      unset($form['actions']['delete']);
    }
    elseif ($step->deleteLabel()) {
      $form['actions']['delete']['#title'] = t($step->deleteLabel());
    }

    // Return the form.
    return $form;
  }

}
