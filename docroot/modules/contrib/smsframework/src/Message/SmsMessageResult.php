<?php

declare(strict_types = 1);

namespace Drupal\sms\Message;

use Drupal\sms\Exception\SmsException;

/**
 * The result of an SMS messaging transaction.
 */
class SmsMessageResult implements SmsMessageResultInterface {

  /**
   * The error of the message, or NULL if unknown.
   *
   * @var string|null
   */
  protected ?string $error = NULL;

  /**
   * The error message as provided by the gateway API.
   *
   * @var string
   */
  protected string $errorMessage = '';

  /**
   * The message delivery reports.
   *
   * @var \Drupal\sms\Message\SmsDeliveryReportInterface[]
   */
  protected array $reports = [];

  /**
   * The credit balance after this message is sent, or NULL if unknown.
   *
   * This number is in the SMS gateway's chosen denomination.
   *
   * @var float|null
   */
  protected ?float $creditsBalance = NULL;

  /**
   * The credits consumed to process this message, or NULL if unknown.
   *
   * This number is in the SMS gateway's chosen denomination.
   *
   * @var float|null
   */
  protected ?float $creditsUsed = NULL;

  /**
   * {@inheritdoc}
   */
  public function getError() {
    return $this->error;
  }

  /**
   * {@inheritdoc}
   */
  public function setError($error) {
    $this->error = $error;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getErrorMessage() {
    return $this->errorMessage;
  }

  /**
   * {@inheritdoc}
   */
  public function setErrorMessage($message) {
    $this->errorMessage = $message;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getReport($recipient) {
    foreach ($this->reports as $report) {
      if ($report->getRecipient() == $recipient) {
        return $report;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getReports() {
    return $this->reports;
  }

  /**
   * {@inheritdoc}
   */
  public function setReports(array $reports) {
    $this->reports = $reports;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addReport(SmsDeliveryReportInterface $report) {
    $this->reports[] = $report;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreditsBalance() {
    return $this->creditsBalance;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreditsBalance($balance) {
    if (is_numeric($balance) || is_null($balance)) {
      $this->creditsBalance = $balance;
    }
    else {
      throw new SmsException(sprintf('Credit balance set is a %s', gettype($balance)));
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreditsUsed() {
    return $this->creditsUsed;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreditsUsed($credits_used) {
    if (is_numeric($credits_used) || is_null($credits_used)) {
      $this->creditsUsed = $credits_used;
    }
    else {
      throw new SmsException(sprintf('Credit used is a %s', gettype($credits_used)));
    }
    return $this;
  }

}
