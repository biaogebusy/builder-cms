<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_merge_fields;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Entity\Editor;

class MergeFieldsProcessor {

  /**
   * @inheritDoc
   */
  public static function process(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $element['#element_validate'] = [[self::class, 'validateElement']];
    return $element;
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
  public static function validateElement(array $element, FormStateInterface $form_state, array $form): void {
    $editor = Editor::load($element['#format']);
    if (!$editor) {
      return;
    }
    if ($editor->getEditor() !== 'ckeditor5') {
      return;
    }
    $editorSettings = $editor->getSettings();
    if (empty(array_intersect($editorSettings['toolbar']['items'], ['insertMergeField', 'previewMergeFields']))) {
      return;
    }
    $entityTypeManager = \Drupal::entityTypeManager();
    $enabledItemsJSON = $editorSettings["plugins"]["ckeditor5_premium_features_merge_fields__merge_fields"]['enabled_items'] ?? '{}';
    $enabledItemsConfig = Json::decode($enabledItemsJSON);

    $enableAll = FALSE;
    if (!empty($enabledItemsConfig)) {
      // Load only enabled merge fields groups and items.
      $groupIds = [];
      $wholeGroupIds = [];
      $itemIds = [];
      foreach ($enabledItemsConfig as $group_id => $group_data) {
        if (isset($group_data['enable']) && $group_data['enable']) {
          if (empty($group_data['items']) || !is_array($group_data['items'])) {
            continue;
          }
          if (empty($group_data['items']) || !is_array($group_data['items'])) {
            continue;
          }
          if (!array_search(1, $group_data['items'])) {
            $wholeGroupIds[] = $group_id;
            $groupIds[] = $group_id;
          }
          else {
            $groupIds[] = $group_id;
            foreach ($group_data['items'] as $item_id => $enabled) {
              if ($enabled) {
                $itemIds[] = $item_id;
              }
            }
          }
        }
      }
      if (empty($wholeGroupIds) && empty($itemIds)) {
        $enableAll = TRUE;
      }
      else {
        $query = $entityTypeManager->getStorage('merge_fields_item')->getQuery();
        $additionalItemIds = $query
          ->accessCheck(FALSE)
          ->condition('group', $wholeGroupIds, 'IN')
          ->execute();
        $itemIds = array_merge($itemIds, $additionalItemIds);
        $items = $entityTypeManager->getStorage('merge_fields_item')->loadMultiple($itemIds);
      }
    }
    if ($enableAll || empty($enabledItemsConfig)) {
      // Load all merge fields groups and items.
      $items = $entityTypeManager->getStorage('merge_fields_item')->loadMultiple();
    }

    $elementValue = $form_state->getValue($element['#parents']);

    foreach ($items as $item) {
      $token = $item->getToken();
      $id = '{{' . $item->id() . '}}';
      $elementValue['value'] = str_replace($id, $token, $elementValue['value']);
    }

    $form_state->setValue($element['#parents'], $elementValue);
  }

}
