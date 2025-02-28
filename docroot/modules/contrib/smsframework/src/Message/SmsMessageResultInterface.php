<?php

declare(strict_types = 1);

namespace Drupal\sms\Message;

/**
 * Contains information on SMS message results.
 */
interface SmsMessageResultInterface {

  /**
   * Gets the error of the message.
   *
   * @return string|null
   *   A error code from \Drupal\sms\Message\SmsMessageResultError, or NULL if
   *   there was no error.
   */
  public function getError();

  /**
   * Sets the error of the message.
   *
   * Usually a setting an error on a result indicates something went wrong with
   * the entire transaction.
   *
   * @param string|null $error
   *   A error code from \Drupal\sms\Message\SmsMessageResultError, or NULL if
   *   unknown.
   *
   * @return $this
   *   Returns this result object for chaining.
   */
  public function setError($error);

  /**
   * Gets the error message.
   *
   * @return string
   *   The error message as provided by the gateway API.
   */
  public function getErrorMessage();

  /**
   * Sets the error message.
   *
   * @param string $message
   *   The error message as provided by the gateway API.
   *
   * @return $this
   *   Returns this report object for chaining.
   */
  public function setErrorMessage($message);

  /**
   * Gets the delivery report for a particular recipient.
   *
   * @param string $recipient
   *   The number of the recipient for which the report is to be retrieved.
   *
   * @return \Drupal\sms\Message\SmsDeliveryReportInterface|null
   *   A delivery report object, or NULL if there is no report for the
   *   recipient.
   *
   * @see SmsMessageResultInterface::getReports()
   */
  public function getReport($recipient);

  /**
   * Gets the delivery reports for all recipients.
   *
   * @return \Drupal\sms\Message\SmsDeliveryReportInterface[]
   *   An array of delivery reports.
   */
  public function getReports();

  /**
   * Sets the delivery reports for all recipients.
   *
   * @param \Drupal\sms\Message\SmsDeliveryReportInterface[] $reports
   *   An array of delivery reports.
   *
   * @return $this
   *   Returns this result object for chaining.
   */
  public function setReports(array $reports);

  /**
   * Adds a delivery report to the result.
   *
   * @param \Drupal\sms\Message\SmsDeliveryReportInterface $report
   *   A delivery report.
   *
   * @return $this
   *   Returns this result object for chaining.
   */
  public function addReport(SmsDeliveryReportInterface $report);

  /**
   * Gets the credit balance after this transaction.
   *
   * @return float|null
   *   The credit balance after the message is processed, or NULL if unknown.
   */
  public function getCreditsBalance();

  /**
   * Sets the credit balance after this transaction.
   *
   * @param float|null $balance
   *   The credit balance after the message is processed, or NULL if unknown.
   *
   * @return $this
   *   Returns this result object for chaining.
   *
   * @throws \Drupal\sms\Exception\SmsException
   *   Thrown if balance set is an invalid variable type.
   */
  public function setCreditsBalance($balance);

  /**
   * Gets the credits consumed for this transaction.
   *
   * @return float|null
   *   The credits consumed for this transaction, or NULL if unknown.
   */
  public function getCreditsUsed();

  /**
   * Sets the credits consumed for this transaction.
   *
   * @param float|null $credits_used
   *   The credits consumed for this transaction, or NULL if unknown.
   *
   * @return $this
   *   Returns this result object for chaining.
   *
   * @throws \Drupal\sms\Exception\SmsException
   *   Thrown if credits set is an invalid variable type.
   */
  public function setCreditsUsed($credits_used);

}
