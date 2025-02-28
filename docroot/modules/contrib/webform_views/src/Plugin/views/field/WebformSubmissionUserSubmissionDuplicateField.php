<?php

namespace Drupal\webform_views\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\EntityLink;
use Drupal\views\ResultRow;

/**
 * Field handler to present a link to a non-admin duplicate form of an entity.
 *
 * @ViewsField("webform_submission_user_submission_duplicate_field")
 */
class WebformSubmissionUserSubmissionDuplicateField extends EntityLink {

  /**
   * {@inheritdoc}
   */
  protected function getUrlInfo(ResultRow $row) {
    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = $this->getEntity($row);
    $webform = $webform_submission->getWebform();

    $url = Url::fromRoute('entity.webform.user.submission.duplicate', [
      'webform' => $webform->id(),
      'webform_submission' => $webform_submission->id(),
    ]);
    $url->setAbsolute($this->options['absolute']);

    return $url;
  }

}
