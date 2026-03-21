<?php

namespace Drupal\simple_oauth\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\simple_oauth\Authentication\TokenAuthUser;
use Drupal\simple_oauth\Entities\JwksEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the User Info endpoint.
 */
class Jwks implements ContainerInjectionInterface {

  /**
   * The authenticated user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $user;

  /**
   * The configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * Jwks constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $user
   *   The user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  private function __construct(AccountProxyInterface $user, ConfigFactoryInterface $config_factory) {
    $this->user = $user->getAccount();
    $this->config = $config_factory->get('simple_oauth.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('config.factory')
    );
  }

  /**
   * The controller.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function handle() {
    if (!$this->user instanceof TokenAuthUser) {
      throw new AccessDeniedHttpException('This route is only available for authenticated requests using OAuth2.');
    }
    if ($this->config->get('disable_openid_connect')) {
      throw new NotFoundHttpException('Not Found');
    }
    return new JsonResponse((new JwksEntity())->getKeys());
  }

}
