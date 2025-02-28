<?php


namespace Drupal\xinshi_api\Controller;

use Drupal\user\Controller\UserAuthenticationController as BaseUserAuthenticationController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class UserAuthenticationController extends BaseUserAuthenticationController {

  /**
   * {@inheritDoc}
   */
  public function login(Request $request) {
    $format = $this->getRequestFormat($request);

    $content = $request->getContent();
    $credentials = $this->serializer->decode($content, $format);
    if (!isset($credentials['name']) && !isset($credentials['pass'])) {
      throw new BadRequestHttpException('Missing credentials.');
    }

    if (!isset($credentials['name'])) {
      throw new BadRequestHttpException('Missing credentials.name.');
    }
    if (!isset($credentials['pass'])) {
      throw new BadRequestHttpException('Missing credentials.pass');
    }

    $this->floodControl($request, $credentials['name']);

    if ($this->userIsBlocked($credentials['name'])) {
      throw new BadRequestHttpException('The user has not been activated or is blocked.');
    }

    if ($uid = \Drupal::service('xinshi_api.auth')->authenticate($credentials['name'], $credentials['pass'])) {
      $this->userFloodControl->clear('user.http_login', $this->getLoginFloodIdentifier($request, $credentials['name']));
      /** @var \Drupal\user\UserInterface $user */
      $user = $this->userStorage->load($uid);
      $this->userLoginFinalize($user);

      // Send basic metadata about the logged in user.
      $response_data = [];
      if ($user->get('uid')->access('view', $user)) {
        $response_data['current_user']['uid'] = $user->id();
      }
      if ($user->get('roles')->access('view', $user)) {
        $response_data['current_user']['roles'] = $user->getRoles();
      }
      if ($user->get('name')->access('view', $user)) {
        $response_data['current_user']['name'] = $user->getAccountName();
      }
      $response_data['csrf_token'] = $this->csrfToken->get('rest');

      $logout_route = $this->routeProvider->getRouteByName('user.logout.http');
      // Trim '/' off path to match \Drupal\Core\Access\CsrfAccessCheck.
      $logout_path = ltrim($logout_route->getPath(), '/');
      $response_data['logout_token'] = $this->csrfToken->get($logout_path);

      $encoded_response_data = $this->serializer->encode($response_data, $format);
      return new Response($encoded_response_data);
    }

    $flood_config = $this->config('user.flood');
    if ($identifier = $this->getLoginFloodIdentifier($request, $credentials['name'])) {
      $this->userFloodControl->register('user.http_login', $flood_config->get('user_window'), $identifier);
    }
    // Always register an IP-based failed login event.
    $this->userFloodControl->register('user.failed_login_ip', $flood_config->get('ip_window'));
    throw new BadRequestHttpException('Sorry, unrecognized username or password.');
  }

  /**
   * {@inheritDoc}
   */
  protected function getLoginFloodIdentifier(Request $request, $username) {
    $flood_config = $this->config('user.flood');
    $accounts = $this->userStorage->loadByProperties(['name' => $username, 'status' => 1]);
    if (empty($accounts)) {
      $accounts = $this->userStorage->loadByProperties(['mail' => $username, 'status' => 1]);
    }
    if ($account = reset($accounts)) {
      if ($flood_config->get('uid_only')) {
        // Register flood events based on the uid only, so they apply for any
        // IP address. This is the most secure option.
        $identifier = $account->id();
      } else {
        // The default identifier is a combination of uid and IP address. This
        // is less secure but more resistant to denial-of-service attacks that
        // could lock out all users with public user names.
        $identifier = $account->id() . '-' . $request->getClientIp();
      }
      return $identifier;
    }
    return '';
  }

  /**
   * {@inheritDoc}
   */
  protected function userIsBlocked($name) {
    $query = \Drupal::entityQuery('user');
    $or_group = $query->orConditionGroup();
    $or_group->condition('name', $name);
    $or_group->condition('mail', $name);
    return (bool) $query
      ->accessCheck(FALSE)
      ->condition($or_group)
      ->condition('status', 0)
      ->execute();
  }
}
