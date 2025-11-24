<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Functional;

use Drupal\Core\Url;
use Drupal\sms\Entity\SmsGatewayInterface;
use GuzzleHttp\Client;

/**
 * Tests incoming route endpoint.
 *
 * @group SMS Framework
 */
final class SmsFrameworkIncomingBrowserTest extends SmsFrameworkBrowserTestBase {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $httpClient;

  /**
   * An incoming gateway instance.
   *
   * @var \Drupal\sms\Entity\SmsGatewayInterface
   */
  protected SmsGatewayInterface $incomingGateway;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->httpClient = $this->container->get('http_client');

    $this->incomingGateway = $this->createMemoryGateway(['plugin' => 'incoming']);
    $this->incomingGateway
      ->setSkipQueue(TRUE)
      ->save();
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Test incoming route endpoint provided by 'incoming' gateway.
   */
  public function testIncomingRouteEndpoint(): void {
    $messages[0] = [
      'message' => $this->randomString(),
      'recipients' => $this->randomPhoneNumbers(),
    ];
    $messages[1] = [
      'message' => $this->randomString(),
      'recipients' => $this->randomPhoneNumbers(),
    ];

    $url = Url::fromRoute('sms.incoming.receive.' . $this->incomingGateway->id())
      ->setRouteParameter('sms_gateway', $this->incomingGateway->id())
      ->setAbsolute()
      ->toString();

    $options = [
      'json' => [
        'messages' => $messages,
      ],
    ];

    static::assertTrue(TRUE, sprintf('POST request to %s', $url));
    $response = $this->httpClient
      ->post($url, $options);

    static::assertEquals(204, $response->getStatusCode(), 'HTTP code is 204');
    static::assertEmpty((string) $response->getBody(), 'Response body is empty.');

    $incoming_messages = $this->getIncomingMessages($this->incomingGateway);
    static::assertCount(count($messages), $incoming_messages, 'There are 2 messages');
    foreach ($messages as $i => $message) {
      static::assertEquals($message['message'], $incoming_messages[$i]->getMessage(), "Message $i contents are same.");
      static::assertEquals($message['recipients'], $incoming_messages[$i]->getRecipients(), "Message $i recipients are same.");
    }
  }

}
