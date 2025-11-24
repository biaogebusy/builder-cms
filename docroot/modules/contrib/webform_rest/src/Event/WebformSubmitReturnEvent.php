<?php

namespace Drupal\webform_rest\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\rest\ModifiedResourceResponse;

/**
 * Class WebformSubmitReturnEvent, an event to change the return.
 *
 * @package Drupal\webform_rest\Event
 */
class WebformSubmitReturnEvent extends Event {
  const WEBFORM_SUBMIT_RETURN = 'webform_rest.submit.return';

  /**
   * @var string
   */
  private $type;

  /**
   * @var array
   */
  protected $submissionValues;

  /**
   * @var array
   */
  protected $returnData;

  /**
   * @var int
   */
  protected $httpCode;

  /**
   * Construct for injection dependency.
   *
   * @param string $type
   * @param array $submissionValues
   * @param array $returnData
   * @param int $httpCode
   */
  public function __construct(string $type, array $submissionValues, array &$returnData, int &$httpCode) {
    $this->type = $type;
    $this->submissionValues = $submissionValues;
    $this->returnData = &$returnData;
    $this->httpCode = &$httpCode;
  }

  /**
   * Get return type, success or error.
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   * Get submit values.
   */
  public function getSubmissionValues(): array {
    return $this->submissionValues;
  }

  /**
   * Get response from request.
   */
  public function &getReturnData(): array {
    return $this->returnData;
  }

  /**
   * Get response from request.
   */
  public function &getHttpCode(): int {
    return $this->httpCode;
  }

}