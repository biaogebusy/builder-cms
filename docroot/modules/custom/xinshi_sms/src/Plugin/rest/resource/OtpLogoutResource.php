<?php

namespace Drupal\xinshi_sms\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Annotation for OTP send.
 *
 * @RestResource(
 *   id = "xinshi_otp_logout",
 *   label = @Translation("XINSHI Logout OTP"),
 *   uri_paths = {
 *     "create" = "/api/v3/otp/logout",
 *     "https://www.drupal.org/link-relations/create" = "/api/v1/otp/logout"
 *   }
 * )
 */
class OtpLogoutResource extends ResourceBase {

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
   * @return \Drupal\rest\ResourceResponse
   *   Throws exception expected.
   */
  public function post() {

    // Use current user after pass authentication to validate access.
    if (\Drupal::currentUser()->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    $user_input = json_decode($this->request->getCurrentRequest()->getContent(), TRUE);
    $session_id = $user_input["session_id"];
    $mobile_number = $user_input["mobile_number"];
    $otp_service = \Drupal::service('xinshi_sms.OTP');
    $is_invalid_session_id = $otp_service->validateSessionid($session_id, $mobile_number);
    if (!$session_id or !$mobile_number) {
      $error_response = ["status" => "session_failure", "message" => "Session id or Mobile number not provided."];
      return new ResourceResponse($error_response);
    } elseif ($is_invalid_session_id) {
      $error_response = ["status" => "session_failure", "message" => "Invalid Session id or Mobile number."];
      return new ResourceResponse($error_response);
    } else {
      return new ResourceResponse($otp_service->userOtpLogout($session_id, $mobile_number));
    }
  }

}
