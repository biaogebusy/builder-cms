<?php

namespace Drupal\webform_rest\Plugin\rest\resource;

use Drupal\webform\WebformSubmissionForm;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a resource for retrieving webform submission data.
 *
 * @RestResource(
 *   id = "webform_rest_submission",
 *   label = @Translation("Webform Submission"),
 *   uri_paths = {
 *     "canonical" = "/webform_rest/{webform_id}/submission/{uuid}"
 *   }
 * )
 */
class WebformSubmissionResource extends ResourceBase {

  /**
   * The entity type manager object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */

  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->request = $container->get('request_stack');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * Retrieve submission data.
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

    // Load the webform submission.
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

    // Check for a submission.
    if (!empty($webform_submission)) {
      $submission_webform_id = $webform_submission->get('webform_id')->getString();

      // Check webform_id.
      if ($submission_webform_id == $webform_id) {

        // Check user have permission to delete webform submission.
        if (!$webform_submission->access('view', $this->currentUser)) {
          $errors = [
            'error' => [
              'message' => $this->t('Permisson denied'),
            ],
          ];
            return new ModifiedResourceResponse($errors, 400);
        }

        // Grab submission data.
        $data = $webform_submission->getData();

        $response = [
          'entity' => $webform_submission,
          'data' => $data,
        ];

        // Return the submission.
        return new ModifiedResourceResponse($response);
      }
    }

    throw new NotFoundHttpException(t("Can't load webform submission."));

  }

  /**
   * Update submission data.
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
  public function patch($webform_id, $uuid) {
    if (empty($webform_id) || empty($uuid)) {
      $errors = [
        'error' => [
          'message' => $this->t('Both webform ID and submission UUID are required.'),
        ],
      ];
      return new ModifiedResourceResponse($errors, 400);
    }

    $webform_data = $this->request->getCurrentRequest()->getContent();
    if (empty($webform_data)) {
      $errors = [
        'error' => [
          'message' => $this->t('No data has been submitted.'),
        ],
      ];
      return new ModifiedResourceResponse($errors, 400);
    }
    $webform_data = json_decode($webform_data, TRUE);

    // Load the webform submission.
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

    // Check for a submission.
    if (!empty($webform_submission)) {
      $submission_webform_id = $webform_submission->get('webform_id')->getString();

      // Check webform_id.
      if ($submission_webform_id == $webform_id) {

      // Check if user have permission to update a submission.
        if (!$webform_submission->access('update', $this->currentUser)) {
          $errors = [
            'error' => [
              'message' => $this->t('Permisson denied'),
            ],
          ];
          return new ModifiedResourceResponse($errors, 400);
        }

        foreach ($webform_data as $field => $value) {
          $webform_submission->setElementData($field, $value);
        }

        $errors = WebformSubmissionForm::validateWebformSubmission($webform_submission);

        // Check there are no validation errors.
        if (!empty($errors)) {
          $errors = ['error' => $errors];
          return new ModifiedResourceResponse($errors, 400);
        }
        else {
          // Return submission UUID.
          $webform_submission = WebformSubmissionForm::submitWebformSubmission($webform_submission);
        }

        // Return submission UUID.
        return new ModifiedResourceResponse(['sid' => $webform_submission->uuid()]);
      }
    }

    throw new NotFoundHttpException(t("Can't load webform submission."));
  }

  /**
   * Delete submission data.
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
  public function delete($webform_id, $uuid) {
    if (empty($webform_id) || empty($uuid)) {
      $errors = [
        'error' => [
          'message' => $this->t('Both webform ID and submission UUID are required.'),
        ],
      ];
      return new ModifiedResourceResponse($errors, 400);
    }

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
  
    // Check for a submission.
    if (!empty($webform_submission)) {
      $submission_webform_id = $webform_submission->get('webform_id')->getString();
  
      // Check webform_id.
      if ($submission_webform_id == $webform_id) {
  
        // Check user have permission to delete webform submission.
        if (!$webform_submission->access('delete', $this->currentUser)) {
          $errors = [
            'error' => [
              'message' => $this->t('Permisson denied'),
            ],
          ];
          return new ModifiedResourceResponse($errors, 400);
        }
        $webform_submission->delete();
        return new ModifiedResourceResponse(['status' => 1]);
        // The entity  can be deleted.
      }
     }
  
    throw new NotFoundHttpException(t("Can't load webform submission."));
  }

}
