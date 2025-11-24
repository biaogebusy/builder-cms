<?php

declare(strict_types = 1);

namespace Drupal\sms;

/**
 * Defines a container for SMS objects for processing and a request response.
 */
class SmsProcessingResponse {

  /**
   * An array of messages to process.
   *
   * @var \Drupal\sms\Message\SmsMessageInterface[]
   */
  protected array $messages = [];

  /**
   * The response to pass to the request controller.
   *
   * @var mixed
   */
  protected mixed $response;

  /**
   * Get an array of messages to process.
   *
   * @return \Drupal\sms\Message\SmsMessageInterface[]
   *   An array of messages to process.
   */
  public function getMessages() {
    return $this->messages;
  }

  /**
   * Set an array of messages to process.
   *
   * @param \Drupal\sms\Message\SmsMessageInterface[] $messages
   *   An array of messages to process.
   *
   * @return $this
   *   Return this object for chaining.
   */
  public function setMessages(array $messages) {
    $this->messages = $messages;
    return $this;
  }

  /**
   * Get the response to pass to the request controller.
   *
   * @return mixed
   *   The response to pass to the request controller.
   */
  public function getResponse() {
    return $this->response;
  }

  /**
   * Set the response to pass to the request controller.
   *
   * @param mixed $response
   *   Set the response to pass to the request controller.
   *
   * @return $this
   *   Return this object for chaining.
   */
  public function setResponse($response) {
    $this->response = $response;
    return $this;
  }

}
