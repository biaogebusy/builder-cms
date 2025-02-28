<?php

namespace Drupal\webform_rest\Plugin\rest\resource;

use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformSubmissionForm;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a resource for submitting a webform.
 *
 * @RestResource(
 *   id = "webform_rest_submit",
 *   label = @Translation("Webform Submit"),
 *   uri_paths = {
 *     "create" = "/webform_rest/submit"
 *   }
 * )
 */
class WebformSubmitResource extends ResourceBase {

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->request = $container->get('request_stack');
    return $instance;
  }

  /**
   * Responds to entity POST requests and saves the new entity.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws HttpException in case of error.
   */
  public function post() {
    $webform_data = $this->request->getCurrentRequest()->getContent();
    if (empty($webform_data)) {
      $errors = [
        'error' => [
          'message' => 'No data has been submitted.',
        ],
      ];
      return new ModifiedResourceResponse($errors, 400);
    }
    $webform_data = json_decode($webform_data, TRUE);

    // Basic check for webform ID.
    if (empty($webform_data['webform_id'])) {
      throw new BadRequestHttpException("Missing requred webform_id value.");
    }

    // Convert to webform values format.
    $values = [
      'webform_id' => $webform_data['webform_id'],
      'entity_type' => NULL,
      'entity_id' => NULL,
      'in_draft' => FALSE,
      'uri' => '/webform/' . $webform_data['webform_id'] . '/api',
    ];

    $values['data'] = $webform_data;

    // Don't submit webform ID.
    unset($values['data']['webform_id']);

    // Check for a valid webform.
    $webform = Webform::load($values['webform_id']);
    if (!$webform) {
      throw new BadRequestHttpException('Invalid webform_id value.');
    }

    // Check webform is open.
    $is_open = WebformSubmissionForm::isOpen($webform);

    if ($is_open === TRUE) {
      // Validate submission.
      $errors = WebformSubmissionForm::validateFormValues($values);

      // Check there are no validation errors.
      if (!empty($errors)) {
        return new ModifiedResourceResponse([
          'message' => 'Submitted Data contains validation errors.',
          'error'   => $errors,
        ], 400);
      }
      else {
        // Return submission ID.
        $webform_submission = WebformSubmissionForm::submitFormValues($values);
        return new ModifiedResourceResponse(['sid' => $webform_submission->id()]);
      }
    }
    else {
      throw new AccessDeniedHttpException('This webform is closed, or too many submissions have been made.');
    }
  }

}
