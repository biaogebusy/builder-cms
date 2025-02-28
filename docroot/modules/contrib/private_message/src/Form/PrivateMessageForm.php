<?php

namespace Drupal\private_message\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\private_message\Ajax\PrivateMessageInboxTriggerUpdateCommand;
use Drupal\private_message\Ajax\PrivateMessageLoadNewMessagesCommand;
use Drupal\private_message\Entity\PrivateMessage;
use Drupal\private_message\Entity\PrivateMessageThread;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\private_message\Service\PrivateMessageThreadManagerInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the private message form.
 */
class PrivateMessageForm extends ContentEntityForm {

  /**
   * A unique instance identifier for the form.
   *
   * @var int
   */
  protected $formId;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The typed data manager service.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The private message configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The private message service.
   *
   * @var \Drupal\private_message\Service\PrivateMessageServiceInterface
   */
  protected $privateMessageService;

  /**
   * The private message thread manager service.
   *
   * @var \Drupal\private_message\Service\PrivateMessageThreadManagerInterface
   */
  protected $privateMessageThreadManager;

  /**
   * The user manager service.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userManager;

  /**
   * The Form Builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a PrivateMessageForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typedDataManager
   *   The typed data manager service.
   * @param \Drupal\user\UserDataInterface $userData
   *   The user data service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\private_message\Service\PrivateMessageServiceInterface $privateMessageService
   *   The private message service.
   * @param \Drupal\private_message\Service\PrivateMessageThreadManagerInterface $privateMessageThreadManager
   *   The private message thread manager service.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The Form Builder service.
   */
  public function __construct(
    EntityRepositoryInterface $entityRepository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    TypedDataManagerInterface $typedDataManager,
    UserDataInterface $userData,
    ConfigFactoryInterface $configFactory,
    PrivateMessageServiceInterface $privateMessageService,
    PrivateMessageThreadManagerInterface $privateMessageThreadManager,
    FormBuilderInterface $formBuilder
  ) {
    parent::__construct($entityRepository, $entity_type_bundle_info, $time);
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->typedDataManager = $typedDataManager;
    $this->userData = $userData;
    $this->configFactory = $configFactory;
    $this->privateMessageService = $privateMessageService;
    $this->privateMessageThreadManager = $privateMessageThreadManager;
    $this->userManager = $entityTypeManager->getStorage('user');
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('typed_data_manager'),
      $container->get('user.data'),
      $container->get('config.factory'),
      $container->get('private_message.service'),
      $container->get('private_message.thread_manager'),
      $container->get('form_builder'),
    );
  }

  /**
   * Set the ID of the form.
   *
   * This allows for the form to be used multiple times on a page.
   *
   * @param mixed $id
   *   An ID required to be unique each time the form is called on a page.
   */
  public function setFormId($id) {
    $this->formId = Html::escape($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    $form_id = parent::getFormId();

    if ($this->formId) {
      $form_id .= '-' . $this->formId;
    }

    return $form_id;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, PrivateMessageThreadInterface $privateMessageThread = NULL) {
    $form = parent::buildForm($form, $form_state);

    if ($privateMessageThread) {
      $form_state->set('thread', $privateMessageThread);
      $form['actions']['submit']['#ajax'] = [
        'callback' => '::ajaxCallback',
      ];

      // Only to do these when using #ajax.
      $form['#attached']['library'][] = 'private_message/message_form';
      $form['#attached']['drupalSettings']['privateMessageSendKey'] = $this->configFactory->get('private_message.settings')->get('keys_send');
      $autofocus_enabled = $this->configFactory->get('private_message.settings')->get('autofocus_enable');
      if ($autofocus_enabled) {
        $form['message']['widget'][0]['#attributes']['autofocus'] = 'autofocus';
      }
    }
    else {
      // Create a dummy private message thread form so as to retrieve the
      // members element from it.
      /** @var PrivateMessageThreadInterface $private_message_thread */
      $private_message_thread = PrivateMessageThread::create();
      $form_copy = $form;
      $form_state_copy = clone($form_state);
      $form_display = EntityFormDisplay::collectRenderDisplay($private_message_thread, 'default');
      $form_display->buildForm($private_message_thread, $form_copy, $form_state_copy);
      // Copy the build information from the dummy form_state object.
      if (empty($storage['field_storage']['#parents']['#fields']['members'])) {
        $storage = $form_state_copy->getStorage();
        $copy_storage = $form_state_copy->getStorage();
        $storage['field_storage']['#parents']['#fields']['members'] = $copy_storage['field_storage']['#parents']['#fields']['members'];
        $form_state->setStorage($storage);
      }
      $form_state->set('pmt_form_display', $form_display);
      $form_state->set('pmt_entity', $private_message_thread);

      $form['members'] = $form_copy['members'];

      $form['#validate'][] = '::validateMembers';
    }

    if ($this->configFactory->get('private_message.settings')->get('hide_form_filter_tips')) {
      $form['#after_build'][] = '::afterBuild';
    }

    if ($save_label = $this->configFactory->get('private_message.settings')->get('save_message_label')) {
      $form['actions']['submit']['#value'] = $save_label;
    }

    return $form;
  }

  /**
   * Validate members that have been added to a private message thread.
   *
   * Validates that submitted members have permission to use the Private message
   * system. This validation is not added automatically, as the members field is
   * not part of the Private Message entity, but rather something that has been
   * shoehorned in from the PrivateMessageThread entity, to make for a better
   * user experience, by creating a thread and a message in a single form.
   *
   * @see \Drupal\private_message\Entity\PrivateMessageThead::baseFieldDefinitions
   */
  public function validateMembers(array &$form, FormStateInterface $formState) {
    // The members form element was loaded from the PrivateMessageThread entity
    // type. As it is not a part of the PrivateMessage entity, for which this
    // form is built, the constraints that are a part of the field on the
    // Private Message Thread are not applied. As such, the constraints need to
    // be checked manually.
    // First, get the PrivateMessageThread entity type.
    $entity_type = $this->entityTypeManager->getDefinition('private_message_thread');
    // Next, load the field definitions as defined on the entity type.
    $field_definitions = PrivateMessageThread::baseFieldDefinitions($entity_type);

    $entity = $formState->get('pmt_entity');
    $form_display = $formState->get('pmt_form_display');

    $form_display->extractFormValues($entity, $form, $formState);
    // Get the member's field, as this is the field to be validated.
    $members_field = $field_definitions['members'];

    // Retrieve any members submitted on the form.
    $members = [];
    $selectedMembers = [];
    foreach ($entity->get('members') as $user) {
      if ($user instanceof EntityReferenceItem) {
        $user = $user->entity;
      }
      $selectedMembers[] = $user;
      if ($user->isActive()) {
        $members[] = $user;
      }
    }

    if ((count($members) === 1 && $this->currentUser->id() === $members[0]->id()) || empty($members)) {
      $formState->setError($form['members'], $this->t('You can not send a message to yourself only.'));
    }

    if (count($members) <> count($selectedMembers)) {
      $formState->setError($form['members'], $this->t('You can not send a message because there are inactive users selected for this thread.'));
    }

    // Get a typed data element that can be used for validation.
    $typed_data = $this->typedDataManager->create($members_field, $members);

    // Validate the submitted members.
    $violations = $typed_data->validate();

    // Check to see if any constraint violations were found.
    if ($violations->count() > 0) {
      // Output any errors for found constraint violations.
      foreach ($violations as $violation) {
        $formState->setError($form['members'], $violation->getMessage());
      }
    }
  }

  /**
   * Ajax callback for the PrivateMessageForm.
   *
   * Re-render form after submission, so user could write new message.
   */
  public function ajaxCallback(array $form, FormStateInterface $formState) {
    $response = new AjaxResponse();

    /** @var \Drupal\private_message\Entity\PrivateMessageInterface $message */
    $message = $formState->getFormObject()->getEntity();

    // @todo move to message method
    $threads = $this->entityTypeManager
      ->getStorage('private_message_thread')
      ->loadByProperties(['private_messages' => $message->id()]);
    $thread = reset($threads);

    $private_message = PrivateMessage::create();

    /** @var \Drupal\private_message\Form\PrivateMessageForm $form_object */
    $form_object = $this->entityTypeManager
      ->getFormObject('private_message', 'add')
      ->setEntity($private_message);

    // We create form State manually so that Drupal won't fill it and
    // submit automatically.
    $new_form_state = new FormState();
    $new_form_state->addBuildInfo('args', [$thread]);
    $new_form_state->setUserInput([]);

    $new_form = $this->formBuilder
      ->buildForm($form_object, $new_form_state);

    $response->setAttachments($new_form['#attached']);

    $response->addCommand(new ReplaceCommand('.private-message-add-form', $new_form));
    $response->addCommand(new PrivateMessageLoadNewMessagesCommand());
    $response->addCommand(new PrivateMessageInboxTriggerUpdateCommand());

    return $response;
  }

  /**
   * After build callback for the Private Message Form.
   */
  public function afterBuild(array $form, FormStateInterface $formState) {
    $form['message']['widget'][0]['format']['#attributes']['class'][] = 'hidden';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $formState) {
    $status = parent::save($form, $formState);

    /** @var \Drupal\private_message\Entity\PrivateMessageThreadInterface $private_message_thread */
    $private_message_thread = $formState->get('thread');
    if (!$private_message_thread) {
      // Generate an array containing the members of the thread.
      $current_user = $this->userManager->load($this->currentUser->id());

      $members = [$current_user];
      $entity = $formState->get('pmt_entity');

      foreach ($entity->get('members') as $user) {
        if ($user instanceof EntityReferenceItem) {
          $user = $user->entity;
        }
        $members[] = $user;
      }
      // Get a private message thread containing the given users.
      $private_message_thread = $this->privateMessageService->getThreadForMembers($members);
    }

    // Save the thread.
    $this->privateMessageThreadManager->saveThread($this->entity, $private_message_thread->getMembers(), $private_message_thread);

    // Save the thread to the form state.
    $formState->set('private_message_thread', $private_message_thread);

    // Send the user to the private message page. As this thread is the newest,
    // it wll be at the top of the list.
    $formState->setRedirect('entity.private_message_thread.canonical', ['private_message_thread' => $private_message_thread->id()]);

    return $status;
  }

}
