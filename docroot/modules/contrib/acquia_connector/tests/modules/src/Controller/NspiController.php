<?php

namespace Drupal\acquia_connector_test\Controller;

use Drupal\acquia_connector\CryptConnector;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Class NspiController.
 *
 * This class mocks a Guzzle response from the relevant APIs.
 *
 * It used to mock the responses in Symfony via a Drupal controller, but this
 * caused the process to block itself when run on the single threaded PHP
 * built-in server.
 *
 * @package Drupal\acquia_connector_test\Controller
 */
class NspiController extends ControllerBase {

  /**
   * Test site machine name.
   *
   * @var string
   */
  protected $acqtestSiteMachineName;

  /**
   * Test site is Acquia hosted if not empty.
   *
   * @var mixed
   */
  protected $acquiaHosted;

  const ACQTEST_SUBSCRIPTION_NOT_FOUND = 1000;
  const ACQTEST_SUBSCRIPTION_EXPIRED = 1200;
  const ACQTEST_SUBSCRIPTION_MESSAGE_FUTURE = 1500;
  const ACQTEST_SUBSCRIPTION_MESSAGE_EXPIRED = 1600;
  const ACQTEST_SUBSCRIPTION_MESSAGE_INVALID = 700;
  const ACQTEST_SUBSCRIPTION_VALIDATION_ERROR = 1800;
  // 15*60.
  const ACQTEST_SUBSCRIPTION_MESSAGE_LIFETIME = 900;
  const ACQTEST_SUBSCRIPTION_SERVICE_UNAVAILABLE = 503;
  const ACQTEST_ID = 'TEST_AcquiaConnectorTestID';
  const ACQTEST_KEY = 'TEST_AcquiaConnectorTestKey';
  const ACQTEST_ERROR_ID = 'TEST_AcquiaConnectorTestIDErr';
  const ACQTEST_ERROR_KEY = 'TEST_AcquiaConnectorTestKeyErr';
  const ACQTEST_EXPIRED_ID = 'TEST_AcquiaConnectorTestIDExp';
  const ACQTEST_EXPIRED_KEY = 'TEST_AcquiaConnectorTestKeyExp';
  const ACQTEST_503_ID = 'TEST_AcquiaConnectorTestID503';
  const ACQTEST_503_KEY = 'TEST_AcquiaConnectorTestKey503';
  const ACQTEST_SITE_UUID = 'TEST_cdbd59f5-ca7e-4652-989b-f9e46d309613';
  const ACQTEST_UUID = 'cdbd59f5-ca7e-4652-989b-f9e46d312458';

  /**
   * Construction method.
   */
  public function __construct() {
    $this->acqtestSiteMachineName = \Drupal::state()->get('acqtest_site_machine_name');
    $this->acquiaHosted = \Drupal::state()->get('acqtest_site_acquia_hosted');
  }

  /**
   * SPI API site update.
   *
   * @param \GuzzleHttp\Psr7\Request $request
   *   Request.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   JsonResponse.
   */
  public function nspiUpdate(Request $request) {
    $data = json_decode($request->getBody(), TRUE);

    $fields = [
      'time' => 'is_numeric',
      'nonce' => 'is_string',
      'hash' => 'is_string',
    ];
    $result = $this->basicAuthenticator($fields, $data);

    if (!empty($result['error'])) {
      return new Response(200, [], json_encode($result));
    }
    if (!empty($data['authenticator']['identifier'])) {
      if ($data['authenticator']['identifier'] != self::ACQTEST_ID && $data['authenticator']['identifier'] != self::ACQTEST_ERROR_ID) {
        return new Response(self::ACQTEST_SUBSCRIPTION_SERVICE_UNAVAILABLE, [], json_encode($this->errorResponse(self::ACQTEST_SUBSCRIPTION_VALIDATION_ERROR, $this->t('Subscription not found'))));
      }
      if ($data['authenticator']['identifier'] == self::ACQTEST_ERROR_ID) {
        return new Response();
      }
      else {
        $result = $this->validateAuthenticator($data);
        // Needs for update definition.
        $data['body']['spi_def_update'] = TRUE;
        $spi_data = $data['body'];

        $result['body'] = ['spi_data_received' => TRUE];
        if (isset($spi_data['spi_def_update'])) {
          $result['body']['update_spi_definition'] = TRUE;
        }

        // Reflect send_method as nspi_messages if set.
        if (isset($spi_data['send_method'])) {
          $result['body']['nspi_messages'][] = $spi_data['send_method'];
        }
        $result['authenticator']['hash'] = CryptConnector::acquiaHash($result['secret']['key'], $result['authenticator']['time'] . ':' . $result['authenticator']['nonce']);
        if (isset($spi_data['test_validation_error'])) {
          // Force a validation fail.
          $result['authenticator']['nonce'] = 'TEST';
        }

        $site_action = $spi_data['env_changed_action'];
        // First connection.
        if (empty($spi_data['site_uuid'])) {
          $site_action = 'create';
        }

        switch ($site_action) {
          case 'create':
            $result['body']['site_uuid'] = self::ACQTEST_SITE_UUID;
            // Set machine name.
            \Drupal::state()->set('acqtest_site_machine_name', $spi_data['machine_name']);
            // Set name.
            \Drupal::state()->set('acqtest_site_name', $spi_data['name']);
            $acquia_hosted = (int) filter_var($spi_data['acquia_hosted'], FILTER_VALIDATE_BOOLEAN);
            \Drupal::state()->set('acqtest_site_acquia_hosted', $acquia_hosted);

            $result['body']['nspi_messages'][] = $this->t('This is the first connection from this site, it may take awhile for it to appear.');
            return new Response(200, [], json_encode($result));

          case 'update':
            $update = $this->updateNspiSite($spi_data);
            $result['body']['nspi_messages'][] = $update;
            break;

          case 'unblock':
            \Drupal::state()->delete('acqtest_site_blocked');
            $result['body']['spi_error'] = '';
            $result['body']['nspi_messages'][] = $this->t('Your site has been enabled and is sending data to Acquia Cloud.');
            return new Response(200, [], json_encode($result));

          case 'block':
            \Drupal::state()->set('acqtest_site_blocked', TRUE);
            $result['body']['spi_error'] = '';
            $result['body']['nspi_messages'][] = $this->t('You have disabled your site from sending data to Acquia Cloud.');
            return new Response(200, [], json_encode($result));

        }

        // Update site name if it has changed.
        $tacqtest_site_name = \Drupal::state()->get('acqtest_site_name');
        if (isset($spi_data['name']) && $spi_data['name'] != $tacqtest_site_name) {
          if (!empty($tacqtest_site_name)) {
            $name_update_message = $this->t('Site name updated (from @old_name to @new_name).', [
              '@old_name' => $tacqtest_site_name,
              '@new_name' => $spi_data['name'],
            ]);

            \Drupal::state()->set('acqtest_site_name', $spi_data['name']);
          }
          $result['body']['nspi_messages'][] = $name_update_message;
        }

        // Detect Changes.
        if ($changes = $this->detectChanges($spi_data)) {
          $result['body']['nspi_messages'][] = $changes['response'];
          $result['body']['spi_error'] = TRUE;
          $result['body']['spi_environment_changes'] = json_encode($changes['changes']);
          return new Response(200, [], json_encode($result));
        }

        unset($result['secret']);
        return new Response(200, [], json_encode($result));
      }
    }
    else {
      return new Response(self::ACQTEST_SUBSCRIPTION_SERVICE_UNAVAILABLE, [], json_encode($this->errorResponse(self::ACQTEST_SUBSCRIPTION_VALIDATION_ERROR, $this->t('Invalid arguments'))));
    }
  }

  /**
   * Detect potential environment changes.
   *
   * @param array $spi_data
   *   SPI data array.
   *
   * @return array|bool
   *   FALSE or changes message.
   */
  public function detectChanges(array $spi_data) {
    $changes = [];
    $site_blocked = \Drupal::state()->get('acqtest_site_blocked');

    if ($site_blocked) {
      $changes['changes']['blocked'] = (string) $this->t('Your site has been enabled.');
    }
    else {

      if ($this->checkAcquiaHostedStatusChanged($spi_data) && !is_null($this->acquiaHosted)) {
        if ($spi_data['acquia_hosted']) {
          $changes['changes']['acquia_hosted'] = (string) $this->t('Your site is now Acquia hosted.');
        }
        else {
          $changes['changes']['acquia_hosted'] = (string) $this->t('Your site is no longer Acquia hosted.');
        }
      }

      if ($this->checkMachineNameStatusChanged($spi_data)) {
        $changes['changes']['machine_name'] = (string) $this->t('Your site machine name changed from @old_machine_name to @new_machine_name.', [
          '@old_machine_name' => $this->acqtestSiteMachineName,
          '@new_machine_name' => $spi_data['machine_name'],
        ]);
      }

    }

    if (empty($changes)) {
      return FALSE;
    }

    $changes['response'] = (string) $this->t('A change has been detected in your site environment. Please check the Acquia SPI status on your Status Report page for more information.');

    return $changes;
  }

  /**
   * Save changes to the site entity.
   *
   * @param array $spi_data
   *   SPI data array.
   *
   * @return string
   *   Message string.
   */
  public function updateNspiSite(array $spi_data) {
    $message = '';

    if ($this->checkMachineNameStatusChanged($spi_data)) {
      if (!empty($this->acqtestSiteMachineName)) {
        $message = (string) $this->t('Updated site machine name from @old_machine_name to @new_machine_name.', ['@old_machine_name' => $this->acqtestSiteMachineName, '@new_machine_name' => $spi_data['machine_name']]);
      }
      else {
        $message = (string) $this->t('Site machine name set to to @new_machine_name.', ['@new_machine_name' => $spi_data['machine_name']]);
      }

      \Drupal::state()->set('acqtest_site_machine_name', $spi_data['machine_name']);
      $this->acqtestSiteMachineName = $spi_data['machine_name'];
    }

    if ($this->checkAcquiaHostedStatusChanged($spi_data)) {
      if (!is_null($this->acquiaHosted)) {
        $hosted_message = $spi_data['acquia_hosted'] ? (string) $this->t('site is now Acquia hosted') : (string) $this->t('site is no longer Acquia hosted');
        $message = (string) $this->t('Updated Acquia hosted status (@hosted_message).', ['@hosted_message' => $hosted_message]);
      }

      $acquia_hosted = (int) filter_var($spi_data['acquia_hosted'], FILTER_VALIDATE_BOOLEAN);
      \Drupal::state()->set('acqtest_site_acquia_hosted', $acquia_hosted);
      $this->acquiaHosted = $acquia_hosted;
    }

    return $message;
  }

  /**
   * Detect if machine name changed.
   *
   * @param array $spi_data
   *   SPI data.
   *
   * @return bool
   *   TRUE if machine name was changed.
   */
  public function checkMachineNameStatusChanged(array $spi_data) {
    return isset($spi_data['machine_name']) && $spi_data['machine_name'] != $this->acqtestSiteMachineName;
  }

  /**
   * Detect if Acquia hosted changed.
   *
   * @param array $spi_data
   *   SPI data.
   *
   * @return bool
   *   TRUE if site is Acquia Hosted.
   */
  public function checkAcquiaHostedStatusChanged(array $spi_data) {
    return isset($spi_data['acquia_hosted']) && (bool) $spi_data['acquia_hosted'] != (bool) $this->acquiaHosted;
  }

  /**
   * Return spi definition.
   *
   * @param \GuzzleHttp\Psr7\Request $request
   *   Request.
   * @param string $version
   *   Version.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   JsonResponse.
   */
  public function spiDefinition(Request $request, $version) {
    $vars = [
      'test_variable_1' => [
        'optional' => FALSE,
        'description' => 'test_variable_1',
      ],
      'test_variable_2' => [
        'optional' => TRUE,
        'description' => 'test_variable_2',
      ],
      'test_variable_3' => [
        'optional' => TRUE,
        'description' => 'test_variable_3',
      ],
    ];
    $data = [
      'drupal_version' => (string) $version,
      'timestamp' => (string) (\Drupal::time()->getRequestTime() + 9),
      'acquia_spi_variables' => $vars,
    ];
    return new Response(200, [], json_encode($data));
  }

  /**
   * Test return communication settings for an account.
   *
   * @param \GuzzleHttp\Psr7\Request $request
   *   Request.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   JsonResponse.
   */
  public function getCommunicationSettings(Request $request) {
    $data = json_decode($request->getBody(), TRUE);
    $fields = [
      'time' => 'is_numeric',
      'nonce' => 'is_string',
      'hash' => 'is_string',
    ];

    // Authenticate.
    $result = $this->basicAuthenticator($fields, $data);
    if (!empty($result['error'])) {
      return new Response(200, [], json_encode($result));
    }

    if (!isset($data['body']) || !isset($data['body']['email'])) {
      return new Response(self::ACQTEST_SUBSCRIPTION_SERVICE_UNAVAILABLE, [], json_encode($this->errorResponse(self::ACQTEST_SUBSCRIPTION_VALIDATION_ERROR, $this->t('Invalid arguments'))));
    }
    $account = user_load_by_mail($data['body']['email']);
    if (empty($account) || $account->isAnonymous()) {
      return new Response(self::ACQTEST_SUBSCRIPTION_SERVICE_UNAVAILABLE, [], json_encode($this->errorResponse(self::ACQTEST_SUBSCRIPTION_VALIDATION_ERROR, $this->t('Account not found'))));
    }
    $result = [
      'algorithm' => 'sha512',
      'hash_setting' => substr($account->getPassword(), 0, 12),
      'extra_md5' => FALSE,
    ];
    return new Response(200, [], json_encode($result));
  }

  /**
   * Basic authenticator.
   *
   * @param array $fields
   *   Fields array.
   * @param array $data
   *   Data array.
   *
   * @return array
   *   Result array.
   */
  protected function basicAuthenticator(array $fields, array $data) {
    $result = [];
    foreach ($fields as $field => $type) {
      if (empty($data['authenticator'][$field]) || !$type($data['authenticator'][$field])) {
        return $this->errorResponse(self::ACQTEST_SUBSCRIPTION_MESSAGE_INVALID, $this->t('Authenticator field @field is missing or invalid.', ['@field' => $field]));
      }
    }
    $now = \Drupal::time()->getRequestTime();
    if ($data['authenticator']['time'] > ($now + self::ACQTEST_SUBSCRIPTION_MESSAGE_LIFETIME)) {
      return $this->errorResponse(self::ACQTEST_SUBSCRIPTION_MESSAGE_FUTURE, $this->t('Message time ahead of server time.'));
    }
    else {
      if ($data['authenticator']['time'] < ($now - self::ACQTEST_SUBSCRIPTION_MESSAGE_LIFETIME)) {
        return $this->errorResponse(self::ACQTEST_SUBSCRIPTION_MESSAGE_EXPIRED, $this->t('Message is too old.'));
      }
    }

    $result['error'] = FALSE;
    return $result;
  }

  /**
   * Test returns subscriptions for an email.
   *
   * @param \GuzzleHttp\Psr7\Request $request
   *   Request.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   JsonResponse.
   */
  public function getCredentials(Request $request) {
    $data = json_decode($request->getBody(), TRUE);

    $fields = [
      'time' => 'is_numeric',
      'nonce' => 'is_string',
      'hash' => 'is_string',
    ];
    $result = $this->basicAuthenticator($fields, $data);
    if (!empty($result['error'])) {
      return new Response(self::ACQTEST_SUBSCRIPTION_SERVICE_UNAVAILABLE, [], $result);
    }

    if (!empty($data['body']['email'])) {
      $account = user_load_by_mail($data['body']['email']);
      $this->getLogger('getCredentials password')->debug($account->getPassword());
      if (empty($account) || $account->isAnonymous()) {
        return new Response(self::ACQTEST_SUBSCRIPTION_SERVICE_UNAVAILABLE, [], json_encode($this->errorResponse(self::ACQTEST_SUBSCRIPTION_VALIDATION_ERROR, $this->t('Account not found'))));
      }
    }
    else {
      return new Response(self::ACQTEST_SUBSCRIPTION_SERVICE_UNAVAILABLE, [], json_encode($this->errorResponse(self::ACQTEST_SUBSCRIPTION_VALIDATION_ERROR, $this->t('Invalid arguments'))));
    }

    $hash = CryptConnector::acquiaHash($account->getPassword(), $data['authenticator']['time'] . ':' . $data['authenticator']['nonce']);
    if ($hash === $data['authenticator']['hash']) {
      $result = [];
      $result['is_error'] = FALSE;
      $result['body']['subscription'][] = [
        'identifier' => self::ACQTEST_ID,
        'key' => self::ACQTEST_KEY,
        'name' => self::ACQTEST_ID,
      ];
      return new Response(200, [], json_encode($result));
    }
    else {
      return new Response(self::ACQTEST_SUBSCRIPTION_SERVICE_UNAVAILABLE, [], json_encode($this->errorResponse(self::ACQTEST_SUBSCRIPTION_VALIDATION_ERROR, $this->t('Incorrect password.'))));
    }
  }

  /**
   * Test validates an Acquia subscription.
   *
   * @param \GuzzleHttp\Psr7\Request $request
   *   Request.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   JsonResponse.
   */
  public function getSubscription(Request $request) {
    $data = json_decode($request->getBody(), TRUE);
    $result = $this->validateAuthenticator($data);
    if (empty($result['error'])) {
      $result['authenticator']['hash'] = CryptConnector::acquiaHash($result['secret']['key'], $result['authenticator']['time'] . ':' . $result['authenticator']['nonce']);
      unset($result['secret']);
      return new Response(200, [], json_encode($result));
    }
    unset($result['secret']);
    return new Response(self::ACQTEST_SUBSCRIPTION_SERVICE_UNAVAILABLE, [], json_encode($result));
  }

  /**
   * Test validates an Acquia authenticator.
   *
   * @param array $data
   *   Data to validate.
   *
   * @return array
   *   Result array.
   */
  protected function validateAuthenticator(array $data) {
    $fields = [
      'time' => 'is_numeric',
      'identifier' => 'is_string',
      'nonce' => 'is_string',
      'hash' => 'is_string',
    ];

    $result = $this->basicAuthenticator($fields, $data);
    if (!empty($result['error'])) {
      return $result;
    }

    if (strpos($data['authenticator']['identifier'], 'TEST_') !== 0) {
      return $this->errorResponse(self::ACQTEST_SUBSCRIPTION_NOT_FOUND, $this->t('Subscription not found'));
    }

    switch ($data['authenticator']['identifier']) {
      case self::ACQTEST_ID:
        $key = self::ACQTEST_KEY;
        break;

      case self::ACQTEST_EXPIRED_ID:
        $key = self::ACQTEST_EXPIRED_KEY;
        break;

      case self::ACQTEST_503_ID:
        $key = self::ACQTEST_503_KEY;
        break;

      default:
        $key = self::ACQTEST_ERROR_KEY;
        break;
    }

    $hash = CryptConnector::acquiaHash($key, $data['authenticator']['time'] . ':' . $data['authenticator']['nonce']);
    $hash_simple = CryptConnector::acquiaHash($key, $data['authenticator']['time'] . ':' . $data['authenticator']['nonce']);

    if (($hash !== $data['authenticator']['hash']) && ($hash_simple != $data['authenticator']['hash'])) {
      return $this->errorResponse(self::ACQTEST_SUBSCRIPTION_VALIDATION_ERROR, $this->t('HMAC validation error: @expected != @actual', [
        '@expected' => $hash,
        '@actual' => $data['authenticator']['hash'],
      ]));
    }

    if ($key === self::ACQTEST_EXPIRED_KEY) {
      return $this->errorResponse(self::ACQTEST_SUBSCRIPTION_EXPIRED, $this->t('Subscription expired.'));
    }

    // Record connections.
    $connections = \Drupal::state()->get('test_connections' . $data['authenticator']['identifier']);
    $connections++;
    \Drupal::state()->set('test_connections' . $data['authenticator']['identifier'], $connections);
    if ($connections == 3 && $data['authenticator']['identifier'] == self::ACQTEST_503_ID) {
      // Trigger a 503 response on 3rd call to this (1st is
      // acquia.agent.subscription and 2nd is acquia.agent.validate)
      return $this->errorResponse(self::ACQTEST_SUBSCRIPTION_SERVICE_UNAVAILABLE, 'Subscription service unavailable.');
    }
    $result['error'] = FALSE;
    $result['body']['subscription_name'] = 'TEST_AcquiaConnectorTestID';
    $result['body']['active'] = 1;
    $result['body']['href'] = 'http://acquia.com/network';
    $result['body']['expiration_date']['value'] = '2023-10-08T06:30:00';
    $result['body']['product'] = '91990';
    $result['body']['derived_key_salt'] = $data['authenticator']['identifier'] . '_KEY_SALT';
    $result['body']['update_service'] = 1;
    $result['body']['search_service_enabled'] = 1;
    $result['body']['uuid'] = self::ACQTEST_UUID;
    if (isset($data['body']['rpc_version'])) {
      $result['body']['rpc_version'] = $data['body']['rpc_version'];
    }
    $result['secret']['data'] = $data;
    $result['secret']['nid'] = '91990';
    $result['secret']['node'] = $data['authenticator']['identifier'] . '_NODE';
    $result['secret']['key'] = $key;
    // $result['secret']['nonce'] = '';.
    $result['authenticator'] = $data['authenticator'];
    $result['authenticator']['hash'] = '';
    $result['authenticator']['time'] += 1;
    $result['authenticator']['nonce'] = $data['authenticator']['nonce'];
    return $result;
  }

  /**
   * Access callback.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed
   *   The access result.
   */
  public function access() {
    return AccessResultAllowed::allowed();
  }

  /**
   * Format the error response.
   *
   * @param mixed $code
   *   Error code.
   * @param string $message
   *   Error message.
   *
   * @return array
   *   Error response.
   */
  protected function errorResponse($code, $message) {
    return [
      'code' => $code,
      'message' => $message,
      'error' => TRUE,
    ];
  }

}
