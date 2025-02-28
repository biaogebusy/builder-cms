<?php

namespace Drupal\xinshi_sms\Plugin\rest\resource;

use Drupal\Core\Render\RenderContext;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Annotation for OTP send.
 *
 * @RestResource(
 *   id = "xinshi_otp_generate",
 *   label = @Translation("XINSHI Generate OTP"),
 *   uri_paths = {
 *     "create" = "/api/v3/otp/generate",
 *     "https://www.drupal.org/link-relations/create" = "/api/v1/otp/generate"
 *   }
 * )
 */
class OtpGenerateResource extends ResourceBase {

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
    $instance->request = $container->get('request_stack');
    return $instance;
  }

  /**
   * Responds to POST requests.
   *
   * Returns a list of bundles for specified entity.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Throws exception expected.
   */
  public function post() {
    // Use current user after pass authentication to validate access.
    if (\Drupal::currentUser()->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    $user_input = json_decode($this->request->getCurrentRequest()->getContent(), TRUE);
    $otp_service = \Drupal::service('xinshi_sms.OTP');
    $data = [
      'status' => TRUE,
      'message' => '',
    ];
    $auto_register = $user_input["auto_register"] ?? TRUE;
    $phone_number = $user_input["mobile_number"];
    try {
      $message = $otp_service->validateMobileNumber($phone_number);
      if (empty($message)) {
        if (empty($auto_register) && !$otp_service->otpLoginCheckUserAlreadyExists($phone_number)) {
          $data = [
            'status' => FALSE,
            'message' => $this->t('The number is not registered'),
          ];
        } else {
          $context = new RenderContext();
          \Drupal::service('renderer')->executeInRenderContext($context, function () use ($otp_service, $phone_number) {
            $otp_service->generateOtp($phone_number);
          });
        }
      } else {
        $data = [
          'status' => FALSE,
          'message' => $message,
        ];
      }
    } catch (\Exception $exception) {
      $data = [
        'status' => FALSE,
        'message' => $exception->getMessage(),
      ];
    }
    return new ResourceResponse($data);
  }

}
