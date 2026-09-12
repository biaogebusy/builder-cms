<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Element;

use Drupal\ckeditor5_premium_features\CKeditorFieldKeyHelper;
use Drupal\ckeditor5_premium_features\Utility\CommonCollaborationSettingsInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_paragraphs\Contracts\ComponentFormInterface;

/**
 * Trait providing scripts with common preprocessing te input text element.
 */
trait Ckeditor5TextFormatTrait {

  /**
   * Common text element preprocessing.
   *
   * @param array $element
   *   Text element to be processed.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   Current form state object.
   * @param array $completeForm
   *   Complete form structure.
   * @param \Drupal\ckeditor5_premium_features\Utility\CommonCollaborationSettingsInterface $commonCollaborationSettings
   *   Settings object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function generalProcessElement(array &$element, FormStateInterface $formState, array &$completeForm, CommonCollaborationSettingsInterface $commonCollaborationSettings): array {

    $elementUniqueId = CKeditorFieldKeyHelper::getElementUniqueId($element['#id']);
    $elementDrupalId = CKeditorFieldKeyHelper::cleanElementDrupalId($element['#id']);
    $idAttribute = 'data-' . Ckeditor5TextFormatBaseInterface::STORAGE_KEY . '-element-id';

    $element['sidebar'] = [
      '#type' => 'container',
      '#weight' => -1,
      '#attributes' => [
        'id' => $elementDrupalId . '-value-presence-list-container',
      ],
    ];

    // Attach annotation sidebar.
    AnnotationSidebar::process($element, $commonCollaborationSettings);

    $formObject = $formState->getFormObject();

    $element['value']['#theme'] = 'ckeditor5_textarea';
    $element['value']["#attributes"]['data-ckeditorfieldid'] = $elementDrupalId;
    $element['value']["#attributes"][$idAttribute] = $elementUniqueId;

    if ($this->isFormTypeSupported($formObject)) {
      // We need to attach the submit just in case the entity was created
      // before the rtc module was enabled.
      self::addCallback('onCompleteFormSubmit', [['actions', 'submit', '#submit']], $completeForm);

      self::addCallback('onValidateForm', [['#validate']], $completeForm);
    }
    else {
      // We still need to process in order to stop our integration from
      // throwing exceptions in console, but we'll block editor toolbar buttons.
      $element['#attached']['drupalSettings']['ckeditor5Premium']['disableCollaboration'] = TRUE;
    }

    // Add the container for the revision list.
    $element['revision_history_container'] = [
      '#type' => 'container',
      '#weight' => -1,
      '#attributes' => [
        'class' => ['revision-history-container-data'],
        $idAttribute => $elementUniqueId,
      ],
      [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['editor-container'],
        ],
        [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['revision-viewer-editor'],
          ],
        ],
        [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['revision-viewer-sidebar'],
          ],
        ],
      ],
    ];

    return $element;
  }

  /**
   * Adds the callback to the form.
   *
   * @param string $callbackName
   *   Callback function name.
   * @param array $callbackKeys
   *   Callback keys.
   * @param array $form
   *   The form structure.
   * @param int $nestingCounter
   *   Nesting counter.
   * @param bool $addFirst
   *   If true, add new callback to the beginning of the callbacks array.
   */
  protected static function addCallback(string $callbackName, array $callbackKeys, array &$form, int $nestingCounter = 0, $addFirst = FALSE): void {
    $callback = [static::class, $callbackName];
    foreach ($callbackKeys as $key) {

      // Get value also checks if the key exists.
      $callbacks = NestedArray::getValue($form, $key) ?? [];
      if (!empty($callbacks)) {

        // Let's make sure that callback is set only once.
        foreach ($callbacks as $test_callback) {
          if (is_array($test_callback) && $test_callback == $callback) {
            return;
          }
        }
        if ($addFirst) {
          array_unshift($callbacks, $callback);
        }
        else {
          $callbacks[] = $callback;
        }
        NestedArray::setValue($form, $key, $callbacks);
      }
    }

    // Here we are diving in the form to find potential #submit elements that
    // are placed deeper in the form. Such case can be observed using Gin admin
    // theme, which is wrapping action bar in another container.
    foreach ($form as &$element) {
      if (!is_array($element)) {
        continue;
      }

      // Here we are checking if nesting is not too deep to prevent loops,
      // or some unexpected errors with nesting.
      if ($nestingCounter > Ckeditor5TextFormatBaseInterface::NESTING_COUNTER_LIMIT) {
        continue;
      }

      self::addCallback($callbackName, $callbackKeys, $element, $nestingCounter + 1, $addFirst);
    }
  }

  /**
   * Checks if the passed form object is supported.
   *
   * @param \Drupal\Core\Form\FormInterface $formObject
   *   Form object from the $form_state object.
   */
  private function isFormTypeSupported(FormInterface $formObject): bool {
    return ($formObject instanceof EntityFormInterface && $formObject->getEntity() instanceof FieldableEntityInterface)
        || $formObject instanceof ComponentFormInterface;
  }

  /**
   * Get related entity.
   *
   * @param \Drupal\Core\Form\FormInterface $formObject
   *   The Form object.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface|null
   *   The Entity or NULL.
   */
  private function getRelatedEntity(FormInterface $formObject): ?FieldableEntityInterface {
    if ($formObject instanceof EntityFormInterface) {
      return $formObject->getEntity();
    }
    if ($formObject instanceof ComponentFormInterface) {
      $entity = $formObject->getParagraph();
      if ($entity instanceof FieldableEntityInterface) {
        return $entity;
      }
    }
    return NULL;
  }

  /**
   * Check if LayoutParagraphs module is used.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormInterface $formObject
   *   The Form.
   */
  private function checkIfLayoutParagraphsIsUsed(array &$element, FormInterface $formObject): void {
    if ($formObject instanceof ComponentFormInterface) {
      $paragraph = $formObject->getParagraph();
      $paragraphUuid = $paragraph->uuid();
      $element['#id'] = $paragraphUuid . '-' . $element['#id'];
    }
  }

  /**
   * Returns an original value set for the element.
   *
   * @param array $form
   *   Form array.
   * @param array $item_parents
   *   Array defining path to the field.
   */
  private function getFormElementOriginalValue(array $form, array $item_parents) {
    $item_parents[] = '#default_value';

    return NestedArray::getValue($form, $item_parents);
  }

  /**
   * Returns the form element source value array.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param array $item_parents
   *   Form item parents.
   * @param string $key
   *   Type of the data stored.
   * @param string $element_id
   *   ID of the document field.
   *
   * @return array
   *   Returns a decoded array of JSON object.
   */
  private function getFormElementSourceData(FormStateInterface $form_state, array $item_parents, string $key, string $element_id): array {
    $source = $form_state->getValue([...$item_parents, $key]) ?? '';

    if (empty($source)) {
      $storageCollaborationData = $form_state->get(static::STORAGE_KEY_COLLABORATION);

      if (isset($storageCollaborationData[$element_id][$key])) {
        $source = $storageCollaborationData[$element_id][$key];
      }
    }

    return (array) json_decode($source, TRUE);
  }

}
