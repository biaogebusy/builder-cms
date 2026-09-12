<?php

namespace Drupal\webform_ui\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_ui.
 */
class WebformUiHooks {

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types) {
    if (isset($entity_types['webform'])) {
      /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $webform_entity_type */
      $webform_entity_type = $entity_types['webform'];
      // Swap the 'default' webform handler with the webform UI entity form
      // and move the old webform source entity webform to a new 'source'
      // webform handler.
      $handlers = $webform_entity_type->getHandlerClasses();
      $handlers['form']['source'] = $handlers['form']['edit'];
      $handlers['form']['edit'] = 'Drupal\webform_ui\WebformUiEntityElementsForm';
      $webform_entity_type->setHandlerClass('form', $handlers['form']);
      // Add 'source' to link template.
      $webform_entity_type->setLinkTemplate('source-form', '/admin/structure/webform/manage/{webform}/source');
    }
    if (isset($entity_types['webform_options'])) {
      /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $webform_entity_type */
      $webform_options_entity_type = $entity_types['webform_options'];
      // Swap the 'default' webform handler with the webform UI option form
      // and move the old webform option source entity webform to a new 'source'
      // webform handler.
      $handlers = $webform_options_entity_type->getHandlerClasses();
      $handlers['form']['source'] = $handlers['form']['edit'];
      $handlers['form']['add'] = 'Drupal\webform_ui\WebformUiOptionsForm';
      $handlers['form']['edit'] = 'Drupal\webform_ui\WebformUiOptionsForm';
      $handlers['form']['duplicate'] = 'Drupal\webform_ui\WebformUiOptionsForm';
      $webform_options_entity_type->setHandlerClass('form', $handlers['form']);
      // Add 'source' to link template.
      $webform_options_entity_type->setLinkTemplate('source-form', '/admin/structure/webform/manage/{webform}/source');
    }
  }

}
