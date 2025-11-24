<?php

declare(strict_types = 1);

namespace Drupal\sms;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\sms\Entity\SmsGatewayInterface;
use Drupal\sms\Provider\SmsProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides delivery reports acknowledgement and passes to the correct gateway.
 */
class DeliveryReportController implements ContainerInjectionInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Creates an new delivery report controller.
   *
   * @param \Drupal\sms\Provider\SmsProviderInterface $smsProvider
   *   The SMS service provider.
   */
  public function __construct(
    protected SmsProviderInterface $smsProvider,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('sms.provider'),
    );
  }

  /**
   * Acknowledges delivery reports and passes them to the correct gateway.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\sms\Entity\SmsGatewayInterface $sms_gateway
   *   The gateway which is handling the the delivery report.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response object to return.
   */
  public function processDeliveryReport(Request $request, SmsGatewayInterface $sms_gateway) {
    return $this->smsProvider->processDeliveryReport($request, $sms_gateway);
  }

}
