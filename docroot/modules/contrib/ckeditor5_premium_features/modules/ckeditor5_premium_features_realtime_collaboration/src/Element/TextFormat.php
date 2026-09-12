<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_realtime_collaboration\Element;

use Drupal\ckeditor5_premium_features\CKeditorFieldKeyHelper;
use Drupal\ckeditor5_premium_features\Element\Ckeditor5TextFormatInterface;
use Drupal\ckeditor5_premium_features\Element\Ckeditor5TextFormatTrait;
use Drupal\ckeditor5_premium_features\Storage\EditorStorageHandlerInterface;
use Drupal\ckeditor5_premium_features\Utility\ApiAdapter;
use Drupal\ckeditor5_premium_features_cloud_services\Element\TextFormat as CloudServicesTextFormat;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Utility\CollaborationSettings;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Utility\NotificationDocumentHelper;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Utility\NotificationIntegrator;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the Text Format utility class for handling the collaboration data.
 */
class TextFormat extends CloudServicesTextFormat implements Ckeditor5TextFormatInterface {

  use Ckeditor5TextFormatTrait;

  /**
   * Creates the text format element instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\ckeditor5_premium_features_realtime_collaboration\Utility\CollaborationSettings $collaborationSettings
   *   The settings service.
   * @param \Drupal\ckeditor5_premium_features\Utility\ApiAdapter $apiAdapter
   *   The api adapter.
   * @param \Drupal\ckeditor5_premium_features\Storage\EditorStorageHandlerInterface $editorStorageHandler
   *   The editor storage handler.
   * @param \Drupal\ckeditor5_premium_features_realtime_collaboration\Utility\NotificationIntegrator $notificationIntegrator
   *   The notifications integrator.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   *
   * @throws InvalidPluginDefinitionException
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CollaborationSettings $collaborationSettings,
    protected ApiAdapter $apiAdapter,
    protected EditorStorageHandlerInterface $editorStorageHandler,
    protected NotificationIntegrator $notificationIntegrator,
    protected ModuleHandlerInterface $moduleHandler,
    protected ConfigFactoryInterface $configFactory,
    protected RequestStack $requestStack
  ) {
    parent::__construct($entityTypeManager, $editorStorageHandler);
  }

  /**
   * {@inheritdoc}
   */
  public function processElement(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    if (!$this->editorStorageHandler->hasCollaborationFeaturesEnabled($element)) {
      // Don't process as the editor does not have
      // any collaboration features enabled.
      return $element;
    }

    $this->generalProcessElement($element, $form_state, $complete_form, $this->collaborationSettings);
    parent::processElement($element, $form_state, $complete_form);
    $element_unique_id = CKeditorFieldKeyHelper::getElementUniqueId($element['#id']);
    $element_drupal_id = CKeditorFieldKeyHelper::cleanElementDrupalId($element['#id']);
    $id_attribute = 'data-' . static::STORAGE_KEY . '-element-id';
    $form_object = $form_state->getFormObject();

    if ($this->isFormTypeSupported($form_object)) {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      if (isset($element['entity_channel']['#value']) && $channel_id = $element['entity_channel']['#value']) {

        // Do not validate bundle on form submit to prevent error when text format has been modified during node edition.
        $request_method = $this->requestStack->getCurrentRequest()->getMethod();
        if ($request_method == 'GET') {
          $this->apiAdapter->validateBundleVersion($channel_id, $element['#format']);
        }
      }
    }

    if ($this->collaborationSettings->isPresenceListEnabled()) {
      $element['presence_list'] = [
        '#type' => 'container',
        '#weight' => -5,
        '#attributes' => [
          'class' => [
            'ck-presence-list-container',
          ],
          'id' => $element_drupal_id . '-value-presence-list-container',
        ],
      ];

      $element['#attached']['drupalSettings']['presenceListCollapseAt'] = $this->collaborationSettings->getPresenceListCollapseAt();
    }

    $isNotificationEnabled = FALSE;
    if ($this->moduleHandler->moduleExists('ckeditor5_premium_features_notifications')) {
      $isNotificationEnabled = TRUE;
      $default_element_keys = [
        '#type' => 'textarea',
        '#attributes' => [
        // The admin theme may vary, so this is the safest solution.
          'style' => 'display: none;',
          $id_attribute => $element_unique_id,
        ],
        '#theme_wrappers' => [],
      ];
      $element['track_changes'] = [
        '#default_value' => [],
      ] + $default_element_keys;
      $element['track_changes']['#attributes']['class'] = ['track-changes-data'];

      $element['comments'] = [
        '#default_value' => [],
      ] + $default_element_keys;
      $element['comments']['#attributes']['class'] = ['comments-data'];
    }
    $element['#attached']['drupalSettings']['ckeditor5Premium']['notificationsEnabled'] = $isNotificationEnabled;

    $form_object = $form_state->getFormObject();

    $track_changes_states = $this->editorStorageHandler->getTrackChangesStates($element, TRUE);
    $element['#attached']['drupalSettings']['ckeditor5Premium']['tracking_changes']['default_state'] = $track_changes_states;
    $element['value']['#theme'] = 'ckeditor5_textarea';

    // Remove element containing the document id before editing and set the callback to add it again after submit.
    $pattern = '/<div data-document-id="[^"]+"><\/div>/';
    $value = $element['value']['#default_value'] ?? '';
    $element['value']['#default_value'] = preg_replace($pattern, '', $value);
    self::addCallback('rtcPreSaveSubmit', [['actions', 'submit', '#submit']], $complete_form, 0, TRUE);
    self::addCallback('previewAction', [['actions', 'preview', '#submit']], $complete_form, 0, TRUE);


    $realtimeConfig = $this->configFactory->get('ckeditor5_premium_features_realtime_collaboration.settings');
    if ($form_object instanceof EntityFormInterface) {
      $realtimePermissionsEnabled = $realtimeConfig->get('realtime_permissions');
      $textFormatChangeAllowed = $realtimeConfig->get('allow_text_format_change');
      if (!$form_object->getEntity()->isNew() && ($realtimePermissionsEnabled || !$textFormatChangeAllowed)) {
        $element['format']['format']['#attributes']['disabled'] = 'disabled';
      }
    }

    $element['#element_validate'] = [[static::class, 'validateElement']];
    return $element;
  }

  /**
   * Validation function for text fields with realtime collaboration enabled.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form.
   * @param array $form
   *   The form structure.
   */
  public static function validateElement(array $element, FormStateInterface $form_state, array $form): void {
    /** @var \Drupal\ckeditor5_premium_features_realtime_collaboration\Element\TextFormat $service */
    $service = \Drupal::service('ckeditor5_premium_features_realtime_collaboration.element.text_format');

    if (!$service->configFactory->get('ckeditor5_premium_features_realtime_collaboration.settings')->get('realtime_permissions')) {
      return;
    }
    if (!$service->editorStorageHandler->hasCollaborationFeaturesEnabled($element, FALSE)) {
      return;
    }

    $channelId = $form_state->getValue([...$element["#parents"], 'entity_channel']);

    if (!$channelId) {
      return;
    }

    $response = $service->apiAdapter->exportDocument($channelId);

    if(isset($response['code'])) {
      $form_state->setError($element, 'An error occurred during document export for validation. Please check details in Drupal watchdog and contact support in case you need assistance solving the issue.');
      return;
    }

    // Set the value retrieved from cloud. This is done instead validation in
    // case other user with broader permissions made changes that are not allowed
    // for user that is saving the entity.
    $form_state->setValue([...$element["#parents"], 'value'], array_shift($response));
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
    $service = \Drupal::service('ckeditor5_premium_features_realtime_collaboration.element.text_format');
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
      $documentId = $form_state->getValue([...$item['parents'], 'entity_channel']) ?? '';
      if (empty($documentId)) {
        continue;
      }
      $suggestions = $this->apiAdapter->getDocumentSuggestions(
        $documentId, [
          'sort_by' => 'updated_at',
          'order' => 'desc',
          'limit' => 1000,
        ]
      );
      foreach ($suggestions as $suggestion) {
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
   * {@inheritdoc}
   */
  public static function process(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    /** @var \Drupal\ckeditor5_premium_features_realtime_collaboration\Element\TextFormat $service */
    $service = \Drupal::service('ckeditor5_premium_features_realtime_collaboration.element.text_format');
    return $service->processElement($element, $form_state, $complete_form);
  }

  /**
   * Callback for operations that should be handled before entity is saved.
   * It adds an empty element with document ID stored as an attribute value, which is required for collaboration tags
   * filter in order to be able to get suggestion data from cloud.
   *
   * @param array $form
   *  The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *  The form state object.
   */
  public static function rtcPreSaveSubmit(array &$form, FormStateInterface $form_state): void {
    $items = $form_state->get(static::STORAGE_KEY) ?? [];
    foreach ($items as $element_data) {
      $channelId = $form_state->getValue([
        ...$element_data['parents'],
        'entity_channel',
      ]);

      $value = $form_state->getValue([
        ...$element_data['parents'],
        'value',
      ]);
      if (!$value) {
        continue;
      }

      $value .= '<div data-document-id="' . $channelId . '"></div>';

      $form_state->setValue([
        ...$element_data['parents'],
        'value',
      ], $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function onCompleteFormSubmit(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\ckeditor5_premium_features_realtime_collaboration\Element\TextFormat $service */
    $service = \Drupal::service('ckeditor5_premium_features_realtime_collaboration.element.text_format');
    $service->completeFormSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function completeFormSubmit(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    if (!$this->isFormTypeSupported($form_object) || $form_state->isRebuilding()) {
      // Do not process anything, the entity is missing or form is rebuilding.
      return;
    }
    parent::completeFormSubmit($form, $form_state);

    $items = $form_state->get(static::STORAGE_KEY) ?? [];
    $entity = $this->getRelatedEntity($form_object);

    foreach ($items as $element_key => $element_data) {
      $entity_channel = $form_state->getValue([
        ...$element_data['parents'],
        'entity_channel',
      ]);

      if ($this->moduleHandler->moduleExists('ckeditor5_premium_features_notifications')) {
        $array_parents = $element_data['array_parents'] ?? [];

        $source_original_data = $this->getFormElementOriginalValue($form, $array_parents);
        $source_new_data = $form_state->getValue(
          [...$element_data['parents'],
            'value',
          ]
        ) ?? '';
        $changed = $element_data['changed'] ?? FALSE;

        if ($changed) {
          $commentsData = $this->getFormElementSourceData($form_state, $element_data['parents'], 'comments', $element_key);
          $this->notificationIntegrator->transformCommentsData($commentsData);

          $documentHelper = new NotificationDocumentHelper($element_key, $source_original_data, $source_new_data);

          $suggestionData = $this->apiAdapter->getDocumentSuggestions(
            $entity_channel, [
              'include_deleted' => 'true',
              'sort_by' => 'updated_at',
              'order' => 'desc',
            ]
          );

          $this->notificationIntegrator->processSuggestionGroups($suggestionData);
          $chainedSuggestions = $this->notificationIntegrator->chainSuggestion($suggestionData);

          $this->notificationIntegrator->handleDocumentUpdateEvent($entity, $documentHelper);
          $this->notificationIntegrator->handleSuggestionsEvent($entity, $documentHelper, $changed, $chainedSuggestions, $commentsData);
          $this->notificationIntegrator->handleCommentsEvent($entity, $documentHelper, $changed, $commentsData, $chainedSuggestions);
        }
      }
    }
  }

}
