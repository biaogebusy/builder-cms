<?php

declare(strict_types = 1);

namespace Drupal\sms\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\sms\Message\SmsMessageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Default implementation of SMS gateway plugin.
 */
interface SmsGatewayPluginInterface extends ConfigurableInterface, DependentPluginInterface, PluginFormInterface, PluginInspectionInterface {

  /**
   * Sends an SMS.
   *
   * @param \Drupal\sms\Message\SmsMessageInterface $sms
   *   The sms to be sent.
   *
   * @return \Drupal\sms\Message\SmsMessageResultInterface
   *   The result of the sms messaging operation.
   */
  public function send(SmsMessageInterface $sms);

  /**
   * Returns the credit balance available on this gateway.
   *
   * The 'credit_balance_available' plugin annotation should be set to inform
   * the framework whether this gateway supports balance queries.
   *
   * @return float|null
   *   The credit balance of the gateway, or NULL if unknown.
   */
  public function getCreditsBalance();

  /**
   * Parses incoming delivery reports and returns the created delivery reports.
   *
   * The request contains delivery reports pushed to the site in a format
   * supplied by the gateway API. This method transforms the raw request into
   * delivery report objects usable by SMS Framework.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object containing the unprocessed delivery reports.
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   HTTP response to return to the server pushing the raw delivery reports.
   *
   * @return \Drupal\sms\Message\SmsDeliveryReportInterface[]
   *   An array of delivery reports created from the request.
   */
  public function parseDeliveryReports(Request $request, Response $response);

  /**
   * Gets delivery reports from the gateway.
   *
   * @param string[]|null $message_ids
   *   A list of specific message ID's to pull, or NULL to get any reports which
   *   have not been requested previously.
   *
   * @return \Drupal\sms\Message\SmsDeliveryReportInterface[]
   *   An array of the delivery reports which have been pulled.
   */
  public function getDeliveryReports(?array $message_ids = NULL);

}
