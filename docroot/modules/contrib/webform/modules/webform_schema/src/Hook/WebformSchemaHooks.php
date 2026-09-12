<?php

namespace Drupal\webform_schema\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_schema.
 */
class WebformSchemaHooks {
  use StringTranslationTrait;

  /**
   * @file
   * Adds a 'Schema' tab to the webform builder UI.
   */

  /**
   * Implements hook_webform_help_info().
   */
  #[Hook('webform_help_info')]
  public function webformHelpInfo() {
    $help = [];
    $help['webform_schema'] = [
      'group' => 'schema',
      'title' => $this->t('Webform Schema'),
      'content' => $this->t("The <strong>Schema</strong> page displays an overview of a webform's elements and specified data types, which can be used to map webform submissions to an external API."),
      'routes' => [
              // @see /admin/structure/webform/manage/{webform}/schema
        'entity.webform.schema_form',
      ],
    ];
    return $help;
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types) {
    if (isset($entity_types['webform'])) {
      /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type */
      $entity_type = $entity_types['webform'];
      $handlers = $entity_type->getHandlerClasses();
      $handlers['form']['schema'] = 'Drupal\webform_schema\Form\WebformSchemaEntitySchemaForm';
      $entity_type->setHandlerClass('form', $handlers['form']);
    }
  }

}
