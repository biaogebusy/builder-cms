<?php

namespace Drupal\simple_oauth\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\simple_oauth\Entities\JwksEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the JWKS endpoint.
 */
class Jwks implements ContainerInjectionInterface {

  use AutowireTrait;

  /**
   * The authenticated user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $user;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  private function __construct(AccountProxyInterface $user, ConfigFactoryInterface $config_factory, protected ClassResolverInterface $classResolver) {
    $this->user = $user->getAccount();
    $this->configFactory = $config_factory;
  }

  /**
   * The controller.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function handle() {
    $config = $this->configFactory->get('simple_oauth.settings');
    if ($config->get('disable_openid_connect')) {
      throw new NotFoundHttpException('Not Found');
    }
    return new JsonResponse(($this->classResolver->getInstanceFromDefinition(JwksEntity::class))->getKeys());
  }

}
