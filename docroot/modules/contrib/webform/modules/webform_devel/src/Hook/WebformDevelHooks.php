<?php

namespace Drupal\webform_devel\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_devel.
 */
class WebformDevelHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_webform_help_info().
   */
  #[Hook('webform_help_info')]
  public function webformHelpInfo() {
    $help = [];
    $help['webform_devel_form_api_export'] = [
      'group' => 'forms',
      'title' => $this->t('Form API Export'),
      'content' => $this->t("The <strong>Form API export</strong> page demonstrates how a webform's elements may be used to create custom configuration forms."),
      'routes' => [
              // @see /admin/structure/webform/manage/{webform}/fapi
        'entity.webform.fapi_export_form',
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
      $handlers['form']['fapi_export'] = 'Drupal\webform_devel\Form\WebformDevelEntityFormApiExportForm';
      $handlers['form']['fapi_test'] = 'Drupal\webform_devel\Form\WebformDevelEntityFormApiTestForm';
      $entity_type->setHandlerClass('form', $handlers['form']);
    }
  }

}
