<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_cloud_services\Element;

use Drupal\ckeditor5_premium_features\CKeditorFieldKeyHelper;
use Drupal\ckeditor5_premium_features\Element\Ckeditor5TextFormatInterface;
use Drupal\ckeditor5_premium_features\Element\Ckeditor5TextFormatTrait;
use Drupal\ckeditor5_premium_features\Storage\EditorStorageHandlerInterface;
use Drupal\ckeditor5_premium_features_cloud_services\Ckeditor5ChannelHandlingException;
use Drupal\ckeditor5_premium_features_cloud_services\Entity\Channel;
use Drupal\ckeditor5_premium_features_cloud_services\Entity\ChannelInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the Text Format utility class for cloud services or on-premise
 * server's communication - especially handling the channelId entity operations.
 */
class TextFormat implements Ckeditor5TextFormatInterface {

  use Ckeditor5TextFormatTrait;

  /**
   * Storage handler for the ckeditor5_channel entities.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $channelStorage;

  /**
   * Creates the text format element instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\ckeditor5_premium_features\Storage\EditorStorageHandlerInterface $editorStorageHandler
   *   The editor storage handler.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EditorStorageHandlerInterface $editorStorageHandler
  ) {
    $this->channelStorage = $this->entityTypeManager->getStorage(ChannelInterface::ENTITY_TYPE_ID);
  }

  /**
   * {@inheritdoc}
   */
  public function processElement(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $element_unique_id = CKeditorFieldKeyHelper::getElementUniqueId($element['#id']);
    $element_drupal_id = CKeditorFieldKeyHelper::cleanElementDrupalId($element['#id']);

    if (isset($element['entity_channel'])
        && isset($element['#attached']['drupalSettings']['ckeditor5ChannelId'][$element_drupal_id])
        && $element['entity_channel']['#value'] == $element['#attached']['drupalSettings']['ckeditor5ChannelId'][$element_drupal_id]
    ) {
      // Channel already processed.
      return $element;
    }

    $form_object = $form_state->getFormObject();

    if ($this->isFormTypeSupported($form_object)) {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $this->getRelatedEntity($form_object);
      if ($entity) {
        $entity_language = $entity->language()->getId();

        $channel_id = NestedArray::getValue(
          $form_state->getUserInput(),
          [...$element['#parents'], 'entity_channel']
        ) ?? $this->getChannelId($entity->uuid(), $element_unique_id, $entity_language);

        if (!$entity->isNew()) {
          $channel = $this->channelStorage->loadByEntity($entity, $element_unique_id);
          if (!$channel) {
            $channel = $this->handleEntityChannel($entity, $channel_id, $element_unique_id);
          }
          if ($channel instanceof ChannelInterface) {
            $channel_id = $channel->id();
          }
          else {
            throw new Ckeditor5ChannelHandlingException("Problem occurred while creating Ckeditor5 Channel Entity");
          }
        }

        $element['entity_channel'] = [
          '#type' => 'hidden',
          '#value' => $channel_id,
        ];
      }
    }
    else {
      $channel_id = $this->getChannelId(uniqid(), $element_drupal_id);
    }

    $items = $form_state->get(static::STORAGE_KEY) ?? [];
    $items[$element_unique_id] = [
      'parents' => $element['#parents'],
      'array_parents' => $element['#array_parents'],
      'changed' => $form_state->getValue('changed'),
    ];
    $form_state->set(static::STORAGE_KEY, $items);

    $element['#attached']['drupalSettings']['ckeditor5ChannelId'][$element_drupal_id] = $channel_id;

    $element['value']['#theme'] = 'ckeditor5_textarea';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function process(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    /** @var \Drupal\ckeditor5_premium_features_cloud_services\Element\TextFormat $service */
    $service = \Drupal::service('ckeditor5_premium_features_cloud_services.element.text_format');
    return $service->processElement($element, $form_state, $complete_form);
  }

  /**
   * {@inheritdoc}
   */
  public static function onCompleteFormSubmit(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\ckeditor5_premium_features_cloud_services\Element\TextFormat $service */
    $service = \Drupal::service('ckeditor5_premium_features_cloud_services.element.text_format');
    $service->completeFormSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function completeFormSubmit(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    $submitted = $form_state->get('cloud_services_submit_finished') ?? FALSE;
    if (!$this->isFormTypeSupported($form_object) || $form_state->isRebuilding() || $submitted) {
      // Do not process anything, the entity is missing or form is rebuilding
      // or already submitted (can happen in case two plugins using cloud
      // services are active).
      return;
    }
    $items = $form_state->get(static::STORAGE_KEY) ?? [];

    $order_switch = $this->detectOrderChange($form_state, $items);

    $entity = $this->getRelatedEntity($form_object);

    foreach ($items as $element_key => $element_data) {
      $entity_channel = $form_state->getValue([
        ...$element_data['parents'],
        'entity_channel',
      ]);

      if (!$entity_channel || isset($order_switch[$element_key]) && $order_switch[$element_key] === FALSE) {
        if (!$entity_channel && isset($order_switch[$element_key]) && $order_switch[$element_key] !== FALSE) {
          $order_switch[$order_switch[$element_key]] = $element_key;
          unset($order_switch[$element_key]);
        }
        $channel = $this->channelStorage->loadByEntity($entity, $element_key);
        if ($channel instanceof Channel) {
          $channel->delete();
        }
      }
    }

    foreach ($items as $element_key => $element_data) {
      $entity_channel = $form_state->getValue([
        ...$element_data['parents'],
        'entity_channel',
      ]);

      if (!$entity_channel || isset($order_switch[$element_key]) && $order_switch[$element_key] === FALSE) {
        $this->channelStorage->deleteChannels($entity, $element_key);
        continue;
      }

      $this->handleEntityChannel($entity, $entity_channel, $element_key, $order_switch[$element_key] ?? NULL);
    }

    $form_state->set('cloud_services_submit_finished', TRUE);
  }

  /**
   * Handles creating new Channel entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Referenced entity.
   * @param string $entity_channel
   *   Desired entity channel ID.
   * @param string $element_id
   *   ID of the field element.
   * @param string|null $new_element_id
   *   New element ID to overwrite the existing one.
   *
   * @return \Drupal\ckeditor5_premium_features_cloud_services\Entity\ChannelInterface|null
   *   Channel entity if exists.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function handleEntityChannel(EntityInterface $entity, string $entity_channel, string $element_id, ?string $new_element_id = NULL): ?ChannelInterface {
    $channel = $this->channelStorage->load($entity_channel);

    if (!$channel) {
      $channel = $this->channelStorage->loadByEntity($entity, $new_element_id ?? $element_id);
    }
    elseif ($channel->getKeyId() != $element_id) {
      $entity_language = $entity->language()->getId();
      $entity_channel = $this->getChannelId($entity->uuid(), $element_id, $entity_language);
      $channel = NULL;
    }

    if ($channel instanceof Channel && !empty($new_element_id) && $channel->getKeyId() !== $new_element_id) {
      $channel->setKeyId($new_element_id)->save();
    }

    if ($channel) {
      return $channel;
    }

    try {
      return $this->channelStorage->createChannel($entity, $entity_channel, $new_element_id ?? $element_id);
    }
    catch (EntityStorageException $e) {
      return $this->channelStorage->loadByEntity($entity, $element_id);
    }
  }

  /**
   * Generate unique channel ID value.
   *
   * @param string $uuid
   *   The node uuid.
   * @param string $key_id
   *   Key id of the field.
   * @param string $langcode
   *   The langcode of the entity.
   *
   * @return string
   *   The channelID.
   */
  private function getChannelId(string $uuid, string $key_id, string $langcode = ''): string {
    $base_str = $uuid . $key_id . time() . $langcode;
    return substr(Crypt::hashBase64($base_str), 0, 36);
  }

  /**
   * Returns a list of element IDs that was reordered.
   *
   * @param \Drupal\Core\Form\FormState $form_state
   *   Form state object.
   * @param array $items
   *   An array with element IDs and their parent paths.
   *
   * @return array
   *   Array containing pairs of element IDs: "before" => "after" order change.
   */
  private function detectOrderChange(FormState $form_state, array $items): array {
    $field_storage = $form_state->get('field_storage');
    $field_storage_parents = $field_storage['#parents'] ?? [];

    $change_order = [];

    foreach ($items as $item_key => $item_data) {
      $new_element_id = $this->getElementIdAfterOrderChanging($item_data['parents'], $field_storage_parents);

      if ($new_element_id === FALSE) {
        if (empty($change_order[$item_key])) {
          $change_order[$item_key] = FALSE;
        }
        else {
          $change_order[$change_order[$item_key]] = FALSE;
        }
        continue;
      }

      if ($new_element_id !== NULL && $new_element_id != $item_key && empty($change_order[$new_element_id])) {
        $change_order[$new_element_id] = $item_key;
      }
    }

    foreach ($items as $item_key => $item_data) {
      if (in_array($item_key, $change_order) && !isset($change_order[$item_key])) {
        $change_order[$item_key] = FALSE;
      }
    }

    return $change_order;
  }

  /**
   * Detects and return elements' new ID if order was changed or NULL otherwise.
   *
   * @param array $parents_path
   *   Element parents path.
   * @param array $fields_storage
   *   Form storage #fields value.
   */
  private function getElementIdAfterOrderChanging(array $parents_path, array $fields_storage): string|null|bool {
    $processed_parents = [];
    $was_modified_delta = FALSE;
    for ($current_key = 0; $current_key < count($parents_path); $current_key++) {
      $parent = $parents_path[$current_key];
      if (!isset($parents_path[$current_key + 1]) || ($parents_path[$current_key + 1] !== 0 && (int) $parents_path[$current_key + 1] == 0)) {
        $processed_parents[] = $parent;
        continue;
      }

      $current_delta = $parents_path[$current_key + 1];

      $elementParent = NestedArray::getValue($fields_storage, [
        ...$processed_parents,
        '#fields',
        $parent,
      ]);

      if (!empty($elementParent)) {
        $old_delta = $elementParent['original_deltas'][$current_delta] ?? $current_delta;
      } else {
        $old_delta = NULL;
      }

      $processed_parents[] = $parent;
      if ($old_delta === NULL) {
        if (!$was_modified_delta) {
          return FALSE;
        }
        continue;
      }

      if ($old_delta === $current_delta) {
        continue;
      }
      $was_modified_delta = TRUE;
      $processed_parents[] = $old_delta;
      ++$current_key;
    }

    if ($was_modified_delta) {
      $new_element_id = 'edit-' . implode('-', $processed_parents);
      return CKeditorFieldKeyHelper::getElementUniqueId($new_element_id);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function onValidateForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
  }

}
