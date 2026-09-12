<?php

namespace Drupal\webform_submission_log\Hook;

use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_submission_log.
 */
class WebformSubmissionLogHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_webform_help_info().
   */
  #[Hook('webform_help_info')]
  public function webformHelpInfo() {
    $help = [];
    $help['submissions_log'] = [
      'group' => 'submissions',
      'title' => $this->t('Submissions: Log'),
      'content' => $this->t('The <strong>Submissions log</strong> page tracks all submission events for all webforms that have submission logging enabled. Submission logging can be enabled globally or on a per webform basis.'),
      'routes' => [
              // @see /admin/structure/webform/results/log
        'entity.webform_submission.collection_log',
      ],
    ];
    $help['submission_log'] = [
      'group' => 'submission',
      'title' => $this->t('Submission: Log'),
      'content' => $this->t("The <strong>Log</strong> page shows all events and transactions for a submission."),
      'video_id' => 'submission',
      'routes' => [
              // @see /admin/structure/webform/manage/{webform}/submission/{webform_submission}/log
        'entity.webform_submission.log',
              // @see /node/{node}/webform/submission/{webform_submission}/log
        'entity.node.webform_submission.log',
      ],
    ];
    $help['results_log'] = [
      'group' => 'submissions',
      'title' => $this->t('Results: Log'),
      'content' => $this->t('The <strong>Results Log</strong> lists all webform submission events for the current webform.'),
      'routes' => [
              // @see /admin/structure/webform/manage/{webform}/results/log
        'entity.webform.results_log',
      ],
    ];
    $help['webform_node_results_log'] = [
      'group' => 'webform_nodes',
      'title' => $this->t('Webform Node: Results: Log'),
      'content' => $this->t('The <strong>Results Log</strong> lists all webform submission events for the current webform.'),
      'routes' => [
              // @see /node/{node}/webform/results/log
        'entity.node.webform.results_log',
      ],
    ];
    return $help;
  }

  /**
   * Implements hook_local_tasks_alter().
   */
  #[Hook('local_tasks_alter')]
  public function localTasksAlter(&$local_tasks) {
    // Remove webform node log if the webform_node.module is not installed.
    if (!\Drupal::moduleHandler()->moduleExists('webform_node')) {
      unset($local_tasks['entity.node.webform.results_log'], $local_tasks['entity.node.webform_submission.log']);
    }
  }

  /**
   * Implements hook_webform_delete().
   */
  #[Hook('webform_delete')]
  public function webformDelete(WebformInterface $webform) {
    \Drupal::database()->delete('webform_submission_log')->condition('webform_id', $webform->id())->execute();
  }

  /**
   * Implements hook_webform_submission_delete().
   */
  #[Hook('webform_submission_delete')]
  public function webformSubmissionDelete(WebformSubmissionInterface $webform_submission) {
    \Drupal::database()->delete('webform_submission_log')->condition('sid', $webform_submission->id())->execute();
  }

}
