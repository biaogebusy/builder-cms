<?php

/**
 * @file
 * Contains install/uninstall functionality of module.
 */

/**
 * Finalize 1.x to 2.x update path.
 */
function menu_item_extras_post_update_1x_to_2x(&$sandbox) {

  $result = \Drupal::state()->get('menu_item_extras_1_to_2');
  if (!empty($result)) {

    $field_name = 'field_body';
    $display_mode = 'default';

    $link_storage = \Drupal::entityTypeManager()
      ->getStorage('menu_link_content');

    /** @var \Drupal\menu_item_extras\Service\UpdateHelper $updater */
    $updater = \Drupal::service('menu_item_extras.update');

    $field_storage = $updater->checkBodyFieldStorage(
      $field_name, 'menu_link_content', 'text_with_summary'
    );

    foreach ($result as $id => $item) {
      /** @var \Drupal\menu_link_content\MenuLinkContentInterface $entity */
      $entity = $link_storage->load($id);

      $updater->checkBodyField(
        $field_storage,
        $entity,
        $field_name,
        t('Body'),
        ['display_summary' => TRUE]
      );

      $entity->get($field_name)->setValue([
        'value' => $item->body__value,
        'format' => $item->body__format,
      ]);

      $entity->save();

      $updater->checkViewDisplay($display_mode, $entity, $field_name, [
        'label' => 'hidden',
        'type' => 'text_default',
      ]);
      $updater->checkFormDisplay($display_mode, $entity, $field_name, [
        'type' => 'text_textarea_with_summary',
      ]);

    }
    \Drupal::state()->delete('menu_item_extras_1_to_2');
  }
}
