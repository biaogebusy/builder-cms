<?php

namespace Drupal\xinshi_sms\Plugin\rest\resource;

use Drupal\Core\Render\RenderContext;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\xinshi_sms\OtpErrorCode;
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
   * Flood event for requests aimed at one phone number.
   */
  const FLOOD_PHONE_EVENT = 'xinshi_sms.otp_generate_phone';

  /**
   * Flood event for requests coming from one client IP.
   */
  const FLOOD_IP_EVENT = 'xinshi_sms.otp_generate_ip';

  /**
   * Codes allowed per phone number within the flood window.
   */
  const FLOOD_PHONE_LIMIT = 5;

  /**
   * Codes allowed per client IP within the flood window — enough for a shared
   * office NAT, low enough to stop a caller cycling through numbers.
   */
  const FLOOD_IP_LIMIT = 20;

  /**
   * Flood window in seconds.
   */
  const FLOOD_WINDOW = 3600;

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The flood control service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->request = $container->get('request_stack');
    $instance->flood = $container->get('flood');
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
    $phone_number = $user_input["mobile_number"] ?? '';
    try {
      $message = $otp_service->validateMobileNumber($phone_number);
      if (!empty($message)) {
        return new ResourceResponse(
          OtpErrorCode::failure(OtpErrorCode::phoneFailure($phone_number), $message)
        );
      }
      // Every code costs an SMS and may create an account, so the send is rate
      // limited before any of that happens.
      if (!$this->isSendAllowed($phone_number)) {
        return new ResourceResponse(
          OtpErrorCode::failure(OtpErrorCode::CODE_SEND_THROTTLED, 'Too many code requests'),
          429
        );
      }
      if (empty($auto_register) && !$otp_service->otpLoginCheckUserAlreadyExists($phone_number)) {
        $data = OtpErrorCode::failure(OtpErrorCode::PHONE_NOT_REGISTERED, 'The number is not registered');
      }
      else {
        $context = new RenderContext();
        \Drupal::service('renderer')->executeInRenderContext($context, function () use ($otp_service, $phone_number) {
          $otp_service->generateOtp($phone_number);
        });
        $this->registerSend($phone_number);
      }
    }
    catch (\Exception $exception) {
      $this->logger->error('OTP generation failed: @message', ['@message' => $exception->getMessage()]);
      $data = OtpErrorCode::failure(OtpErrorCode::CODE_SEND_FAILED, $exception->getMessage());
    }
    return new ResourceResponse($data);
  }

  /**
   * Checks both flood limits.
   *
   * @param string $phone_number
   *   The number the code would be sent to.
   *
   * @return bool
   *   TRUE when the send may proceed.
   */
  protected function isSendAllowed($phone_number) {
    return $this->flood->isAllowed(self::FLOOD_PHONE_EVENT, self::FLOOD_PHONE_LIMIT, self::FLOOD_WINDOW, $phone_number)
      && $this->flood->isAllowed(self::FLOOD_IP_EVENT, self::FLOOD_IP_LIMIT, self::FLOOD_WINDOW);
  }

  /**
   * Counts a send against both flood limits.
   *
   * @param string $phone_number
   *   The number the code was sent to.
   */
  protected function registerSend($phone_number) {
    $this->flood->register(self::FLOOD_PHONE_EVENT, self::FLOOD_WINDOW, $phone_number);
    $this->flood->register(self::FLOOD_IP_EVENT, self::FLOOD_WINDOW);
  }

}
