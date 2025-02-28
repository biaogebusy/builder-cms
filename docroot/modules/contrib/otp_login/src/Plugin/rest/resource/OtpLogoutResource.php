<?php

namespace Drupal\otp_login\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Psr\Log\LoggerInterface;

/**
 * Annotation for OTP send.
 *
 * @RestResource(
 *   id = "logout_otp_resource",
 *   label = @Translation("Logout OTP"),
 *   uri_paths = {
 *     "canonical" = "/otp/logout",
 *     "https://www.drupal.org/link-relations/create" = "/otp/logout"
 *   }
 * )
 */
class OtpLogoutResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
  array $configuration,
  $plugin_id,
  $plugin_definition,
  array $serializer_formats,
  LoggerInterface $logger,
   AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id,
    $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
     $configuration,
     $plugin_id,
     $plugin_definition,
     $container->getParameter('serializer.formats'),
     $container->get('logger.factory')->get('otp_login'),
     $container->get('current_user')
    );
  }

  /**
   * Responds to POST requests.
   *
   * Returns a list of bundles for specified entity.
   *
   * @param array $user_input
   *   User input.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Throws exception expected.
   */
  public function post(array $user_input) {

    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }
    $session_id = $user_input["session_id"];
    $mobile_number = $user_input["mobile_number"];
    $otp_service = \Drupal::service('otp_login.OTP');
    $is_invalid_session_id = $otp_service->validateSessionid($session_id, $mobile_number);
    if (!$session_id or !$mobile_number) {
      $error_response = ["status" => "session_failure", "message" => "Session id or Mobile number not provided."];
      return new ResourceResponse($error_response);
    }
    elseif ($is_invalid_session_id) {
      $error_response = ["status" => "session_failure", "message" => "Invalid Session id or Mobile number."];
      return new ResourceResponse($error_response);
    }
    else {
      return new ResourceResponse($otp_service->userOtpLogout($session_id, $mobile_number));
    }
  }

}
