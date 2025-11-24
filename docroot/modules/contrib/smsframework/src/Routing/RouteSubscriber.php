<?php

declare(strict_types = 1);

namespace Drupal\sms\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Drupal\sms\Entity\SmsGateway;

/**
 * Subscriber for SMS Framework routes.
 */
class RouteSubscriber implements ContainerInjectionInterface {

  /**
   * Constructs a new SMS Framework RouteSubscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
    );
  }

  /**
   * Returns a set of route objects.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   A route collection.
   */
  public function routes() {
    $sms_settings = $this->configFactory->get('sms.settings');
    $collection = new RouteCollection();

    // Phone number verification.
    $path_verify = $sms_settings->get('page.verify');
    // String length must include at least a slash + another character.
    if (isset($path_verify) && mb_strlen($path_verify) >= 2) {
      $collection->add('sms.phone.verify', new Route(
        $path_verify,
        [
          '_form' => '\Drupal\sms\Form\VerifyPhoneNumberForm',
          '_title' => 'Verify a phone number',
        ],
        [
          '_permission' => 'sms verify phone number',
        ],
      ));
    }

    /** @var \Drupal\sms\Entity\SmsGatewayInterface $gateway */
    foreach (SmsGateway::loadMultiple() as $id => $gateway) {
      if ($gateway->supportsReportsPush()) {
        $path = $gateway->getPushReportPath();
        if (isset($path) && mb_strlen($path) >= 2 && mb_substr($path, 0, 1) == '/') {
          $route = (new Route($path))
            ->setDefault('_controller', '\Drupal\sms\DeliveryReportController::processDeliveryReport')
            ->setDefault('_sms_gateway_push_endpoint', $id)
            ->setRequirement('_sms_gateway_supports_pushed_reports', 'TRUE');
          $collection->add('sms.delivery_report.receive.' . $id, $route);
        }
      }

      if ($gateway->autoCreateIncomingRoute()) {
        $path = $gateway->getPushIncomingPath();
        if (isset($path) && mb_strlen($path) >= 2 && mb_substr($path, 0, 1) == '/') {
          $parameters['sms_gateway']['type'] = 'entity:sms_gateway';
          $route = (new Route($path))
            ->setDefault('sms_gateway', $id)
            ->setDefault('_controller', '\Drupal\sms\SmsIncomingController::processIncoming')
            ->setRequirement('_access', 'TRUE')
            ->setOption('parameters', $parameters)
            ->setMethods(['POST']);
          $collection->add('sms.incoming.receive.' . $id, $route);
        }
      }
    }

    return $collection;
  }

}
