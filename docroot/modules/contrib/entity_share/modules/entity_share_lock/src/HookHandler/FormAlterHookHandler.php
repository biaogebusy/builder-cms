<?php

declare(strict_types = 1);

namespace Drupal\entity_share_lock\HookHandler;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_share_client\Service\StateInformationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook handler for the form_alter() hook.
 */
class FormAlterHookHandler implements ContainerInjectionInterface {
  use StringTranslationTrait;

  /**
   * The machine name of the locked policy.
   */
  public const LOCKED_POLICY = 'locked';

  /**
   * The operations that are locked.
   */
  public const LOCKED_OPERATIONS = [
    'edit',
    'layout_builder',
  ];

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The state information service.
   *
   * @var \Drupal\entity_share_client\Service\StateInformationInterface
   */
  protected $stateInformation;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\entity_share_client\Service\StateInformationInterface $stateInformation
   *   The state information service.
   */
  public function __construct(
    MessengerInterface $messenger,
    StateInformationInterface $stateInformation
  ) {
    $this->messenger = $messenger;
    $this->stateInformation = $stateInformation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('entity_share_client.state_information')
    );
  }

  /**
   * Disable a content form depending on criteria.
   *
   * @param array $form
   *   Nested array of form elements that comprise the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. The arguments that
   *   \Drupal::formBuilder()->getForm() was originally called with are
   *   available in the array $form_state->getBuildInfo()['args'].
   * @param string $form_id
   *   String representing the name of the form itself. Typically, this is the
   *   name of the function that generated the form.
   */
  public function formAlter(array &$form, FormStateInterface $form_state, $form_id) {
    $build_info = $form_state->getBuildInfo();

    // Check if acting on a content entity form.
    if (!isset($build_info['callback_object']) || !($build_info['callback_object'] instanceof ContentEntityFormInterface)) {
      return;
    }

    $entity_form = $build_info['callback_object'];
    $operation = $entity_form->getOperation();

    // Check the operation.
    if ($operation != 'default' && !in_array($operation, $this::LOCKED_OPERATIONS)) {
      return;
    }

    $entity = $entity_form->getEntity();

    // Some content entity types (like Menu link content or Taxonomy term) do
    // not have a dedicated edit operation.
    // So check if the entity is new to determine if on an edit form.
    if ($operation == 'default' && $entity->isNew()) {
      return;
    }

    $entity_type = $entity->getEntityType();
    $entity_type_id = $entity_type->id();

    // Do not act on user.
    if ($entity_type_id == 'user') {
      return;
    }

    // If the entity type does not have a UUID it can not be imported with
    // Entity Share.
    if (!$entity_type->hasKey('uuid')) {
      return;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    /** @var \Drupal\entity_share_client\Entity\EntityImportStatusInterface $import_status */
    $import_status = $this->stateInformation->getImportStatusOfEntity($entity);

    // Check if the entity is from an import.
    if (!$import_status) {
      return;
    }

    if ($import_status->getPolicy() == $this::LOCKED_POLICY) {
      $form['#disabled'] = TRUE;
      $this->messenger->addWarning($this->t('The entity had been locked from edition because of an import policy.'));
    }
  }

}
