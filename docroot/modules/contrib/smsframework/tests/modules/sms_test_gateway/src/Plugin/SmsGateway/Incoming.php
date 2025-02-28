<?php

declare(strict_types = 1);

namespace Drupal\sms_test_gateway\Plugin\SmsGateway;

use Drupal\sms\Message\SmsDeliveryReport;
use Drupal\sms\Message\SmsMessageResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Component\Serialization\Json;
use Drupal\sms\Direction;
use Drupal\sms\Entity\SmsGatewayInterface;
use Drupal\sms\Message\SmsMessage;
use Drupal\sms\Message\SmsMessageInterface;
use Drupal\sms\Plugin\SmsGatewayPluginBase;
use Drupal\sms\SmsProcessingResponse;

/**
 * Defines a gateway supporting incoming route.
 *
 * @SmsGateway(
 *   id = "incoming",
 *   label = @Translation("Incoming"),
 *   incoming = TRUE,
 *   incoming_route = TRUE,
 * )
 */
final class Incoming extends SmsGatewayPluginBase {

  /**
   * {@inheritdoc}
   */
  public function send(SmsMessageInterface $sms) {
  }

  /**
   * Process an incoming message POST request.
   *
   * This callback expects a 'messages' POST value containing JSON.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\sms\Entity\SmsGatewayInterface $sms_gateway
   *   The gateway instance.
   *
   * @return \Drupal\sms\SmsProcessingResponse
   *   A SMS processing response task.
   */
  public function processIncoming(Request $request, SmsGatewayInterface $sms_gateway): SmsProcessingResponse {
    $json = Json::decode($request->getContent());
    $raw_messages = $json['messages'];

    // JSON property to SmsMessage method setters mapping.
    $sms_properties = [
      'sender_number' => 'setSenderNumber',
      'message' => 'setMessage',
      'recipients' => 'addRecipients',
    ];

    $messages = [];
    foreach ($raw_messages as $raw_message) {
      $result = new SmsMessageResult();

      foreach ($raw_message['recipients'] as $recipient) {
        $report = (new SmsDeliveryReport())
          ->setRecipient($recipient);
        $result->addReport($report);
      }

      $message = (new SmsMessage())
        ->setDirection(Direction::INCOMING)
        ->setGateway($sms_gateway)
        ->setResult($result);

      foreach ($sms_properties as $property => $method) {
        if (array_key_exists($property, $raw_message)) {
          $value = $raw_message[$property];
          call_user_func_array([$message, $method], [$value]);
        }
      }

      $messages[] = $message;
    }

    $response = new Response('', 204);
    $task = (new SmsProcessingResponse())
      ->setResponse($response)
      ->setMessages($messages);

    return $task;
  }

}
