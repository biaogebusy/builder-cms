<?php

namespace Drupal\xinshi_sms\Plugin\rest\resource;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Site\Settings;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\xinshi_sms\OtpErrorCode;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Defuse\Crypto\Crypto;

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
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->request = $container->get('request_stack');
    $instance->moduleHandler = $container->get('module_handler');

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
    $otp = $user_input["code"] ?? '';
    $mobile_number = $user_input["mobile_number"] ?? '';
    $otp_service = \Drupal::service('xinshi_sms.OTP');
    $data = [
      'status' => TRUE,
      'message' => '',
    ];
    try {
      $message = $otp_service->validateMobileNumber($mobile_number);
      if (empty($message)) {
        $is_invalid_otp = $otp_service->validateOtp($otp, $mobile_number);
        if ($is_invalid_otp) {
          $data = OtpErrorCode::failure(OtpErrorCode::CODE_INCORRECT, 'Incorrect Code');
        }
        else {
          // Check if OAuth2 token generation is requested.
          if (!empty($user_input['grant_type']) && $user_input['grant_type'] === 'oauth2') {
            // For OAuth flow, only validate client and generate token.
            // generateOAuthToken throws \RuntimeException on failure with a
            // specific reason, kept as debug text behind a single stable code.
            try {
              return new ResourceResponse($this->generateOAuthToken($user_input));
            }
            catch (\RuntimeException $exception) {
              return new ResourceResponse(
                OtpErrorCode::failure(OtpErrorCode::OAUTH_FAILED, $exception->getMessage())
              );
            }
          }
          else {
            // Standard OTP login flow.
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
        }
      }
      else {
        $data = OtpErrorCode::failure(OtpErrorCode::phoneFailure($mobile_number), $message);
      }
    }
    catch (\Exception $exception) {
      $this->logger->error('OTP login failed: @message', ['@message' => $exception->getMessage()]);
      $data = OtpErrorCode::failure(OtpErrorCode::SERVER_ERROR, $exception->getMessage());
    }
    return new ResourceResponse($data);
  }

  /**
   * Generates OAuth2 token for authenticated user.
   *
   * @param array $user_input
   *   The user input from the request.
   *
   * @return array
   *   The token data on success.
   *
   * @throws \RuntimeException
   *   When token generation fails. The message identifies the failed step.
   */
  protected function generateOAuthToken(array $user_input) {
    // Check if simple_oauth module is enabled.
    if (!$this->moduleHandler->moduleExists('simple_oauth')) {
      throw new \RuntimeException('simple_oauth module is not enabled');
    }

    // Validate required parameters.
    if (empty($user_input['client_id'])) {
      throw new \RuntimeException('Missing client_id');
    }

    $client_repository = \Drupal::service('simple_oauth.repositories.client');
    $scope_repository = \Drupal::service('simple_oauth.repositories.scope');
    $access_token_repository = \Drupal::service('simple_oauth.repositories.access_token');
    $refresh_token_repository = \Drupal::service('simple_oauth.repositories.refresh_token');
    $config_factory = \Drupal::service('config.factory');

    // Confirm client is registered. For this custom OTP grant the OTP itself
    // is the user credential — browser-based clients cannot safely hold a
    // client_secret, so we don't require one. If a client_secret is provided
    // we still validate it for defense in depth.
    $client_entity = $client_repository->getClientEntity($user_input['client_id']);
    if (empty($client_entity)) {
      throw new \RuntimeException('Client not found');
    }
    if (!empty($user_input['client_secret'])) {
      if (!$client_repository->validateClient(
        $user_input['client_id'],
        $user_input['client_secret'],
        'password'
      )) {
        throw new \RuntimeException('Invalid client credentials');
      }
    }

    // Get user by mobile number.
    if (empty($user_input['mobile_number'])) {
      throw new \RuntimeException('Missing mobile_number');
    }

    $user = \Drupal::service('xinshi_sms.OTP')->otpLoginCheckUserAlreadyExists($user_input['mobile_number']);
    if (empty($user)) {
      throw new \RuntimeException('User not found for mobile_number');
    }
    $user_id = $user->id();

    try {
      // Scopes live on the consumer's `scopes` field, each item keyed by
      // `scope_id`. A consumer with none configured yields a token without
      // scopes — authorization is still enforced by Drupal roles/permissions.
      $scopes = [];
      $scope_values = $client_entity->getDrupalEntity()->get('scopes')->getValue();
      foreach (array_filter(array_column($scope_values, 'scope_id')) as $scope_identifier) {
        $scope = $scope_repository->getScopeEntityByIdentifier($scope_identifier);
        if ($scope) {
          $scopes[] = $scope;
        }
      }
      // Create access token.
      $access_token = $access_token_repository->getNewToken($client_entity, $scopes, $user_id);
      $settings = $config_factory->get('simple_oauth.settings');
      $expiration = new \DateInterval(sprintf('PT%dS', $settings->get('access_token_expiration')));
      $access_token->setExpiryDateTime((new \DateTimeImmutable())->add($expiration));
      $access_token->setIdentifier(\Drupal::service('uuid')->generate());

      // Persist access token.
      $access_token_repository->persistNewAccessToken($access_token);

      // Create refresh token.
      $refresh_token_expiration = new \DateInterval(sprintf('PT%dS', $settings->get('refresh_token_expiration')));
      $refresh_token = $refresh_token_repository->getNewRefreshToken();
      $refresh_token->setExpiryDateTime((new \DateTimeImmutable())->add($refresh_token_expiration));
      $refresh_token->setIdentifier(\Drupal::service('uuid')->generate());
      $refresh_token->setAccessToken($access_token);

      // Persist refresh token.
      $refresh_token_repository->persistNewRefreshToken($refresh_token);

      // Generate JWT token for access token.
      $private_key_path = $settings->get('private_key');
      if (empty($private_key_path)) {
        throw new \RuntimeException('simple_oauth private_key is not configured');
      }
      $private_key = \Drupal::service('file_system')->realpath($private_key_path);
      if (empty($private_key) || !is_readable($private_key)) {
        throw new \RuntimeException('simple_oauth private_key file is missing or unreadable');
      }
      $key = new \League\OAuth2\Server\CryptKey($private_key);

      $access_token->setPrivateKey($key);
      $jwt_access_token = $access_token->toString();

      // Encrypt refresh token.
      $refresh_token_payload = json_encode([
        'client_id' => $client_entity->getIdentifier(),
        'refresh_token_id' => $refresh_token->getIdentifier(),
        'access_token_id' => $access_token->getIdentifier(),
        'scopes' => $access_token->getScopes(),
        'user_id' => $user_id,
        'expire_time' => $refresh_token->getExpiryDateTime()->getTimestamp(),
      ]);

      // Use Drupal's hash salt as encryption key (first 32 characters).
      $salt = Settings::getHashSalt();
      $encryption_key = substr($salt, 0, 32);
      $encrypted_refresh_token = Crypto::encryptWithPassword($refresh_token_payload, $encryption_key);

      return [
        'token_type' => 'Bearer',
        'expires_in' => $settings->get('access_token_expiration'),
        'access_token' => $jwt_access_token,
        'refresh_token' => $encrypted_refresh_token,
      ];
    } catch (\RuntimeException $exception) {
      // Already carries the failed step; re-thrown as-is so the reason is not
      // buried under a second wrapper.
      $this->logger->error('OAuth2 token generation failed: @message', ['@message' => $exception->getMessage()]);
      throw $exception;
    } catch (\Exception $exception) {
      $this->logger->error('OAuth2 token generation failed: @message', ['@message' => $exception->getMessage()]);
      throw new \RuntimeException('OAuth2 token generation failed: ' . $exception->getMessage(), 0, $exception);
    }
  }
}
