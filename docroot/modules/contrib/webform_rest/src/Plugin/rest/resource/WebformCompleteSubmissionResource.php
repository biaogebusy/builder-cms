<?php

namespace Drupal\webform_rest\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\webform\Entity\Webform;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a resource for retrieving webform submission data and fields.
 *
 * @RestResource(
 *   id = "webform_rest_complete_submission",
 *   label = @Translation("Webform submission and fields"),
 *   uri_paths = {
 *     "canonical" = "/webform_rest/{webform_id}/complete_submission/{uuid}"
 *   }
 * )
 */
class WebformCompleteSubmissionResource extends ResourceBase {

  /**
   * The entity type manager object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * Retrieve webform fields and submission data.
   *
   * @param string $webform_id
   *   Webform ID.
   * @param string $uuid
   *   Webform Submission UUID.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   HTTP response object containing webform submission.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws HttpException in case of error.
   */
  public function get($webform_id, $uuid) {
    if (empty($webform_id) || empty($uuid)) {
      $errors = [
        'error' => [
          'message' => $this->t('Both webform ID and submission UUID are required.'),
        ],
      ];
      return new ModifiedResourceResponse($errors, 400);
    }

    // Get webform submission results from Webform Submission Resource.
    $webform_submission = $this->entityTypeManager->getStorage('webform_submission')->loadByProperties(['uuid' => $uuid]);
    if (empty($webform_submission)) {
      $errors = [
        'error' => [
          'message' => $this->t('Invalid submission UUID.'),
        ],
      ];
      return new ModifiedResourceResponse($errors, 400);
    }
    $webform_submission = reset($webform_submission);
    $submissionData = $webform_submission->getData();

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
        $result[$k]['value'] = isset($submission[$v['#webform_key']]) ?
            $submission[$v['#webform_key']] : NULL;
      }
    }

    return $result;
  }

}
