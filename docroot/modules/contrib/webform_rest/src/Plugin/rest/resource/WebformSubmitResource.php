<?php

namespace Drupal\webform_rest\Plugin\rest\resource;

use Drupal\Core\Render\RendererInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformSubmissionForm;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\webform_rest\Event\WebformSubmitReturnEvent;

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
   * The entity type manager object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */

  protected $currentUser;

  /**
   * An event dispatcher instance to use for configuration events.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->request = $container->get('request_stack');
    $instance->renderer = $container->get('renderer');
    $instance->configFactory = $container->get('config.factory');
    $instance->currentUser = $container->get('current_user');
    $instance->eventDispatcher = $container->get('event_dispatcher');
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
          'message' => $this->t('No data has been submitted.'),
        ],
      ];
      return $this->dispatchReturnEvent([], $errors, 'error', 400);
    }
    $webform_data = json_decode($webform_data, TRUE);

    // Basic check for webform ID.
    if (empty($webform_data['webform_id'])) {
      $errors = [
        'error' => [
          'message' => $this->t('Missing required webform_id value.'),
        ],
      ];
      return $this->dispatchReturnEvent([], $errors, 'error', 400);
    }

    // Convert to webform values format.
    $values = [
      'webform_id' => $webform_data['webform_id'],
      'entity_type' => NULL,
      'entity_id' => NULL,
      'uri' => $this->request->getCurrentRequest()->headers->get('referer')
    ];

    $values['data'] = $webform_data;

    // Don't submit webform ID.
    unset($values['data']['webform_id']);

    // Check for a valid webform.
    $webform = Webform::load($values['webform_id']);

    if (!$webform) {
      $errors = [
        'error' => [
          'message' => $this->t('Invalid webform_id value.'),
        ],
      ];
      return $this->dispatchReturnEvent([], $errors, 'error', 400);
    }

    //Check if webform allows drafts
    $allow_draft = $webform->getSetting('draft');
      if(isset($webform_data['draft']) && $allow_draft === 'none' && $webform_data['draft'] === TRUE){
        $errors = [
          'error' => [
            'message' => $this->t('This webform does not allow draft submissions.'),
          ],
        ];
      return $this->dispatchReturnEvent($values, $errors, 'error', 400);
    }
    
    if (isset($webform_data['draft'])) {
      $values['in_draft'] = $webform_data['draft'] !== TRUE ? FALSE : TRUE;
    }

    //Check if user have permission to submit a webform submission
    if (!$webform->access('submission_create', $this->currentUser, FALSE)) {
      $errors = [
        'error' => [
          'message' => $this->t('Permission denied.'),
        ],
      ];
      return $this->dispatchReturnEvent([], $errors, 'error', 400);
    }

    // Check webform is open.
    $is_open = WebformSubmissionForm::isOpen($webform);

    if ($is_open === TRUE) {
      // Since validateFormValues also fires submitFormValues we only ran the latter to avoid calling handlers and hooks twice
      $webform_submission = WebformSubmissionForm::submitFormValues($values);

      // Check there are no validation errors.
      if (is_array($webform_submission)) {  
        $data_error = [
          'message' => $this->t('Submitted Data contains validation errors.'),
          'error'   => $webform_submission,
        ];
        return $this->dispatchReturnEvent($values, $data_error, 'error', 400);
      }
      else {
        // Prepare response
        $response = ['sid' => $webform_submission->uuid()];
        $send_confirmation_settings = $this->configFactory->get('webform_rest.settings')->get('confirmation_settings');
        if($send_confirmation_settings){
          $response += [
            'confirmation_type' => $webform->getSetting('confirmation_type'),
            'confirmation_url' => $webform->getSetting('confirmation_url'),
            'confirmation_message' => $webform->getSetting('confirmation_message'),
            'confirmation_title' => $webform->getSetting('confirmation_title'),
            'confirmation_attributes' => $webform->getSetting('confirmation_attributes'),
            'confirmation_back' => $webform->getSetting('confirmation_back'),
            'confirmation_back_label' => $webform->getSetting('confirmation_back_label'),
            'confirmation_back_attributes' => $webform->getSetting('confirmation_back_attributes'),
          ];
        }
        //return new ModifiedResourceResponse($response);
        return $this->dispatchReturnEvent($values,$response);
      }
    }
    else {
      $errors = [
        'error' => [
          'message' => $this->renderer->renderPlain($is_open),
        ],
      ];
      return $this->dispatchReturnEvent($values, $errors, 'error', 400);
    }
  }

  /**
   * Dispatch event to manipulate the data and the Response return.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   */
  protected function dispatchReturnEvent(array $submissionValues, $data = NULL, string $type_name = 'success', int $http_code = 200): ModifiedResourceResponse {
    // Dispatch event
    $this->eventDispatcher->dispatch(new WebformSubmitReturnEvent($type_name, $submissionValues, $data, $http_code), WebformSubmitReturnEvent::WEBFORM_SUBMIT_RETURN);

    return new ModifiedResourceResponse($data, $http_code);
  }

}
