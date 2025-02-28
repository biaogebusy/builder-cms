<?php

namespace Drupal\webform_views\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\EntityLinkEdit;
use Drupal\views\ResultRow;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Field handler to present a link to a non-admin edit form of an entity.
 *
 * @ViewsField("webform_submission_user_submission_edit_field")
 */
class WebformSubmissionUserSubmissionEditField extends EntityLinkEdit {

  /**
   * {@inheritdoc}
   */
  protected function getUrlInfo(ResultRow $row) {
    /** @var WebformSubmissionInterface $webform_submission */
    $webform_submission = $this->getEntity($row);
    $webform = $webform_submission->getWebform();

    $url = Url::fromRoute('entity.webform.user.submission.edit', [
      'webform' => $webform->id(),
      'webform_submission' => $webform_submission->id(),
    ]);
    $url->setAbsolute($this->options['absolute']);

    return $url;
  }

}
