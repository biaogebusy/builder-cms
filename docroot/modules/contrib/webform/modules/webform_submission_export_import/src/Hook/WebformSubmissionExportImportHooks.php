<?php

namespace Drupal\webform_submission_export_import\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_submission_export_import.
 */
class WebformSubmissionExportImportHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_webform_help_info().
   */
  #[Hook('webform_help_info')]
  public function webformHelpInfo() {
    $help = [];
    $help['webform_submission_export_import'] = [
      'group' => 'forms',
      'title' => $this->t('Upload'),
      'content' => $this->t('The <strong>Upload</strong> page allows a CSV (comma separated values) file or URL to be uploaded, converted, and imported into webform submissions.'),
      'video_id' => 'import',
      'routes' => [
              // @see /admin/structure/webform/manage/{webform}/results/upload
        'entity.webform_submission_export_import.results_import',
              // @see /node/{node}/webform/results/upload
        'entity.node.webform_submission_export_import.results_import',
      ],
    ];
    return $help;
  }

  /**
   * Implements hook_local_tasks_alter().
   */
  #[Hook('local_tasks_alter')]
  public function localTasksAlter(&$local_tasks) {
    // Remove webform node results import if the webform_node.module
    // is not installed.
    if (!\Drupal::moduleHandler()->moduleExists('webform_node')) {
      unset($local_tasks['entity.node.webform_submission_export_import.results_import']);
    }
  }

}
