<?php

namespace Drupal\webform_templates\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_templates.
 */
class WebformTemplatesHooks {
  use StringTranslationTrait;

  /**
   * @file
   * Provides starter templates that can be used to create new webforms.
   */

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types) {
    if (isset($entity_types['webform_submission'])) {
      /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $webform_submission_entity_type */
      $webform_submission_entity_type = $entity_types['webform_submission'];
      $handlers = $webform_submission_entity_type->getHandlerClasses();
      $handlers['form']['preview'] = 'Drupal\webform_templates\WebformTemplatesSubmissionPreviewForm';
      $webform_submission_entity_type->setHandlerClass('form', $handlers['form']);
    }
  }

  /**
   * Implements hook_webform_help_info().
   */
  #[Hook('webform_help_info')]
  public function webformHelpInfo() {
    $help = [];
    $help['webform_templates'] = [
      'group' => 'forms',
      'title' => $this->t('Templates'),
      'content' => $this->t('The <strong>Templates</strong> page lists reusable templates that can be duplicated and customized to create new webforms.'),
      'video_id' => 'forms',
      'routes' => [
              // @see /admin/structure/webform/templates
        'entity.webform.templates',
      ],
    ];
    return $help;
  }

}
