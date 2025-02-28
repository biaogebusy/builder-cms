<?php

namespace Drupal\webform_rest\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\webform\Entity\Webform;

/**
 * Creates a resource for retrieving webform submission data and fields.
 *
 * @RestResource(
 *   id = "webform_rest_complete_submission",
 *   label = @Translation("Webform submission and fields"),
 *   uri_paths = {
 *     "canonical" = "/webform_rest/{webform_id}/complete_submission/{sid}"
 *   }
 * )
 */
class WebformCompleteSubmissionResource extends ResourceBase {

  /**
   * Retrieve webform fields and submission data.
   *
   * @param string $webform_id
   *   Webform ID.
   * @param int $sid
   *   Submission ID.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   HTTP response object containing webform submission.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws HttpException in case of error.
   */
  public function get($webform_id, $sid) {
    if (empty($webform_id) || empty($sid)) {
      $errors = [
        'error' => [
          'message' => 'Both webform ID and submission ID are required.',
        ],
      ];
      return new ModifiedResourceResponse($errors);
    }

    // Get webform submission results from Webform Submission Resource.
    $webformSubmissionResource = new WebformSubmissionResource($this->configuration, 'webform_rest_submission', $this->pluginDefinition, $this->serializerFormats, $this->logger);
    $webform_submission = $webformSubmissionResource->get($webform_id, $sid);
    $submissionData = $webform_submission->getResponseData();

    // Get webform fields/structure from Webform Fields Resource.
    $webformFieldsResource = new WebformFieldsResource($this->configuration, 'webform_rest_fields', $this->pluginDefinition, $this->serializerFormats, $this->logger);
    $fields = $webformFieldsResource->get($webform_id);
    $fieldsData = $fields->getResponseData();

    // Get webform entity.
    $webform = Webform::load($webform_id);

    // Get webform title.
    $title = $webform->label();
    $result = $this->buildResponse($fieldsData, $submissionData);
    return new ModifiedResourceResponse([
      'title' => $title,
      'processed_submission' => $result,
      'webform_submission' => $submissionData,
    ]);
  }

  /**
   * Create an array from the form field structure and submission.
   *
   * Fill the fields with the input values.
   *
   * @return array|null
   *   Response.
   */
  private function buildResponse($fields, $submission) {
    $result = $fields;
    foreach ($fields as $k => $v) {
      if (isset($v['#title']) && isset($v['#type'])) {
        $result[$k] = $this->buildResponse($v, $submission);
        $result[$k]['value'] = isset($submission['data'][$v['#webform_key']]) ?
            $submission['data'][$v['#webform_key']] : NULL;
      }
    }

    return $result;
  }

}
