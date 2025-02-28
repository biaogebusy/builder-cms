<?php

declare(strict_types = 1);

namespace Drupal\sms;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\sms\Entity\SmsGatewayInterface;
use Drupal\sms\Provider\SmsProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

/**
 * Provides a controller for receiving incoming messages.
 */
class SmsIncomingController extends ControllerBase {

  /**
   * The argument resolver.
   *
   * @var \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface
   */
  protected $argumentResolver;

  /**
   * The SMS Provider.
   *
   * @var \Drupal\sms\Provider\SmsProviderInterface
   */
  protected $smsProvider;

  /**
   * Creates an incoming route controller.
   *
   * @param \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface $controller_resolver
   *   The argument resolver.
   * @param \Drupal\sms\Provider\SmsProviderInterface $sms_provider
   *   The SMS service provider.
   */
  public function __construct(ArgumentResolverInterface $controller_resolver, SmsProviderInterface $sms_provider) {
    $this->argumentResolver = $controller_resolver;
    $this->smsProvider = $sms_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_kernel.controller.argument_resolver'),
      $container->get('sms.provider')
    );
  }

  /**
   * Receives incoming messages for a gateway.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current HTTP request.
   * @param \Drupal\sms\Entity\SmsGatewayInterface $sms_gateway
   *   The gateway which is handling the the incoming request.
   *
   * @return mixed
   *   A response to return.
   */
  public function processIncoming(Request $request, SmsGatewayInterface $sms_gateway) {
    $controller = [$sms_gateway->getPlugin(), 'processIncoming'];
    $arguments = $this->argumentResolver
      ->getArguments($request, $controller);

    /** @var \Drupal\sms\SmsProcessingResponse $response */
    $response = call_user_func_array($controller, $arguments);

    foreach ($response->getMessages() as $message) {
      $this->smsProvider->queue($message);
    }

    return $response->getResponse();
  }

}
