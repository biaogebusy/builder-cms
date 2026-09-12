<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Element;

use Drupal\ckeditor5_premium_features\CKeditorFieldKeyHelper;
use Drupal\ckeditor5_premium_features\CollaborationAccessHandlerInterface;
use Drupal\ckeditor5_premium_features\Diff\DocumentDiffHelper;
use Drupal\ckeditor5_premium_features\Element\Ckeditor5TextFormatInterface;
use Drupal\ckeditor5_premium_features\Element\Ckeditor5TextFormatTrait;
use Drupal\ckeditor5_premium_features\Event\CollaborationEventBase;
use Drupal\ckeditor5_premium_features\Storage\EditorStorageHandlerInterface;
use Drupal\ckeditor5_premium_features_collaboration\DataProvider\UserDataProvider;
use Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationContentFilteringStorageInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityEventDispatcherInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationSuggestionDependingStorageInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\CommentInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\CommentsStorage;
use Drupal\ckeditor5_premium_features_collaboration\Entity\RevisionInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\RevisionStorage;
use Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionStorage;
use Drupal\ckeditor5_premium_features_collaboration\Utility\CollaborationSettings;
use Drupal\ckeditor5_premium_features_collaboration\Utility\RevisionsLimitHandler;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\filter\Entity\FilterFormat;
use Drupal\filter\FilterFormatInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines the Text Format utility class for handling the collaboration data.
 */
class TextFormat implements Ckeditor5TextFormatInterface {

  use Ckeditor5TextFormatTrait;
  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The suggestion storage.
   *
   * @var \Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionStorage
   */
  protected SuggestionStorage $suggestionStorage;

  /**
   * The comments storage.
   *
   * @var \Drupal\ckeditor5_premium_features_collaboration\Entity\CommentsStorage
   */
  protected CommentsStorage $commentsStorage;

  /**
   * The revision storage.
   *
   * @var \Drupal\ckeditor5_premium_features_collaboration\Entity\RevisionStorage
   */
  protected RevisionStorage $revisionStorage;

  /**
   * The array of storages operation to dispatch.
   *
   * @var array
   */
  protected array $storagesOperations;

  /**
   * The array of collaboration features storages.
   *
   * @var array
   */
  protected array $features;

  /**
   * Creates the text format element instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\ckeditor5_premium_features\Storage\EditorStorageHandlerInterface $editorStorageHandler
   *   The editor storage handler.
   * @param \Drupal\ckeditor5_premium_features_collaboration\DataProvider\UserDataProvider $userDataProvider
   *   The user data storage.
   * @param \Drupal\ckeditor5_premium_features_collaboration\Utility\CollaborationSettings $collaborationSettings
   *   Collaboration settings helper.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state object.
   * @param \Drupal\ckeditor5_premium_features\Diff\DocumentDiffHelper $documentDiffHelper
   *   Document diff helper.
   * @param \Drupal\ckeditor5_premium_features\CollaborationAccessHandler $collaborationAccessHandler
   *   Access handler.
   * @param \Drupal\ckeditor5_premium_features_collaboration\Utility\RevisionsLimitHandler $revisionsLimitHandler
   *   Revisions limit handler.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EditorStorageHandlerInterface $editorStorageHandler,
    protected UserDataProvider $userDataProvider,
    protected CollaborationSettings $collaborationSettings,
    protected EventDispatcherInterface $eventDispatcher,
    protected AccountInterface $currentUser,
    protected DocumentDiffHelper $documentDiffHelper,
    protected CollaborationAccessHandlerInterface $collaborationAccessHandler,
    protected RevisionsLimitHandler $revisionsLimitHandler
  ) {
    $this->suggestionStorage = $this->entityTypeManager->getStorage(SuggestionInterface::ENTITY_TYPE_ID);
    $this->commentsStorage = $this->entityTypeManager->getStorage(CommentInterface::ENTITY_TYPE_ID);
    $this->revisionStorage = $this->entityTypeManager->getStorage(RevisionInterface::ENTITY_TYPE_ID);
    $this->features = [
      'track_changes' => $this->suggestionStorage,
      'comments' => $this->commentsStorage,
      'revision_history' => $this->revisionStorage,
    ];
  }

  /**
   * Process the text_format form element.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form.
   * @param array $complete_form
   *   The form structure.
   *
   * @return array
   *   The element data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function processElement(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    if (!$this->editorStorageHandler->hasCollaborationFeaturesEnabled($element)) {
      // Don't process as the editor does not have
      // any collaboration features enabled.
      return $element;
    }

    $form_object = $form_state->getFormObject();

    $this->checkIfLayoutParagraphsIsUsed($element, $form_object);

    $this->generalProcessElement($element, $form_state, $complete_form, $this->collaborationSettings);

    $entity = NULL;
    if ($this->isFormTypeSupported($form_object)) {
      $entity = $this->getRelatedEntity($form_object);
    }

    $id = CKeditorFieldKeyHelper::getElementUniqueId($element['#id']);
    $id_attribute = 'data-' . static::STORAGE_KEY . '-element-id';

    $default_element_keys = [
      '#type' => 'textarea',
      '#attributes' => [
        // The admin theme may vary, so this is the safest solution.
       'style' => 'display: none;',
        $id_attribute => $id,
      ],
      '#theme_wrappers' => [],
    ];

    $storageData = $form_state->get(static::STORAGE_KEY_COLLABORATION) ?? [];

    // Setup the suggestions.
    $suggestions = $entity ? $this->suggestionStorage->loadByEntity($entity, $id) : [];

    $element['value']['#attributes'][$id_attribute] = $id;
    $element['track_changes'] = [
      '#default_value' => $storageData[$id]['track_changes'] ?? $this->suggestionStorage->serializeCollection($suggestions),
    ] + $default_element_keys;
    $element['track_changes']['#attributes']['class'] = ['track-changes-data'];

    // Setup the comments.
    $comments = $entity ? $this->commentsStorage->loadByEntity($entity, $id) : [];

    $element['comments'] = [
      '#default_value' => $storageData[$id]['comments'] ?? $this->commentsStorage->serializeCollection($comments),
    ] + $default_element_keys;
    $element['comments']['#attributes']['class'] = ['comments-data'];

    $items = $form_state->get(static::STORAGE_KEY) ?? [];
    $items[$id] = [
      'parents' => $element['#parents'],
      'array_parents' => $element['#array_parents'],
    ];
    $form_state->set(static::STORAGE_KEY, $items);

    // Setup the revision history.
    $revisions = $entity ? $this->revisionStorage->loadByEntity($entity, $id) : [];

    $element['revision_history'] = [
      '#default_value' => $storageData[$id]['revision_history'] ?? $this->revisionStorage->serializeCollection($revisions),
    ] + $default_element_keys;
    $element['revision_history']['#attributes']['class'] = ['revision-history-data'];
    $add_revision_on_submit = $this->collaborationSettings->isRevisionHistoryOnSubmit();
    $element['#attached']['drupalSettings']['ckeditor5Premium']['addRevisionOnSubmit'] = $add_revision_on_submit;

    // Set temporary field for collecting comments from resolved suggestion.
    $element['resolved_suggestions_comments'] = $default_element_keys;
    $element['resolved_suggestions_comments']['#attributes']['class'] = ['resolved-suggestions-comments-data'];

    /** @var \Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface[] $users_data */
    $users_data = array_merge($comments, $suggestions, $revisions);
    $element['#attached']['drupalSettings']['ckeditor5Premium']['users'] = $this->userDataProvider->getFromEntities($users_data);

    $track_changes_states = $this->editorStorageHandler->getTrackChangesStates($element);
    $element['#attached']['drupalSettings']['ckeditor5Premium']['tracking_changes']['default_state'] = $track_changes_states;

    $element['#attached']['drupalSettings']['ckeditor5Premium']['current_user']['editor_permission'] =
      $this->collaborationAccessHandler->getUserPermissionsForTextFormats($this->currentUser);

    $element['#element_validate'] = [[$this, 'validateElement']];
    $element['value']['#theme'] = 'ckeditor5_textarea';

    self::addCallback('previewAction', [['actions', 'preview', '#submit']], $complete_form, 0, TRUE);

    return $element;
  }

  /**
   * Loads the service in static call and executes pre preview action.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function previewAction(array &$form, FormStateInterface $form_state): void {
    $service = \Drupal::service('ckeditor5_premium_features_collaboration.element.text_format');
    $service->preparePreview($form, $form_state);
  }

  /**
   * Custom action for previewing content. Newly added suggestions won't be added to database yes, so we're storing
   * attribute suggestion data in temp storage for text filter processing.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function preparePreview(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    if (!$this->isFormTypeSupported($form_object)) {
      // Do not process anything, the entity is missing.
      return;
    }
    $items = $form_state->get(static::STORAGE_KEY) ?? [];
    $storageData = [];
    foreach ($items as $item_key => $item) {
      $key = 'track_changes';
      $source_data = $this->getFormElementSourceData($form_state, $item['parents'], $key, $item_key);
      if (empty($source_data)) {
        continue;
      }
      foreach ($source_data as $suggestion) {
        if (!str_contains($suggestion['type'], 'attribute')) {
          continue;
        }
        $storageData[$suggestion['id']] = $suggestion;
      }
    }

    $store = \Drupal::service('tempstore.private')->get('ckeditor5_premium_features_collaboration');
    $store->set($form_object->getEntity()->uuid(), $storageData);
  }



  /**
   * Validate element.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form.
   * @param array $form
   *   The form.
   */
  public function validateElement(array $element, FormStateInterface $form_state, array $form): void {
    if (!$this->editorStorageHandler->hasCollaborationFeaturesEnabled($element, FALSE)) {
      return;
    }
    if (!ckeditor5_premium_features_check_htmldiff_installed()) {
      $form_state->setError($element['value'], $this->t('Field validation in collaboration features require <code>caxy/php-htmldiff</code> library to be installed'));
      return ;
    }
    $form_object = $form_state->getFormObject();
    if (!$this->isFormTypeSupported($form_object)) {
      // Do not process anything, the entity is missing.
      return;
    }
    $item_parents = $element['#parents'];
    $array_parents = $element['#array_parents'];
    $item_key = CKeditorFieldKeyHelper::getElementUniqueId($element['#id']);

    $sourceOriginalData = $this->getFormElementOriginalValue($form, $array_parents) ?? '';
    $sourceNewData = $form_state->getValue([...$item_parents, 'value']) ?? '';
    $fieldFormat = $form_state->getValue([...$item_parents, 'format']);

    $userAccess = $this->collaborationAccessHandler->getUserCollaborationAccess($this->currentUser, $fieldFormat);

    // User has full access. Skip validation.
    if ($userAccess['document_write'] && $userAccess['comment_admin']) {
      return;
    }

    // User does not have permission to make non-suggestion changes. Throw
    // error in case there are changes outside collaboration tags.
    $trackChangesData = $this->getFormElementSourceData($form_state, $item_parents, 'track_changes', $item_key);
    $isRawDocumentChanged = $this->documentDiffHelper->isRawDocumentChanged($sourceOriginalData, $sourceNewData, $trackChangesData);
    if (!$userAccess['document_write'] && $isRawDocumentChanged) {
      $form_state->setError($element, $this->t("You are not allowed to edit the %field field.", ['%field' => $element['#title']]));
      return;
    }

    // Get form comments data and original comments data. Compare their ids
    // and get a list of added and removed comments.
    $commentsData = $this->getFormElementSourceData($form_state, $item_parents, 'comments', $item_key);
    $origCommentsData = $this->commentsStorage->loadByEntity($form_object->getEntity(), $item_key);
    $commentsChanges = $this->getChangedComments($commentsData, $origCommentsData);

    // We can get document changes array and remove all comment changes,
    // so suggestion changes only will remain for further validation.
    $changes = $this->documentDiffHelper->getDocumentChanges($sourceOriginalData, $sourceNewData);
    $removedContentWithComment = 0;
    $this->removeCommentChanges($changes, $removedContentWithComment);

    if ($commentsChanges['added'] && !$userAccess['comment_admin'] && !$userAccess['comment_write']) {
      $form_state->setError($element, $this->t("You are not allowed to post collaboration comments in %field field.", ['%field' => $element['#title']]));
      return;
    }

    foreach ($commentsChanges['changed'] as $uid) {
      if ($uid != $this->currentUser->id()) {
        // @todo once editing all users comments is available in CKEditor we
        // can change condition here.
        $form_state->setError($element, $this->t("You are not allowed to edit collaboration comments in %field.", ['%field' => $element['#title']]));
        return;
      }
      elseif (!$userAccess['comment_admin'] && !$userAccess['comment_write']) {
        $form_state->setError($element, $this->t("You are not allowed to edit collaboration comments in %field.", ['%field' => $element['#title']]));
        return;
      }
    }

    if ($commentsChanges['removed'] && count($commentsChanges['removed_threads']) !== $removedContentWithComment) {
      if (!$userAccess['comment_admin'] && !$userAccess['comment_write']) {
        $form_state->setError($element, $this->t("You are not allowed to delete collaboration comments in %field.", ['%field' => $element['#title']]));
        return;
      }
      if (!$userAccess['comment_admin']) {
        foreach ($commentsChanges['removed'] as $removedComment) {
          if ($removedComment->getAuthorId() != $this->currentUser->id()) {
            $form_state->setError($element, $this->t("You are not allowed to delete other users collaboration comments in %field.", ['%field' => $element['#title']]));
            return;
          }
        }
      }
    }

    // If we're here then only suggestion changes should remain in changes
    // array, check if user has permission for suggestions.
    if (!$userAccess['document_suggestion'] && !$userAccess['document_write'] && !empty($changes)) {
      $form_state->setError($element, $this->t("You're not allowed to add collaboration suggestions in %field.", ['%field' => $element['#title']]));
      return;
    }

  }

  /**
   * Process entity form to handle collaboration data after paragraphs collapse.
   *
   * @param array $form
   *   Form to be altered.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   */
  public static function processFormWithCollaborationStorage(array &$form, FormStateInterface $form_state): void {
    $storage = $form_state->getStorage();

    if (!empty($storage[static::STORAGE_KEY_COLLABORATION])) {
      self::addCallback('onCompleteFormSubmit', [['actions', 'submit', '#submit']], $form);
    }
  }

  /**
   * Process the text_format form element.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form.
   * @param array $complete_form
   *   The form structure.
   *
   * @return array
   *   The element data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function process(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    /** @var \Drupal\ckeditor5_premium_features_collaboration\Element\TextFormat $service */
    $service = \Drupal::service('ckeditor5_premium_features_collaboration.element.text_format');
    return $service->processElement($element, $form_state, $complete_form);
  }

  /**
   * The complete form submit callback.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function onCompleteFormSubmit(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\ckeditor5_premium_features_collaboration\Element\TextFormat $service */
    $service = \Drupal::service('ckeditor5_premium_features_collaboration.element.text_format');
    $service->completeFormSubmit($form, $form_state);
  }

  /**
   * The complete form submit callback.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function completeFormSubmit(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    if (!$this->isFormTypeSupported($form_object)) {
      // Do not process anything, the entity is missing.
      return;
    }
    $items = $form_state->get(static::STORAGE_KEY) ?? [];

    $order_switch = $this->detectOrderChange($form_state, $items);
    $this->filterOrderSwitch($order_switch);

    if ($form_state->isRebuilding()) {
      $this->storeEntitiesDataInFormStorage($items, $form_state);

      return;
    }

    $entity = $this->getRelatedEntity($form_object);

    if (!$entity->uuid()) {
      return;
    }

    $sendNotifications = TRUE;

    /*
     * TODO: Notification for paragraphs entities
     */
    if ($entity instanceof Paragraph) {
      $sendNotifications = FALSE;
    }

    foreach ($items as $item_key => $item_parents) {
      $this->processTemporaryStorageRevisionData($form_state, $item_key);

      $source_original_data = $this->getFormElementOriginalValue($form, $item_parents['array_parents']);
      $source_new_data = $form_state->getValue(
        [...$item_parents['parents'],
          'value',
        ]
      ) ?? '';

      if ($sendNotifications) {
        $this->dispatchDocumentUpdateEvent($entity, $item_key, $source_original_data, $source_new_data);
      }

      $resolved_suggestions_comments = $this->getFormElementSourceData($form_state, $item_parents['parents'], 'resolved_suggestions_comments', $item_key);
      $suggestion_source_data = $this->getFormElementSourceData($form_state, $item_parents['parents'], 'track_changes', $item_key);
      $suggestion_ids = $this->suggestionStorage->getSuggestionEntityIDs($suggestion_source_data);

      $filter_format = $this->getFormElementFilterFormat($form_state, $item_parents['parents']);

      foreach ($this->features as $key => $storage) {
        $source_data = $this->getFormElementSourceData($form_state, $item_parents['parents'], $key, $item_key);
        if ($storage instanceof CommentsStorage && !empty($resolved_suggestions_comments)) {
          $source_data = array_merge($source_data, $resolved_suggestions_comments);
        }

        if (empty($source_data) && !$storage instanceof CommentsStorage) {
          continue;
        }

        if ($storage instanceof CollaborationSuggestionDependingStorageInterface) {
          $storage->setSuggestionIds($suggestion_ids);
        }
        if ($storage instanceof CollaborationContentFilteringStorageInterface
          && $filter_format instanceof FilterFormatInterface) {
          $storage->setSourceFilterFormat($filter_format);
        }

        $entities_data = $storage->processSourceData($source_data, $entity, $item_key);
        $this->doStorageOperations($entities_data, $storage, $key, $item_key, $source_original_data, $source_new_data);
      }
      if ($this->collaborationSettings->isRevisionsLimitationEnabled()) {
        $this->revisionsLimitHandler->clearRevisions($entity, $item_key);
      }
    }
    if (!empty($order_switch)) {
      $this->changeValuesOrder($order_switch, $entity);
    }
    if ($sendNotifications) {
      $this->dispatchStoragesEvents();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function onValidateForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\ckeditor5_premium_features_collaboration\Element\TextFormat $service */
    $service = \Drupal::service('ckeditor5_premium_features_collaboration.element.text_format');
    $service->validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Execute the storage commands based on the given markup data.
   *
   * @param array $entities_data
   *   The entities data collected from markup.
   * @param object $storage
   *   The related type storage.
   */
  private function doStorageOperations(array $entities_data,
                                       object $storage,
                                       string $storageKey,
                                       string $itemKey,
                                       ?string $originalContent,
                                       ?string $newContent): void {

    // Prevent saving new revision if no changes were made.
    if ($storage instanceof RevisionStorage && $originalContent === $newContent) {
      $count = count($entities_data);
      // Second to the last is new revision. Last one is an empty revision.
      unset($entities_data[$count - 2]);
    }

    if ($storage instanceof SuggestionStorage) {
      $this->processSuggestionGroups($entities_data);
    }

    $added = [];
    $updated = [];
    foreach ($entities_data as $element_data) {
      $data_entity = $storage->load($element_data['id']);
      if ($data_entity instanceof EntityInterface) {
        $updated[] = [
          'old' => clone $data_entity,
          'new' => $storage->update($data_entity, $element_data),
        ];
      }
      else {
        $added[] = $storage->add($element_data);
      }
    }
    if (!$storage instanceof CollaborationEntityEventDispatcherInterface) {
      return;
    }

    if ($storage instanceof CommentsStorage) {
      $threadIds = [];
      $added = array_filter($added, function ($comment) use (&$threadIds) {
        $uniqueThread = !in_array($comment->getThreadId(), $threadIds);
        if ($uniqueThread) {
          $threadIds[] = $comment->getThreadId();
        }
        return $uniqueThread;
      });
    }

    $this->storagesOperations[$storageKey][$itemKey]['added'] = $added;
    $this->storagesOperations[$storageKey][$itemKey]['updated'] = $updated;
    $this->storagesOperations[$storageKey][$itemKey]['original_content'] = $originalContent;
    $this->storagesOperations[$storageKey][$itemKey]['new_content'] = $newContent;
  }

  /**
   * Overrides head attribute in case of grouped suggestions as they should be sent as a single notification.
   *
   * @param array $entities_data
   *   The entities data collected from markup.
   */
  private function processSuggestionGroups(&$entities_data): void {
    $groups = [];
    // Override head value of grouped suggestions, so they'll be sent in a single notification.
    foreach ($entities_data as $key => $element_data) {
      if (isset($element_data['attributes']['groupId'])) {
        $groupId = $element_data['attributes']['groupId'];
        if (!array_key_exists($element_data['attributes']['groupId'], $groups)) {
          $groups[$groupId] = $element_data['id'];
        }
        $entities_data[$key]['attributes']['head'] = $groups[$groupId];
      }
    }
  }

  /**
   * Additional processing required for handling data stored in form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param string $element_id
   *   ID of the document field.
   */
  private function processTemporaryStorageRevisionData(FormStateInterface $form_state, string $element_id): void {
    $collaboration_storage = $form_state->get(static::STORAGE_KEY_COLLABORATION);

    if (!empty($collaboration_storage[$element_id]['revision_history'])) {
      $source_data = json_decode($collaboration_storage[$element_id]['revision_history'], TRUE);
      foreach ($source_data as &$rev_data) {
        if (empty($rev_data['creatorId'])) {
          $rev_data['attributes']['new_draft_req'] = TRUE;
        }
      }
      $collaboration_storage[$element_id]['revision_history'] = json_encode($source_data);

      $form_state->set(static::STORAGE_KEY_COLLABORATION, $collaboration_storage);
    }
  }

  /**
   * Stores collaboration entities in the form state for later processing.
   *
   * @param array $items
   *   List of document fields info.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   */
  private function storeEntitiesDataInFormStorage(array $items, FormStateInterface $form_state): void {
    $storageData = [];
    foreach ($items as $item_key => $item_parents) {
      foreach ($this->features as $key => $storage) {
        $source_data = $this->getFormElementSourceData($form_state, $item_parents['parents'], $key, $item_key);
        if (empty($source_data)) {
          continue;
        }

        $storageData[$item_key][$key] = json_encode($source_data);
      }
    }

    if (!empty($storageData)) {
      $form_state->set(static::STORAGE_KEY_COLLABORATION, $storageData);
    }
  }

  /**
   * Returns FilterFormat entity matching value in the selected field.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param array $item_parents
   *   An array describing field values location.
   */
  private function getFormElementFilterFormat(FormStateInterface $form_state, array $item_parents): ?FilterFormatInterface {
    $fieldFormat = $form_state->getValue([...$item_parents, 'format']);

    return $fieldFormat ? FilterFormat::load($fieldFormat) : NULL;
  }

  /**
   * Dispatches document update event for specified field.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Source entity.
   * @param string $key
   *   Key value for source field.
   * @param string|null $original_value
   *   Optional original document value.
   */
  protected function dispatchDocumentUpdateEvent(FieldableEntityInterface $entity,
                                                 string $key,
                                                 ?string $original_value = NULL,
                                                 ?string $new_value = NULL): void {
    $event = new CollaborationEventBase(
      $entity,
      User::load($this->currentUser->id()),
      CollaborationEventBase::DOCUMENT_UPDATED,
    );
    $event->setRelatedDocumentKey($key);
    if (!empty($original_value)) {
      $event->setOriginalContent($original_value);
    }
    if (!empty($new_value)) {
      $event->setNewContent($new_value);
    }

    $this->eventDispatcher->dispatch(
      $event,
      CollaborationEventBase::DOCUMENT_UPDATED
    );
  }

  /**
   * Detect order changes in form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $items
   *   Items.
   *
   * @return array
   *   Array of keys to swap.
   */
  private function detectOrderChange(FormStateInterface $form_state, array $items): array {
    $field_storage = $form_state->get('field_storage');
    $field_storage_parents = $field_storage['#parents'] ?? [];

    $change_order = [];

    foreach ($items as $itemKey => $field_parents) {
      $newElementId = $this->getOriginalParentsPath($field_parents['parents'], $field_storage_parents);

      if ($newElementId !== NULL && $newElementId != $itemKey) {
        $change_order[$itemKey] = $newElementId;
      }
    }

    return $change_order;
  }

  /**
   * Get orginal parent path.
   *
   * @param array $parentsPath
   *   Parent path.
   * @param array $fieldsStorage
   *   Fields storage.
   *
   * @return string|null
   *   Element id or null.
   */
  private function getOriginalParentsPath(array $parentsPath, array $fieldsStorage): ?string {
    $processedParents = [];
    $wasModifiedDelta = FALSE;
    for ($currentKey = 0; $currentKey < count($parentsPath); $currentKey++) {
      $parent = $parentsPath[$currentKey];
      if (!isset($parentsPath[$currentKey + 1]) || ($parentsPath[$currentKey + 1] !== 0 && (int) $parentsPath[$currentKey + 1] == 0)) {
        $processedParents[] = $parent;
        continue;
      }

      $currentDelta = $parentsPath[$currentKey + 1];
      $oldDelta = NestedArray::getValue(
        $fieldsStorage,
          [...array_slice($parentsPath, 0, $currentKey),
            '#fields', $parent,
            'original_deltas',
            $currentDelta,
          ]);

      $processedParents[] = $parent;
      if ($oldDelta === NULL || $oldDelta === $currentDelta) {
        continue;
      }
      $wasModifiedDelta = TRUE;
      $processedParents[] = $oldDelta;
      ++$currentKey;
    }

    if ($wasModifiedDelta) {
      $newElementId = 'edit-' . implode('-', $processedParents);
      return CKeditorFieldKeyHelper::getElementUniqueId($newElementId);
    }

    return NULL;
  }

  /**
   * Swaps key attributes in collaboration entities.
   *
   * @param array $idsToSwap
   *   Array with ids to swap.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   */
  private function changeValuesOrder(array $idsToSwap, EntityInterface $entity) {
    foreach ($idsToSwap as $firstId => $secondId) {
      foreach ($this->features as $storage) {
        $firstValues = $storage->loadByEntity($entity, $firstId);
        $secondValues = $storage->loadByEntity($entity, $secondId);
        $this->swapKeyAttribute($firstValues, $secondId);
        $this->swapKeyAttribute($secondValues, $firstId);
      }
    }
  }

  /**
   * Changes key attribute in collaboration entities.
   *
   * @param array $values
   *   Array of the collaboration entities.
   * @param string $key
   *   New key.
   */
  private function swapKeyAttribute(array $values, string $key) {
    if (!empty($values)) {
      foreach ($values as $collaborationEntity) {
        $collaborationEntity->setKey($key);
        $collaborationEntity->save();
      }
    }
  }

  /**
   * Remove duplicates from orderSwitch array.
   *
   * @param array $orderSwitch
   *   Array with ids to swap.
   */
  private function filterOrderSwitch(array &$orderSwitch) {
    $swapIds = [];
    foreach ($orderSwitch as $key => $value) {
      if (!isset($swapIds[$key]) && !isset($swapIds[$value])) {
        $swapIds[$key] = $value;
      }
    }
    $orderSwitch = $swapIds;
  }

  /**
   * Dispatch collaboration storages events.
   */
  protected function dispatchStoragesEvents(): void {
    foreach ($this->features as $key => $storage) {
      if (empty($this->storagesOperations[$key])) {
        continue;
      }
      $operations = $this->storagesOperations[$key];

      foreach ($operations as $itemOperations) {
        $originalContent = $itemOperations['original_content'] ?? '';
        $newContent = $itemOperations['new_content'] ?? '';
        $storage->setDocumentOriginalValue($originalContent);
        $storage->setDocumentNewValue($newContent);

        $added = $itemOperations['added'] ?? [];
        $updated = $itemOperations['updated'] ?? [];
        if ($added) {
          foreach ($added as $added_entity) {
            $storage->dispatchNewEntity($added_entity);
          }
        }
        if ($updated) {
          foreach ($updated as $upd_info) {
            $storage->dispatchUpdatedEntity($upd_info['old'], $upd_info['new']);
          }
        }
      }
    }
  }

  /**
   * Process document changes array and remove all that are comments inserts or
   * removals.
   *
   * @param array $changes
   *   The document changes array.
   */
  private function removeCommentChanges(array &$changes, int &$removedWithChanges): void {
    $commentStart = '/<comment-start name="[a-z0-9:]*"><\/comment-start>/';
    $commentEnd = '/<comment-end name="[a-z0-9:]*"><\/comment-end>/';
    foreach ($changes as $key => $change) {
      $removedCount = 0;
      switch ($change['action']) {
        case 'insert':
          $change['added'] = preg_replace($commentStart, '', $change['added']);
          $change['added'] = preg_replace($commentEnd, '', $change['added']);
          if (empty($change['added'])) {
            unset($changes[$key]);
          }
          break;

        case 'delete':
          $change['removed'] = preg_replace($commentStart, '', $change['removed'], -1, $removedCount);
          $change['removed'] = preg_replace($commentEnd, '', $change['removed']);
          if (empty($change['removed'])) {
            unset($changes[$key]);
          }
          else {
            $removedWithChanges += $removedCount;
          }
          break;

        case 'replace':
          $change['added'] = preg_replace($commentStart, '', $change['added']);
          $change['added'] = preg_replace($commentEnd, '', $change['added']);
          $change['removed'] = preg_replace($commentStart, '', $change['removed'], -1, $removedCount);
          $change['removed'] = preg_replace($commentEnd, '', $change['removed']);
          if ($change['added'] == $change['removed']) {
            unset($changes[$key]);
          }
          else {
            $removedWithChanges += $removedCount;
          }
          break;
      }
    }
  }

  /**
   * Get list of added comments ids and removed comment entities.
   *
   * @param array $comments
   *   Form comments data.
   * @param array $origComments
   *   Comments data associated to a specific field in an entity.
   *
   * @return array
   *   An array containing info only for added or removed comments.
   */
  private function getChangedComments(array $comments, array $origComments): array {
    $commentIds = [];
    $changedComments = [];
    foreach ($comments as $thread) {
      foreach ($thread['comments'] as $comment) {
        $commentId = $comment['commentId'];
        $commentIds[] = $commentId;

        $origComment = $origComments[$commentId] ?? NULL;
        if ($origComment && $comment['content'] != $origComment->getContent()) {
          $changedComments[$commentId] = $origComment->getAuthor()?->id();
        }
      }
    }
    $commentIds = array_flip($commentIds);

    $removedComments = array_diff_key($origComments, $commentIds);

    // Gather info about removed threads.
    $removedThreadIds = [];
    foreach ($removedComments as $removedComment) {
      $attributes = $removedComment->getAttributes();
      if ($attributes['is_reply'] === FALSE) {
        $removedThreadIds[] = $removedComment->getThreadId();
      }
    }

    return [
      'added' => array_diff_key($commentIds, $origComments),
      'changed' => $changedComments,
      'removed' => $removedComments,
      'removed_threads' => $removedThreadIds,
    ];
  }

}
