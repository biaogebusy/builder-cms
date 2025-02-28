<?php

namespace Drupal\xinshi_sms\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Psr\Log\LoggerInterface;
use Drupal\otp_login\Plugin\rest\resource\OtpSubmitResource as BaseOtpSubmitResource;

/**
 * Annotation for OTP send.
 *
 * @RestResource(
 *   id = "xinshi_otp_login",
 *   label = @Translation("XINSHI Login OTP"),
 *   uri_paths = {
 *     "create" = "/api/v3/otp/login",
 *     "https://www.drupal.org/link-relations/create" = "/api/v1/otp/login"
 *   }
 * )
 */
class OtpSubmitResource extends ResourceBase {

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
    $otp = $user_input["code"];
    $mobile_number = $user_input["mobile_number"];
    $otp_service = \Drupal::service('xinshi_sms.OTP');
    $data = [
      'status' => TRUE,
      'message' => '',
    ];
    try {
      $message = $otp_service->validateMobileNumber($user_input["mobile_number"]);
      if (empty($message)) {
        $is_invalid_otp = $otp_service->validateOtp($otp, $mobile_number);
        if ($is_invalid_otp) {
          $data = [
            'status' => FALSE,
            'message' => 'Incorrect Code',
          ];
        } else {
          $otp_service->userOtpLogin($otp, $mobile_number);
          $logout_path = \Drupal::service('router.route_provider')->getRouteByName('user.logout.http');
          $logout_path = ltrim($logout_path->getPath(), '/');
          $data['logout_token'] = \Drupal::service('csrf_token')->get($logout_path);
          $user = \Drupal::currentUser();
          $data['current_user'] = [
            'uid' => $user->id(),
            'name' => $user->getDisplayName(),
          ];
          $data['csrf_token'] = \Drupal::service('csrf_token')->get('rest');
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
